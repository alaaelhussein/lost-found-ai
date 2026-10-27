<?php
/**
 * Auth : gestion de l'utilisateur connecté.
 *
 * Seul l'identifiant de l'utilisateur est stocké en session
 * ($_SESSION['user_id']). Les autres informations (nom, rôle...) sont relues en
 * base à chaque requête : si le compte est supprimé, la session cesse
 * immédiatement d'être valide.
 *
 * Utilisation :
 *     Auth::requireLogin();   // en tête d'une action réservée aux membres
 *     Auth::user()['name'];   // nom de l'utilisateur connecté
 *     Auth::id();             // son id, ou null
 */

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

final class Auth
{
    private const SESSION_USER     = 'user_id';
    private const SESSION_INTENDED = '_intended_url';
    private const SESSION_ACTIVITY = '_last_activity';
    private const SESSION_VERSION  = '_session_version';

    /** Déconnexion automatique après 2 h sans aucune requête. */
    public const IDLE_TIMEOUT = 2 * 60 * 60;

    /** Utilisateur chargé pour la requête en cours (cache). */
    private static ?array $user = null;
    private static bool $loaded = false;

    /**
     * Connecte l'utilisateur après vérification de son mot de passe.
     */
    public static function login(array $user): void
    {
        // On change l'identifiant de session à la connexion. Sans ça, un
        // attaquant qui aurait fixé l'id de session de la victime avant sa
        // connexion pourrait ensuite l'utiliser (attaque « session fixation »).
        session_regenerate_id(true);

        $_SESSION[self::SESSION_USER] = (int) $user['id'];
        $_SESSION[self::SESSION_ACTIVITY] = time();

        // Nouveau jeton CSRF pour la session authentifiée : un jeton obtenu
        // avant la connexion (par exemple sur un poste partagé) ne sert plus.
        Csrf::rotate();

        self::$user   = null;
        self::$loaded = false;
        self::rememberSessionVersion();
    }

    /**
     * Mémorise en session la « version » actuelle du mot de passe du compte
     * (users.session_version). Appelée à la connexion, et après un changement
     * de mot de passe pour que la session EN COURS reste ouverte alors que
     * toutes les autres (autres appareils, voleur éventuel) sont fermées.
     */
    public static function rememberSessionVersion(): void
    {
        $id = $_SESSION[self::SESSION_USER] ?? null;
        $fresh = $id === null ? null : (new User())->findById((int) $id);
        $_SESSION[self::SESSION_VERSION] = (int) ($fresh['session_version'] ?? 0);

        self::$user   = null;
        self::$loaded = false;
    }

    /**
     * Déconnecte l'utilisateur : la session est entièrement détruite puis une
     * nouvelle, vide, est ouverte (pour pouvoir afficher un message flash).
     */
    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();

        session_start();
        session_regenerate_id(true);

        self::$user   = null;
        self::$loaded = true;
    }

    /**
     * Déconnecte automatiquement une session restée inactive trop longtemps
     * (ordinateur de bibliothèque oublié allumé...). Appelée à chaque requête
     * par bootstrap.php, juste après session_start().
     */
    public static function enforceIdleTimeout(): void
    {
        if (!isset($_SESSION[self::SESSION_USER])) {
            return;
        }

        $last = (int) ($_SESSION[self::SESSION_ACTIVITY] ?? 0);
        if ($last > 0 && time() - $last > self::IDLE_TIMEOUT) {
            self::logout();
            Flash::add('info', 'Votre session a expiré après une longue inactivité. Reconnectez-vous.');
            return;
        }

        $_SESSION[self::SESSION_ACTIVITY] = time();
    }

    /**
     * Retourne l'utilisateur connecté (sans le hash du mot de passe), ou null.
     */
    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION[self::SESSION_USER] ?? null;

            if ($id !== null) {
                self::$user = (new User())->findById((int) $id);

                // Mot de passe changé depuis l'ouverture de cette session (sur
                // un autre appareil, ou après « mot de passe oublié ») : la
                // session n'est plus valable, l'utilisateur doit se reconnecter.
                if (self::$user !== null
                    && (int) self::$user['session_version'] !== (int) ($_SESSION[self::SESSION_VERSION] ?? 0)) {
                    self::$user = null;
                }

                // Compte supprimé ou session périmée : on nettoie la session
                if (self::$user === null) {
                    unset($_SESSION[self::SESSION_USER], $_SESSION[self::SESSION_VERSION]);
                }
            }
        }

        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    /**
     * Bloque l'accès aux visiteurs non connectés : ils sont envoyés vers
     * /login, puis ramenés sur la page demandée après connexion.
     */
    public static function requireLogin(): void
    {
        if (self::check()) {
            return;
        }

        // On ne mémorise que les pages consultées en GET : revenir sur une
        // URL de traitement de formulaire (POST) n'aurait pas de sens.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $_SESSION[self::SESSION_INTENDED] = $_SERVER['REQUEST_URI'] ?? '/';
        }

        Flash::add('info', 'Connectez-vous pour accéder à cette page.');
        redirect('/login');
    }

    /** Vrai si l'utilisateur connecté a confirmé son email par code. */
    public static function isVerified(): bool
    {
        $user = self::user();

        return $user !== null && $user['email_verified_at'] !== null;
    }

    /**
     * Pour les actions qui contactent d'autres personnes (publier, écrire,
     * réclamer un objet, commander) : l'email doit être vérifié. Sinon, des
     * robots pourraient créer des comptes avec des adresses inventées.
     */
    public static function requireVerified(): void
    {
        self::requireLogin();
        if (self::isVerified()) {
            return;
        }

        Flash::add('info', 'Confirmez d’abord votre adresse email avec le code reçu pour utiliser cette fonctionnalité.');
        redirect('/verify-email');
    }

    /** Vrai si l'utilisateur connecté est administrateur (users.role = 'admin'). */
    public static function isAdmin(): bool
    {
        $user = self::user();

        return $user !== null && $user['role'] === 'admin';
    }

    /**
     * Pages de modération (/admin/...) : réservées aux administrateurs. Un
     * visiteur reçoit une 404 plutôt qu'une 403, pour ne pas révéler que la
     * page existe.
     */
    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            View::error(404, 'La page demandée est introuvable.');
            exit;
        }
    }

    /**
     * Pour les pages login / register : un utilisateur déjà connecté n'a rien
     * à y faire, on le renvoie à l'accueil.
     */
    public static function requireGuest(): void
    {
        if (self::check()) {
            redirect('/');
        }
    }

    /**
     * Retourne (et oublie) la page à laquelle l'utilisateur voulait accéder
     * avant d'être envoyé vers /login.
     */
    public static function pullIntendedUrl(string $default = '/'): string
    {
        $url = $_SESSION[self::SESSION_INTENDED] ?? $default;
        unset($_SESSION[self::SESSION_INTENDED]);

        // Sécurité (« open redirect ») : on n'accepte qu'un chemin interne.
        //   - '//site-pirate.com' commence par '/' mais pointe vers un autre domaine ;
        //   - '/\site-pirate.com' aussi : les navigateurs lisent '\' comme '/' ;
        //   - les caractères de contrôle (retour à la ligne, tabulation...) sont
        //     ignorés par les navigateurs et peuvent masquer les cas ci-dessus.
        if (
            !is_string($url)
            || !str_starts_with($url, '/')
            || str_starts_with($url, '//')
            || str_contains($url, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $url)
        ) {
            return $default;
        }

        return $url;
    }
}
