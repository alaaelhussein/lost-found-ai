<?php
/**
 * AuthController : inscription, connexion et déconnexion.
 *
 * Routes (déclarées dans public/index.php) :
 *   GET  /register  => showRegister()  formulaire d'inscription
 *   POST /register  => register()      traitement de l'inscription
 *   GET  /login     => showLogin()     formulaire de connexion
 *   POST /login     => login()         traitement de la connexion
 *   POST /logout    => logout()        déconnexion (POST + CSRF, jamais GET :
 *                                      un simple lien ou une image piégée
 *                                      pourrait sinon déconnecter l'utilisateur)
 *
 * Gestion des erreurs de formulaire : en cas d'erreur, on n'effectue PAS de
 * redirection. On réaffiche directement le formulaire avec :
 *   - $errors : ['champ' => 'message'] affiché sous chaque champ ;
 *   - $old    : les valeurs saisies, pour ne pas obliger à tout retaper.
 * Le mot de passe n'est jamais renvoyé dans $old.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Throttle;
use App\Core\View;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\EmailVerifier;
use App\Services\PasswordPolicy;
use PDOException;

final class AuthController
{
    /**
     * Hash bcrypt d'un mot de passe fictif. Quand l'email n'existe pas, on
     * vérifie quand même le mot de passe contre ce hash : la réponse prend
     * alors le même temps que pour un compte existant, et un attaquant ne
     * peut pas deviner quels emails sont inscrits en mesurant la durée.
     */
    private const DUMMY_HASH = '$2y$10$u1vflQ4AeSjNVUGydCSHhOe4x.Vqd.teA1y.2FAe6MtYIZJpBDYGO';

    private const NAME_MIN = 2;
    private const NAME_MAX = 100;
    private const EMAIL_MAX = 190;   // taille de la colonne users.email

    // ------------------------------------------------------------------
    // Inscription
    // ------------------------------------------------------------------

    /** GET /register */
    public function showRegister(): void
    {
        Auth::requireGuest();

        View::render('auth/register', [
            'title'  => 'Créer un compte',
            'errors' => [],
            'old'    => [],
        ]);
    }

    /** POST /register */
    public function register(): void
    {
        Auth::requireGuest();
        Csrf::verify();
        // Anti-création de comptes en masse : 5 inscriptions par heure et par IP
        Throttle::check('register', 'ip:' . client_ip(), 5, 3600);

        // (string) : si quelqu'un envoie un tableau (name[]=x) au lieu d'un
        // texte, on obtient une chaîne au lieu d'une erreur PHP.
        $name     = trim((string) ($_POST['name'] ?? ''));
        $email    = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');
        $terms    = (string) ($_POST['terms'] ?? '') === '1';

        $users  = new User();
        $errors = $this->validateRegistration($name, $email, $password, $confirm);

        // Acceptation des CGU obligatoire : la date est enregistrée avec le
        // compte (preuve en cas de litige sur les règles d'utilisation).
        if (!$terms) {
            $errors['terms'] = 'Vous devez accepter les conditions d’utilisation pour créer un compte.';
        }

        // Vérification d'unicité seulement si l'email est valide (inutile sinon)
        if (!isset($errors['email']) && $users->emailExists($email)) {
            $errors['email'] = 'Un compte existe déjà avec cet email.';
        }

        if ($errors !== []) {
            http_response_code(422); // 422 = données envoyées invalides
            View::render('auth/register', [
                'title'  => 'Créer un compte',
                'errors' => $errors,
                'old'    => ['name' => $name, 'email' => $email, 'terms' => $terms ? '1' : ''],
            ]);
            return;
        }

        // password_hash choisit l'algorithme recommandé par PHP (bcrypt
        // aujourd'hui) et génère un « sel » aléatoire : deux utilisateurs avec
        // le même mot de passe auront des hash différents.
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $userId = $users->create($name, $email, $hash);
        } catch (PDOException $e) {
            // Cas rare : deux inscriptions simultanées avec le même email.
            // La contrainte UNIQUE de la base bloque la seconde (code 23000).
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            http_response_code(422);
            View::render('auth/register', [
                'title'  => 'Créer un compte',
                'errors' => ['email' => 'Un compte existe déjà avec cet email.'],
                'old'    => ['name' => $name, 'email' => $email],
            ]);
            return;
        }

        // Connexion automatique juste après l'inscription, puis envoi du code
        // de vérification : le compte peut naviguer, mais pas publier ni
        // contacter quelqu'un tant que l'email n'est pas confirmé.
        Auth::login(['id' => $userId]);
        $sent = (new EmailVerifier())->sendCode(Auth::user());

        Flash::add('success', 'Bienvenue ' . $name . ' ! ' . ($sent
            ? 'Un code de vérification vient d’être envoyé à ' . $email . '.'
            : 'L’email de vérification n’a pas pu partir : demandez un nouveau code ci-dessous.'));
        redirect('/verify-email');
    }

    /**
     * Règles de validation de l'inscription.
     *
     * @return array<string, string> Messages d'erreur indexés par nom de champ (vide = tout est valide)
     */
    private function validateRegistration(string $name, string $email, string $password, string $confirm): array
    {
        $errors = [];

        $nameLength = mb_strlen($name);
        if ($nameLength < self::NAME_MIN || $nameLength > self::NAME_MAX) {
            $errors['name'] = 'Le nom doit contenir entre ' . self::NAME_MIN . ' et ' . self::NAME_MAX . ' caractères.';
        }

        if ($email === '' || strlen($email) > self::EMAIL_MAX || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Adresse email invalide.';
        }

        // Mêmes règles que la réinitialisation et le changement de mot de passe
        return $errors + PasswordPolicy::validate($password, $confirm);
    }

    // ------------------------------------------------------------------
    // Connexion
    // ------------------------------------------------------------------

    /** GET /login */
    public function showLogin(): void
    {
        Auth::requireGuest();

        View::render('auth/login', [
            'title'  => 'Connexion',
            'errors' => [],
            'old'    => [],
        ]);
    }

    /** POST /login */
    public function login(): void
    {
        Auth::requireGuest();
        Csrf::verify();

        $email    = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $ip       = client_ip();

        // Un email plus long que la colonne ne peut correspondre à aucun compte.
        // On le tronque pour qu'il tienne dans login_attempts.email : sinon
        // l'INSERT échouerait (erreur 500) et l'échec ne serait pas compté.
        if (strlen($email) > self::EMAIL_MAX) {
            $email = mb_strcut($email, 0, self::EMAIL_MAX);
        }

        $attempts = new LoginAttempt();

        // 1. Trop d'échecs récents : on refuse sans même vérifier le mot de passe
        if ($attempts->isBlocked($email, $ip)) {
            $this->renderLoginError(
                $email,
                'Trop de tentatives de connexion. Réessayez dans ' . LoginAttempt::WINDOW_MINUTES . ' minutes.',
                429 // 429 = trop de requêtes
            );
            return;
        }

        // 2. Vérification email + mot de passe
        $user = $email !== '' ? (new User())->findByEmail($email) : null;

        // On appelle password_verify dans TOUS les cas (voir DUMMY_HASH)
        $hash = $user['password_hash'] ?? self::DUMMY_HASH;
        $passwordOk = password_verify($password, $hash);

        if ($user === null || !$passwordOk) {
            $attempts->record($email, $ip);
            // Message volontairement identique que l'email existe ou non :
            // on ne révèle pas quels emails ont un compte.
            $this->renderLoginError($email, 'Email ou mot de passe incorrect.', 401);
            return;
        }

        // 3. Succès
        $attempts->clear($email);

        // Si PHP recommande désormais un algorithme ou un coût plus fort, on
        // en profite pour mettre à jour le hash : c'est le seul moment où l'on
        // connaît le mot de passe en clair.
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            (new User())->updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        Auth::login($user);
        Flash::add('success', 'Bonjour ' . $user['name'] . ', vous êtes connecté.');

        // Retour sur la page demandée avant la connexion, sinon l'accueil
        redirect(Auth::pullIntendedUrl('/'));
    }

    private function renderLoginError(string $email, string $message, int $httpCode): void
    {
        http_response_code($httpCode);
        View::render('auth/login', [
            'title'  => 'Connexion',
            // Clé 'form' : erreur globale affichée en haut du formulaire
            'errors' => ['form' => $message],
            'old'    => ['email' => $email],
        ]);
    }

    // ------------------------------------------------------------------
    // Déconnexion
    // ------------------------------------------------------------------

    /** POST /logout */
    public function logout(): void
    {
        Csrf::verify();

        Auth::logout();
        Flash::add('info', 'Vous êtes déconnecté.');
        redirect('/');
    }
}
