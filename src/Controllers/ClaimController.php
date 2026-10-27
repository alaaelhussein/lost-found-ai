<?php
/**
 * ClaimController : demandes de restitution (preuve de propriété).
 *
 * Routes :
 *   GET  /items/{id}/claim     formulaire « Cet objet est à moi »
 *   POST /items/{id}/claim     envoi de la demande
 *   POST /claims/{id}/approve  la personne qui a trouvé l'objet accepte
 *   POST /claims/{id}/reject   ... ou refuse
 *   GET  /claims/{id}/photo    photo de preuve (PRIVÉE : 2 personnes seulement)
 *
 * Qui peut faire quoi :
 *   - demander : un compte vérifié, sur une annonce « trouvé » encore ouverte,
 *     qui n'est pas la sienne, une seule fois par objet ;
 *   - décider : uniquement l'auteur de l'annonce, une seule demande acceptée par objet ;
 *   - voir la photo : le demandeur et l'auteur de l'annonce.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Throttle;
use App\Core\View;
use App\Models\Claim;
use App\Models\Conversation;
use App\Models\Item;
use App\Services\Notifier;
use App\Services\PhotoStorage;
use App\Services\ShippingService;
use PDOException;

final class ClaimController
{
    private const ANSWER_MAX = 255;
    private const PROOF_MIN = 20;
    private const PROOF_MAX = 1000;

    /** GET /items/{id}/claim */
    public function create(int $id): void
    {
        Auth::requireVerified();
        $item = $this->claimableItem($id);
        if ($item === null) {
            return;
        }

        $this->renderForm($item, [], []);
    }

    /** POST /items/{id}/claim */
    public function store(int $id): void
    {
        Auth::requireVerified();
        Csrf::verify();
        Throttle::check('claim', 'user:' . Auth::id(), 5, 3600);

        $item = $this->claimableItem($id);
        if ($item === null) {
            return;
        }

        $question = (new Item())->findVerificationQuestion($id);
        $answer = trim((string) ($_POST['answer'] ?? ''));
        $proof = trim((string) ($_POST['proof'] ?? ''));
        $old = ['answer' => $answer, 'proof' => $proof];

        $errors = [];
        if ($question !== null && ($answer === '' || mb_strlen($answer) > self::ANSWER_MAX)) {
            $errors['answer'] = 'Répondez à la question (255 caractères maximum).';
        }
        $proofLength = mb_strlen($proof);
        if ($proofLength < self::PROOF_MIN || $proofLength > self::PROOF_MAX) {
            $errors['proof'] = 'Décrivez au moins un détail précis (entre 20 et 1000 caractères).';
        }
        if (($_POST['certify'] ?? '') !== '1') {
            $errors['certify'] = 'Cochez cette case pour confirmer que l’objet vous appartient.';
        }

        // Photo stockée dans storage/proofs (privé), jamais dans public/uploads
        $storage = PhotoStorage::privateProofs();
        $photo = $errors === [] ? $storage->store($_FILES['photo'] ?? null, $errors) : null;

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm($item, $errors, $old);
            return;
        }

        $claims = new Claim();
        try {
            $claimId = $claims->create($id, (int) Auth::id(), $question !== null ? $answer : null, $proof, $photo);
        } catch (PDOException $e) {
            $storage->delete($photo);
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            // Contrainte UNIQUE (item, demandeur) : demande déjà envoyée (double clic)
            Flash::add('info', 'Vous avez déjà envoyé une demande pour cet objet.');
            redirect('/items/' . $id);
        }

        (new Notifier())->claimReceived($claims->findById($claimId));

        Flash::add('success', 'Demande envoyée ! La personne qui a trouvé l’objet va comparer vos réponses. Vous serez prévenu par email.');
        redirect('/items/' . $id);
    }

    /** POST /claims/{id}/approve */
    public function approve(int $id): void
    {
        $this->decide($id, true);
    }

    /** POST /claims/{id}/reject */
    public function reject(int $id): void
    {
        $this->decide($id, false);
    }

    /** GET /claims/{id}/photo */
    public function photo(int $id): void
    {
        Auth::requireLogin();
        $claim = (new Claim())->findById($id);
        $me = (int) Auth::id();

        // Contrôle d'accès : seules les deux personnes concernées voient la preuve
        if ($claim === null || $claim['proof_photo'] === null
            || ($me !== (int) $claim['claimant_id'] && $me !== (int) $claim['finder_id'])) {
            View::error(404, 'Photo introuvable.');
            return;
        }

        $file = PhotoStorage::privateProofs()->path($claim['proof_photo']);
        if (!is_file($file)) {
            View::error(404, 'Photo introuvable.');
            return;
        }

        // On remplace les en-têtes HTML envoyés par bootstrap.php
        $types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        header('Content-Type: ' . ($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($file));
        header('Content-Disposition: inline; filename="preuve.' . pathinfo($file, PATHINFO_EXTENSION) . '"');
        readfile($file);
    }

    /**
     * Décision de la personne qui a trouvé l'objet.
     */
    private function decide(int $id, bool $approve): void
    {
        Auth::requireVerified();
        Csrf::verify();

        $claims = new Claim();
        $claim = $claims->findById($id);
        if ($claim === null || (int) $claim['finder_id'] !== (int) Auth::id()) {
            View::error(404, 'Demande introuvable ou accès refusé.');
            return;
        }

        if ($approve && $claims->hasApproved((int) $claim['item_id'])) {
            Flash::add('error', 'Vous avez déjà accepté une demande pour cet objet.');
            redirect('/items/' . $claim['item_id'] . '#demandes');
        }

        if (!$claims->decide($id, $approve ? 'approved' : 'rejected')) {
            Flash::add('info', 'Cette demande a déjà été traitée.');
            redirect('/items/' . $claim['item_id'] . '#demandes');
        }

        // Livraison désactivée (ShippingService::isEnabled) : la restitution se
        // fait en main propre. On ouvre tout de suite la conversation entre les
        // deux personnes pour qu'elles fixent un rendez-vous.
        $delivery = ShippingService::isEnabled();
        $conversationId = null;
        if ($approve && !$delivery) {
            $conversationId = (new Conversation())->findOrCreateForItem((int) $claim['item_id'], (int) $claim['claimant_id']);
        }

        (new Notifier())->claimDecided($claim, $approve, $conversationId);

        if (!$approve) {
            Flash::add('success', 'Demande refusée. La personne en est informée.');
            redirect('/items/' . $claim['item_id'] . '#demandes');
        }
        if ($delivery) {
            Flash::add('success', 'Demande acceptée. ' . $claim['claimant_name'] . ' va pouvoir commander la livraison : vous recevrez l’étiquette par email.');
            redirect('/items/' . $claim['item_id'] . '#demandes');
        }

        Flash::add('success', 'Demande acceptée. Convenez avec ' . $claim['claimant_name'] . ' d’un rendez-vous dans un lieu public pour lui rendre son objet.');
        redirect('/conversations/' . $conversationId);
    }

    /**
     * Annonce sur laquelle l'utilisateur connecté peut faire une demande, ou
     * null (et une page d'erreur est affichée).
     */
    private function claimableItem(int $id): ?array
    {
        $item = (new Item())->findById($id);

        if ($item === null || $item['type'] !== 'found') {
            View::error(404, 'Seuls les objets trouvés peuvent être réclamés.');
            return null;
        }
        if ((int) $item['user_id'] === (int) Auth::id()) {
            View::error(422, 'Vous ne pouvez pas réclamer votre propre annonce.');
            return null;
        }
        if ($item['status'] !== 'open') {
            View::error(422, 'Cet objet n’accepte plus de demandes.');
            return null;
        }
        if ((new Claim())->findByItemAndClaimant($id, (int) Auth::id()) !== null) {
            Flash::add('info', 'Vous avez déjà envoyé une demande pour cet objet.');
            redirect('/items/' . $id);
        }

        return $item;
    }

    private function renderForm(array $item, array $errors, array $old): void
    {
        View::render('claims/create', [
            'title'    => 'Cet objet est à moi',
            'item'     => $item,
            'question' => (new Item())->findVerificationQuestion((int) $item['id']),
            'errors'   => $errors,
            'old'      => $old,
        ]);
    }
}
