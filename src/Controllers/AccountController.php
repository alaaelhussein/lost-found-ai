<?php
/**
 * AccountController : espace « Mon compte ».
 *
 * Routes :
 *   GET  /account                 page du compte (informations + formulaires)
 *   POST /account/password        changer de mot de passe
 *   POST /account/notifications   activer / couper les emails de correspondance
 *   GET  /account/export          télécharger toutes ses données (JSON, RGPD art. 15 et 20)
 *   POST /account/delete          supprimer définitivement son compte (RGPD art. 17)
 *
 * Les actions sensibles (mot de passe, suppression) redemandent le mot de
 * passe actuel : quelqu'un qui trouverait une session ouverte sur un
 * ordinateur partagé ne pourrait ni verrouiller le compte, ni l'effacer.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Throttle;
use App\Core\View;
use App\Models\User;
use App\Services\AccountService;
use App\Services\Notifier;
use App\Services\PasswordPolicy;

final class AccountController
{
    /** GET /account */
    public function show(): void
    {
        Auth::requireLogin();

        $this->render([], []);
    }

    /** POST /account/password */
    public function changePassword(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        // Limite les essais du mot de passe actuel (sinon : brute force depuis une session volée)
        Throttle::check('account_password', 'user:' . Auth::id(), 10, 3600);

        $userId = (int) Auth::id();
        $users = new User();

        $errors = [];
        if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $users->passwordHash($userId))) {
            $errors['current_password'] = 'Mot de passe actuel incorrect.';
        } else {
            $errors = PasswordPolicy::validate(
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['password_confirm'] ?? '')
            );
        }

        if ($errors !== []) {
            http_response_code(422);
            $this->render($errors, []);
            return;
        }

        // session_version + 1 : les AUTRES sessions sont fermées...
        $users->changePassword($userId, password_hash((string) $_POST['password'], PASSWORD_DEFAULT));
        // ...mais celle-ci reste ouverte (l'utilisateur vient de prouver qu'il connaît le mot de passe)
        Auth::rememberSessionVersion();

        (new Notifier())->passwordChanged(Auth::user());

        Flash::add('success', 'Mot de passe modifié. Vos autres appareils ont été déconnectés.');
        redirect('/account');
    }

    /** POST /account/notifications */
    public function notifications(): void
    {
        Auth::requireLogin();
        Csrf::verify();

        $enabled = (string) ($_POST['notify_matches'] ?? '') === '1';
        (new User())->setNotifyMatches((int) Auth::id(), $enabled);

        Flash::add('success', $enabled
            ? 'Vous serez prévenu par email des nouvelles correspondances.'
            : 'Vous ne recevrez plus d’emails pour les nouvelles correspondances.');
        redirect('/account');
    }

    /**
     * GET /account/export : fichier JSON avec toutes les données du compte.
     * En GET car c'est une simple lecture (aucune modification) ; la page est
     * réservée à l'utilisateur connecté et ne concerne que ses propres données.
     */
    public function export(): void
    {
        Auth::requireLogin();
        Throttle::check('account_export', 'user:' . Auth::id(), 10, 3600);

        $data = (new User())->exportData((int) Auth::id());
        $data['_export'] = [
            'genere_le' => date('c'),
            'note'      => 'Données personnelles associées à votre compte Lost&Found AI (RGPD, articles 15 et 20).',
        ];

        // On remplace l'en-tête HTML envoyé par bootstrap.php : le navigateur
        // propose d'enregistrer le fichier au lieu de l'afficher.
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="mes-donnees-lost-found.json"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** POST /account/delete */
    public function delete(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Throttle::check('account_delete', 'user:' . Auth::id(), 10, 3600);

        $userId = (int) Auth::id();
        $errors = [];

        if (!password_verify((string) ($_POST['delete_password'] ?? ''), (string) (new User())->passwordHash($userId))) {
            $errors['delete_password'] = 'Mot de passe incorrect.';
        }
        if ((string) ($_POST['confirm_delete'] ?? '') !== '1') {
            $errors['confirm_delete'] = 'Cochez la case pour confirmer la suppression.';
        }

        $accounts = new AccountService();
        $blocking = $accounts->blockingReason($userId);
        if ($blocking !== null) {
            $errors['delete_password'] = $blocking;
        }

        if ($errors !== []) {
            http_response_code(422);
            $this->render([], $errors);
            return;
        }

        $accounts->delete($userId);
        Auth::logout();

        Flash::add('success', 'Votre compte et toutes vos données ont été supprimés. Merci d’avoir utilisé Lost&Found AI.');
        redirect('/');
    }

    /**
     * @param array<string, string> $passwordErrors Erreurs du formulaire « mot de passe »
     * @param array<string, string> $deleteErrors   Erreurs du formulaire « suppression »
     */
    private function render(array $passwordErrors, array $deleteErrors): void
    {
        View::render('account/show', [
            'title'          => 'Mon compte',
            'user'           => Auth::user(),
            'passwordErrors' => $passwordErrors,
            'deleteErrors'   => $deleteErrors,
            'blocking'       => (new AccountService())->blockingReason((int) Auth::id()),
        ]);
    }
}
