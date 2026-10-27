<?php
/**
 * AdminController : modération des annonces signalées.
 *
 * Routes (toutes réservées aux administrateurs, voir Auth::requireAdmin) :
 *   GET  /admin/reports              liste des annonces signalées
 *   POST /admin/reports/{id}/dismiss annonce conforme : on classe les signalements (id = annonce)
 *   POST /admin/items/{id}/delete    annonce supprimée (photo et preuves comprises)
 *   POST /admin/users/{id}/delete    compte de l'auteur supprimé (spammeur, fraudeur)
 *
 * Devenir administrateur : php bin/make-admin.php email@exemple.fr
 * (aucune page web ne permet de se donner ce rôle).
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;
use App\Models\Claim;
use App\Models\Item;
use App\Models\Order;
use App\Models\Report;
use App\Models\User;
use App\Services\AccountService;
use App\Services\PhotoStorage;

final class AdminController
{
    /** GET /admin : tableau de bord avec les statistiques clés de la plateforme. */
    public function dashboard(): void
    {
        Auth::requireAdmin();

        View::render('admin/dashboard', [
            'title'      => 'Administration',
            'userStats'  => (new User())->stats(),
            'itemStats'  => (new Item())->stats(),
            'claimStats' => (new Claim())->countByStatus(),
            'orderStats' => (new Order())->stats(),
        ]);
    }

    /** GET /admin/reports */
    public function reports(): void
    {
        Auth::requireAdmin();

        View::render('admin/reports', [
            'title'   => 'Modération',
            'reports' => (new Report())->openGroupedByItem(),
            'reasons' => Report::REASONS,
        ]);
    }

    /** POST /admin/reports/{id}/dismiss */
    public function dismiss(int $itemId): void
    {
        Auth::requireAdmin();
        Csrf::verify();

        (new Report())->closeForItem($itemId);
        Flash::add('success', 'Signalements classés : l’annonce reste en ligne.');
        redirect('/admin/reports');
    }

    /** GET /admin/items : liste et recherche de toutes les annonces. */
    public function items(): void
    {
        Auth::requireAdmin();

        $search = trim((string) ($_GET['q'] ?? ''));
        View::render('admin/items', [
            'title'  => 'Annonces',
            'items'  => (new Item())->searchAdmin($search),
            'search' => $search,
        ]);
    }

    /** POST /admin/items/{id}/delete */
    public function deleteItem(int $itemId): void
    {
        Auth::requireAdmin();
        Csrf::verify();
        $back = $this->safeBack();

        $items = new Item();
        $item = $items->findById($itemId);
        if ($item === null) {
            Flash::add('info', 'Cette annonce n’existe plus.');
            redirect($back);
        }

        // Fichiers relevés avant la suppression (les lignes partent en cascade)
        $proofPhotos = (new Claim())->proofPhotosForItem($itemId);
        $items->deleteById($itemId);

        PhotoStorage::publicPhotos()->delete($item['photo_path']);
        foreach ($proofPhotos as $proofPhoto) {
            PhotoStorage::privateProofs()->delete($proofPhoto);
        }

        Flash::add('success', 'Annonce « ' . $item['title'] . ' » supprimée.');
        redirect($back);
    }

    /** GET /admin/users : liste et recherche des comptes. */
    public function users(): void
    {
        Auth::requireAdmin();

        $search = trim((string) ($_GET['q'] ?? ''));
        View::render('admin/users', [
            'title'  => 'Utilisateurs',
            'users'  => (new User())->searchAdmin($search),
            'search' => $search,
        ]);
    }

    /** POST /admin/users/{id}/toggle-admin : donne ou retire le rôle administrateur. */
    public function toggleAdmin(int $userId): void
    {
        Auth::requireAdmin();
        Csrf::verify();

        if ($userId === (int) Auth::id()) {
            Flash::add('error', 'Vous ne pouvez pas changer votre propre rôle.');
            redirect('/admin/users');
        }

        $users = new User();
        $user = $users->findById($userId);
        if ($user === null) {
            Flash::add('info', 'Ce compte n’existe plus.');
            redirect('/admin/users');
        }

        $nowAdmin = $user['role'] !== 'admin';
        $users->setAdmin($userId, $nowAdmin);
        Flash::add('success', $nowAdmin
            ? $user['name'] . ' est maintenant administrateur.'
            : $user['name'] . ' n’est plus administrateur.');
        redirect('/admin/users');
    }

    /** POST /admin/users/{id}/delete */
    public function deleteUser(int $userId): void
    {
        Auth::requireAdmin();
        Csrf::verify();
        $back = $this->safeBack();

        if ($userId === (int) Auth::id()) {
            Flash::add('error', 'Vous ne pouvez pas supprimer votre propre compte depuis la modération.');
            redirect($back);
        }

        $user = (new User())->findById($userId);
        if ($user === null) {
            Flash::add('info', 'Ce compte n’existe plus.');
            redirect($back);
        }
        if ($user['role'] === 'admin') {
            Flash::add('error', 'Un compte administrateur ne peut pas être supprimé ici.');
            redirect($back);
        }

        // Pas de blocage « livraison en cours » ici : un compte frauduleux
        // doit pouvoir être supprimé immédiatement. Les remboursements
        // éventuels se gèrent depuis le tableau de bord Stripe.
        (new AccountService())->delete($userId);

        Flash::add('success', 'Le compte de ' . $user['name'] . ' et toutes ses annonces ont été supprimés.');
        redirect($back);
    }

    /**
     * Les actions de suppression sont utilisées depuis deux pages (signalements
     * et listes). On revient sur la page d'origine via une liste blanche : jamais
     * une redirection construite à partir d'une valeur arbitraire envoyée par le formulaire.
     */
    private function safeBack(): string
    {
        $back = (string) ($_POST['back'] ?? '');

        return in_array($back, ['/admin/reports', '/admin/users', '/admin/items'], true) ? $back : '/admin/reports';
    }
}
