<?php
/**
 * PasswordController : « mot de passe oublié ».
 *
 * Routes :
 *   GET  /forgot-password           formulaire : saisir son email
 *   POST /forgot-password           envoi du lien de réinitialisation
 *   GET  /reset-password?token=...  formulaire : choisir un nouveau mot de passe
 *   POST /reset-password            enregistrement du nouveau mot de passe
 *
 * Parcours :
 *   1. l'utilisateur donne son email ; s'il a un compte, il reçoit un lien
 *      contenant un jeton aléatoire de 64 caractères, valable 1 heure ;
 *   2. en ouvrant le lien, il choisit un nouveau mot de passe ;
 *   3. le jeton est supprimé (usage unique), toutes ses autres sessions sont
 *      fermées (users.session_version) et il doit se reconnecter.
 *
 * Sécurité :
 *   - message IDENTIQUE que l'email ait un compte ou non : impossible de
 *     tester quels emails sont inscrits ;
 *   - jeton tiré avec random_bytes (256 bits) : impossible à deviner ;
 *   - stocké sous forme de HMAC (Crypto::sign) : la base seule ne suffit pas ;
 *   - limites : 5 demandes par heure et par IP, un lien par minute et par compte
 *     (empêche d'inonder la boîte mail de quelqu'un).
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Crypto;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Throttle;
use App\Core\View;
use App\Models\LoginAttempt;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\Notifier;
use App\Services\PasswordPolicy;

final class PasswordController
{
    public const VALID_MINUTES = 60;
    private const RESEND_DELAY = 60; // secondes entre deux liens pour un même compte

    /** GET /forgot-password */
    public function showForgot(): void
    {
        Auth::requireGuest();

        View::render('auth/forgot', ['title' => 'Mot de passe oublié', 'errors' => [], 'old' => []]);
    }

    /** POST /forgot-password */
    public function sendLink(): void
    {
        Auth::requireGuest();
        Csrf::verify();
        Throttle::check('password_forgot', 'ip:' . client_ip(), 5, 3600);

        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            http_response_code(422);
            View::render('auth/forgot', [
                'title'  => 'Mot de passe oublié',
                'errors' => ['email' => 'Adresse email invalide.'],
                'old'    => ['email' => $email],
            ]);
            return;
        }

        $user = (new User())->findByEmail($email);
        $resets = new PasswordReset();

        // On n'envoie le lien que si le compte existe et qu'aucun lien n'est
        // parti dans la dernière minute. Dans TOUS les cas, la réponse est la même.
        if ($user !== null) {
            $since = $resets->secondsSinceLast((int) $user['id']);
            if ($since === null || $since >= self::RESEND_DELAY) {
                // 32 octets aléatoires => 64 caractères hexadécimaux dans l'URL
                $token = bin2hex(random_bytes(32));
                $resets->store((int) $user['id'], self::hash($token), self::VALID_MINUTES);
                (new Notifier())->passwordReset($user, $token, self::VALID_MINUTES);
            }
        }

        Flash::add('success', 'Si un compte existe avec ' . $email . ', un lien pour choisir un nouveau mot de passe vient de lui être envoyé (valable 1 heure). Pensez à regarder dans les spams.');
        redirect('/login');
    }

    /** GET /reset-password?token=... */
    public function showReset(): void
    {
        $token = (string) ($_GET['token'] ?? '');

        if ($this->userIdForToken($token) === null) {
            $this->renderInvalidLink();
            return;
        }

        $this->renderResetForm($token, []);
    }

    /** POST /reset-password */
    public function reset(): void
    {
        Csrf::verify();
        Throttle::check('password_reset', 'ip:' . client_ip(), 10, 3600);

        $token = (string) ($_POST['token'] ?? '');
        $userId = $this->userIdForToken($token);
        if ($userId === null) {
            $this->renderInvalidLink();
            return;
        }

        $password = (string) ($_POST['password'] ?? '');
        $errors = PasswordPolicy::validate($password, (string) ($_POST['password_confirm'] ?? ''));
        if ($errors !== []) {
            http_response_code(422);
            $this->renderResetForm($token, $errors);
            return;
        }

        $users = new User();
        // changePassword incrémente session_version : toutes les sessions
        // ouvertes sur ce compte (y compris celle d'un éventuel voleur) sont fermées.
        $users->changePassword($userId, password_hash($password, PASSWORD_DEFAULT));
        (new PasswordReset())->delete($userId); // le lien ne peut servir qu'une fois

        // Ouvrir le lien reçu par email prouve qu'on possède l'adresse : on en
        // profite pour la marquer vérifiée, et on efface les échecs de connexion.
        $user = $users->findById($userId);
        if ($user !== null) {
            if ($user['email_verified_at'] === null) {
                $users->markEmailVerified($userId);
            }
            (new LoginAttempt())->clear($user['email']);
        }

        // Si quelqu'un était connecté dans ce navigateur, on le déconnecte proprement
        if (Auth::check()) {
            Auth::logout();
        }

        Flash::add('success', 'Votre mot de passe a été modifié. Connectez-vous avec le nouveau mot de passe.');
        redirect('/login');
    }

    /** Compte lié au jeton s'il est valide, sinon null. */
    private function userIdForToken(string $token): ?int
    {
        // Format attendu : exactement 64 caractères hexadécimaux. Inutile
        // d'interroger la base pour n'importe quelle chaîne envoyée par un robot.
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }

        return (new PasswordReset())->findValidUserId(self::hash($token));
    }

    /** HMAC du jeton : c'est lui qui est stocké en base, jamais le jeton. */
    private static function hash(string $token): string
    {
        return Crypto::sign('password-reset:' . $token);
    }

    private function renderResetForm(string $token, array $errors): void
    {
        View::render('auth/reset', [
            'title'  => 'Nouveau mot de passe',
            'token'  => $token,
            'errors' => $errors,
            'valid'  => true,
        ]);
    }

    private function renderInvalidLink(): void
    {
        http_response_code(410); // 410 = la ressource n'existe plus
        View::render('auth/reset', [
            'title'  => 'Lien expiré',
            'token'  => '',
            'errors' => [],
            'valid'  => false,
        ]);
    }
}
