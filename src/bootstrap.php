<?php
/**
 * Démarrage de l'application : exécuté au début de CHAQUE requête par
 * public/index.php, avant le routeur.
 *
 * Étapes :
 *   1. autoloader : charge automatiquement les classes App\... depuis src/
 *   2. configuration + connexion BDD + fonctions utilitaires
 *   3. gestion des erreurs (page 500 propre, détails seulement en mode debug)
 *   4. en-têtes de sécurité HTTP
 *   5. session sécurisée
 */

declare(strict_types=1);

use App\Core\Config;
use App\Core\View;

// ---------------------------------------------------------------------
// 1. Autoloader (PSR-4 simplifié, sans Composer)
//
// Le nom complet d'une classe donne son fichier :
//     App\Controllers\AuthController  =>  src/Controllers/AuthController.php
//     App\Core\Router                 =>  src/Core/Router.php
// Plus besoin de require pour chaque classe : PHP appelle cette fonction
// la première fois qu'une classe inconnue est utilisée.
// ---------------------------------------------------------------------
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return; // classe hors du projet : pas notre affaire
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// ---------------------------------------------------------------------
// 2. Configuration, base de données, fonctions globales
// ---------------------------------------------------------------------
require __DIR__ . '/helpers.php';
Config::load(__DIR__ . '/../config/config.php');
require __DIR__ . '/../config/database.php';

// Fuseau horaire utilisé par les fonctions date() de PHP
date_default_timezone_set(Config::get('app.timezone', 'Europe/Paris'));

// ---------------------------------------------------------------------
// 3. Gestion des erreurs
//
// - Toute erreur PHP (warning, notice) est convertie en exception : on ne
//   laisse passer aucune erreur silencieuse.
// - Toute exception non attrapée affiche une page 500.
//   En mode debug : le message et le fichier sont affichés pour le développeur.
//   En production (app.debug = false) : message neutre, aucun détail technique
//   n'est montré au visiteur (il pourrait révéler des informations sensibles).
// ---------------------------------------------------------------------
$debug = (bool) Config::get('app.debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    // Erreur volontairement masquée avec l'opérateur @ : on la laisse passer
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e) use ($debug): void {
    // Toujours écrire l'erreur dans le log du serveur
    error_log((string) $e);

    $message = $debug
        ? get_class($e) . ' : ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
        : 'Une erreur interne est survenue. Réessayez plus tard.';

    // Si une partie de la page a déjà été générée, on l'efface
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    try {
        View::error(500, $message);
    } catch (Throwable $viewError) {
        // La page d'erreur elle-même a échoué (ex. base inaccessible pendant
        // l'affichage du layout) : on se rabat sur du texte brut.
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Erreur 500 : ' . $message;
    }
});

// ---------------------------------------------------------------------
// 4. En-têtes de sécurité HTTP
// ---------------------------------------------------------------------
header('X-Content-Type-Options: nosniff');          // le navigateur respecte le type annoncé (pas de « devinette »)
header('X-Frame-Options: DENY');                    // interdit d'afficher le site dans une <iframe> (anti-clickjacking)
header('Referrer-Policy: same-origin');             // n'envoie pas l'URL de nos pages aux sites externes
header('Content-Type: text/html; charset=UTF-8');
header_remove('X-Powered-By');                      // ne révèle pas la version de PHP

// Content-Security-Policy : liste blanche de ce que le navigateur a le droit
// de charger. Même si une faille XSS laissait passer un <script> injecté, il
// ne serait PAS exécuté : seuls nos fichiers /js/*.js le sont (aucun script
// inline, aucun attribut onclick/onsubmit dans les vues).
//   img-src blob: : aperçu de la photo choisie avant l'envoi (app.js)
//   form-action   : les formulaires ne peuvent envoyer que vers notre site, et
//                   vers Stripe (après le formulaire de livraison, le navigateur
//                   est redirigé vers la page de paiement : Chrome applique
//                   form-action à cette redirection)
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; "
    . "img-src 'self' blob:; font-src 'self'; connect-src 'self'; object-src 'none'; "
    . "base-uri 'self'; form-action 'self' https://checkout.stripe.com; frame-ancestors 'none'");

// Fonctions du navigateur dont le site n'a pas besoin : désactivées.
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
header('Cross-Origin-Opener-Policy: same-origin');

// Les pages sont personnalisées (messages privés, nom de l'utilisateur) : ni le
// navigateur ni un proxy ne doivent les garder en cache. Sans ça, après une
// déconnexion, le bouton « Précédent » réafficherait une conversation privée.
header('Cache-Control: no-store, private');

// ---------------------------------------------------------------------
// 5. Session sécurisée
// ---------------------------------------------------------------------
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

if ($isHttps) {
    // HSTS : pendant 1 an, le navigateur refusera de revenir sur ce site en
    // HTTP simple (protège contre l'interception sur un wifi public).
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

ini_set('session.use_strict_mode', '1');   // refuse un id de session inventé par le client
ini_set('session.use_only_cookies', '1');  // l'id de session ne passe jamais dans l'URL

session_name('lfai_session');
session_set_cookie_params([
    'lifetime' => 0,          // cookie supprimé à la fermeture du navigateur
    'path'     => '/',
    'secure'   => $isHttps,   // en HTTPS (production), cookie envoyé uniquement en HTTPS
    'httponly' => true,       // cookie inaccessible au JavaScript (limite le vol de session par XSS)
    'samesite' => 'Lax',      // cookie non envoyé lors de POST venant d'autres sites (défense CSRF supplémentaire)
]);
session_start();

// Déconnexion automatique après une longue inactivité (voir Auth::IDLE_TIMEOUT)
App\Core\Auth::enforceIdleTimeout();

// ---------------------------------------------------------------------
// 6. Encodage des données reçues
//
// La base est en utf8mb4 : un texte qui n'est pas de l'UTF-8 valide (envoyé
// par un robot ou un client mal configuré) ferait échouer l'INSERT avec une
// erreur 500. On le refuse proprement dès l'entrée (400 = requête invalide).
// ---------------------------------------------------------------------
$validUtf8 = true;
array_walk_recursive($_GET, function ($value) use (&$validUtf8): void {
    $validUtf8 = $validUtf8 && mb_check_encoding((string) $value, 'UTF-8');
});
array_walk_recursive($_POST, function ($value) use (&$validUtf8): void {
    $validUtf8 = $validUtf8 && mb_check_encoding((string) $value, 'UTF-8');
});
if (!$validUtf8) {
    View::error(400, 'Les données envoyées contiennent des caractères invalides.');
    exit;
}
