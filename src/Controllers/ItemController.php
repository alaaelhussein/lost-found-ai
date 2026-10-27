<?php
/**
 * ItemController : création des annonces perdues et trouvées.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Throttle;
use App\Core\View;
use App\Models\Item;
use App\Models\Claim;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\MatchModel;
use App\Services\MatchingService;
use App\Services\PhotoStorage;
use DateTimeImmutable;
use Throwable;

final class ItemController
{
    private const TITLE_MAX = 150;
    private const CATEGORY_MAX = 50;
    private const COLOR_MAX = 30;
    private const LOCATION_MAX = 150;
    private const DESCRIPTION_MAX = 2000;


    /** GET /items/create */
    public function showCreate(): void
    {
        Auth::requireVerified();

        $type = ($_GET['type'] ?? 'lost') === 'found' ? 'found' : 'lost';
        $this->renderForm($type, [], []);
    }

    /** GET /items */
    public function index(): void
    {
        $filters = [
            'type' => (string) ($_GET['type'] ?? ''),
            'q' => trim((string) ($_GET['q'] ?? '')),
            'category' => trim((string) ($_GET['category'] ?? '')),
        ];
        View::render('items/index', [
            'title' => 'Annonces',
            'items' => (new Item())->search($filters),
            'filters' => $filters,
        ]);
    }

    /** GET /items/{id} */
    public function show(int $id): void
    {
        $item = (new Item())->findById($id);
        if ($item === null) {
            View::error(404, 'Cette annonce est introuvable.');
            return;
        }

        // Correspondances : visibles uniquement par l'auteur de l'annonce, et
        // recalculées à chaque visite (de nouvelles annonces ont pu arriver).
        $isOwner = Auth::check() && (int) Auth::id() === (int) $item['user_id'];
        $matches = $isOwner && $item['status'] === 'open'
            ? (new MatchingService())->refreshForItem($item)
            : [];

        // Demandes de restitution (objets trouvés) :
        //   - l'auteur voit toutes les demandes reçues pour les accepter/refuser ;
        //   - un autre utilisateur voit l'état de SA demande s'il en a fait une.
        $claims = new Claim();
        $isFound = $item['type'] === 'found';

        View::render('items/show', [
            'title' => $item['title'],
            'item' => $item,
            'isOwner' => $isOwner,
            'matches' => $matches,
            'claims' => $isOwner && $isFound ? $claims->forItem($id) : [],
            'myClaim' => !$isOwner && $isFound && Auth::check() ? $claims->findByItemAndClaimant($id, (int) Auth::id()) : null,
        ]);
    }

    /** POST /items/{id}/contact */
    public function contact(int $id): void
    {
        Auth::requireVerified();
        Csrf::verify();
        // Évite qu'un compte contacte des dizaines d'annonces à la chaîne
        Throttle::check('contact', 'user:' . Auth::id(), 10, 3600);

        $target = (new Item())->findById($id);
        if ($target === null || (int) $target['user_id'] === (int) Auth::id()) {
            View::error(422, 'Cette annonce ne peut pas être contactée.');
            return;
        }

        if ($target['status'] !== 'open') {
            View::error(422, 'Cette annonce n’est plus ouverte aux nouveaux contacts.');
            return;
        }

        $conversationId = (new Conversation())->findOrCreateForItem($id, (int) Auth::id());

        Flash::add('success', 'La conversation sécurisée est prête.');
        redirect('/conversations/' . $conversationId);
    }

    /** GET /my-items */
    public function mine(): void
    {
        Auth::requireLogin();
        View::render('items/mine', [
            'title' => 'Mes annonces',
            'items' => (new Item())->findByUser((int) Auth::id()),
            'matchCounts' => (new MatchModel())->countsForUser(
                (int) Auth::id(),
                (int) Config::get('matching.min_score', 60)
            ),
            'myClaims' => (new Claim())->forClaimant((int) Auth::id()),
            'shipments' => (new Order())->forFinder((int) Auth::id()),
        ]);
    }

    /** POST /items/create */
    public function create(): void
    {
        Auth::requireVerified();
        Csrf::verify();
        Throttle::check('item_create', 'user:' . Auth::id(), 10, 3600);

        $type = ($_POST['type'] ?? '') === 'found' ? 'found' : 'lost';
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $color = trim((string) ($_POST['color'] ?? ''));
        $location = trim((string) ($_POST['location_label'] ?? ''));
        $dateEvent = trim((string) ($_POST['date_event'] ?? ''));
        // Objet trouvé : question que seul le vrai propriétaire saura résoudre
        $question = $type === 'found' ? trim((string) ($_POST['verification_question'] ?? '')) : '';
        $old = [
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'color' => $color,
            'location_label' => $location,
            'date_event' => $dateEvent,
            'verification_question' => $question,
        ];
        $errors = $this->validate($title, $description, $category, $color, $location, $dateEvent);
        if (mb_strlen($question) > 255) {
            $errors['verification_question'] = 'La question doit contenir 255 caractères maximum.';
        }
        $photoPath = null;

        if ($errors === []) {
            $photoPath = PhotoStorage::publicPhotos()->store($_FILES['photo'] ?? null, $errors);
        }

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm($type, $errors, $old);
            return;
        }

        try {
            $itemId = (new Item())->create(
                Auth::id(),
                $type,
                $title,
                $description,
                $category,
                $color,
                $location,
                $dateEvent,
                $photoPath,
                $question
            );
        } catch (Throwable $e) {
            // L'annonce n'existe pas : on ne laisse pas sa photo en ligne
            PhotoStorage::publicPhotos()->delete($photoPath);
            throw $e;
        }

        // Recherche immédiate des correspondances avec les annonces existantes
        $matchCount = count((new MatchingService())->refreshForItem((new Item())->findById($itemId)));

        Flash::add('success', $matchCount > 0
            ? 'Votre annonce est publiée. ' . $matchCount . ' correspondance' . ($matchCount > 1 ? 's possibles ont été trouvées' : ' possible a été trouvée') . ' !'
            : 'Votre annonce est publiée. Aucune correspondance pour l’instant : nous comparerons chaque nouvelle annonce avec la vôtre.');
        redirect('/items/' . $itemId . ($matchCount > 0 ? '#correspondances' : ''));
    }

    /** POST /items/{id}/status */
    public function updateStatus(int $id): void
    {
        Auth::requireLogin();
        Csrf::verify();

        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, ['open', 'resolved'], true)) {
            View::error(422, 'Statut d’annonce invalide.');
            return;
        }

        if (!(new Item())->updateStatus($id, (int) Auth::id(), $status)) {
            View::error(404, 'Annonce introuvable ou accès refusé.');
            return;
        }

        Flash::add('success', $status === 'resolved'
            ? 'Annonce marquée comme résolue.'
            : 'Annonce remise en ligne.');
        redirect('/my-items');
    }

    /** POST /items/{id}/delete */
    public function delete(int $id): void
    {
        Auth::requireLogin();
        Csrf::verify();

        $items = new Item();
        $item = $items->findById($id);

        // Une livraison payée en cours dépend de cette annonce (cascade SQL) :
        // la supprimer ferait disparaître le suivi du colis et du pourboire.
        if ($item !== null && (int) $item['user_id'] === (int) Auth::id()
            && (new Order())->countActiveForItem($id) > 0) {
            Flash::add('error', 'Une livraison est en cours pour cet objet : l’annonce pourra être supprimée une fois l’objet reçu.');
            redirect('/my-items');
        }

        // Noms des photos de preuve relevés AVANT la suppression (les demandes
        // partent en cascade avec l'annonce, mais pas les fichiers)
        $proofPhotos = $item === null ? [] : (new Claim())->proofPhotosForItem($id);

        if ($item === null || !$items->deleteOwned($id, (int) Auth::id())) {
            View::error(404, 'Annonce introuvable ou accès refusé.');
            return;
        }

        // La photo est publique (servie par /uploads) : sans cette suppression,
        // elle resterait accessible par son URL après la suppression de l'annonce.
        PhotoStorage::publicPhotos()->delete($item['photo_path']);
        foreach ($proofPhotos as $proofPhoto) {
            PhotoStorage::privateProofs()->delete($proofPhoto);
        }

        Flash::add('success', 'Annonce supprimée.');
        redirect('/my-items');
    }

    private function validate(
        string $title,
        string $description,
        string $category,
        string $color,
        string $location,
        string $dateEvent
    ): array {
        $errors = [];
        if ($title === '' || mb_strlen($title) > self::TITLE_MAX) {
            $errors['title'] = 'Le titre est obligatoire et doit contenir 150 caractères maximum.';
        }
        if ($description === '' || mb_strlen($description) > self::DESCRIPTION_MAX) {
            $errors['description'] = 'La description est obligatoire et doit contenir 2000 caractères maximum.';
        }
        if ($category === '' || mb_strlen($category) > self::CATEGORY_MAX) {
            $errors['category'] = 'La catégorie est obligatoire et doit contenir 50 caractères maximum.';
        }
        if (mb_strlen($color) > self::COLOR_MAX) {
            $errors['color'] = 'La couleur doit contenir 30 caractères maximum.';
        }
        if ($location === '' || mb_strlen($location) > self::LOCATION_MAX) {
            $errors['location_label'] = 'Le lieu est obligatoire et doit contenir 150 caractères maximum.';
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateEvent);
        if ($date === false || $date->format('Y-m-d') !== $dateEvent) {
            $errors['date_event'] = 'La date est invalide.';
        } elseif ($date > new DateTimeImmutable('today')) {
            $errors['date_event'] = 'La date ne peut pas être dans le futur.';
        }

        return $errors;
    }

    private function renderForm(string $type, array $errors, array $old): void
    {
        View::render('items/create', [
            'title' => $type === 'lost' ? 'Déclarer un objet perdu' : 'Déclarer un objet trouvé',
            'type' => $type,
            'errors' => $errors,
            'old' => $old,
        ]);
    }
}
