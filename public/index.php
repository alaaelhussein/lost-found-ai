<?php
/**
 * Point d'entrée unique de l'application (« front controller »).
 *
 * TOUTES les requêtes passent par ce fichier :
 *   - avec Apache (XAMPP, Hostinger), grâce à public/.htaccess ;
 *   - avec le serveur intégré de PHP, lancé ainsi :
 *         php -S localhost:8000 -t public public/index.php
 *
 * Son rôle : démarrer l'application, déclarer les routes, puis laisser le
 * routeur appeler le bon contrôleur.
 *
 * Pour ajouter une page : déclarer la route ici, puis créer la méthode du
 * contrôleur et la vue correspondante.
 */

declare(strict_types=1);

// Serveur intégré de PHP uniquement : s'il s'agit d'un vrai fichier
// (CSS, JS, image), on le laisse servir tel quel (return false).
// Les fichiers .php sont exclus : seul index.php doit être exécuté.
if (PHP_SAPI === 'cli-server') {
    $requested = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($requested) && pathinfo($requested, PATHINFO_EXTENSION) !== 'php') {
        return false;
    }
}

require __DIR__ . '/../src/bootstrap.php';

use App\Controllers\AccountController;
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\ClaimController;
use App\Controllers\HomeController;
use App\Controllers\ItemController;
use App\Controllers\ConversationController;
use App\Controllers\OrderController;
use App\Controllers\PageController;
use App\Controllers\PasswordController;
use App\Controllers\ReportController;
use App\Controllers\VerificationController;
use App\Controllers\WebhookController;
use App\Core\Router;

$router = new Router();

// --- Pages publiques ---
$router->get('/', [HomeController::class, 'index']);
$router->get('/items', [ItemController::class, 'index']);
$router->get('/items/{id}', [ItemController::class, 'show']);
$router->post('/items/{id}/contact', [ItemController::class, 'contact']);
$router->get('/my-items', [ItemController::class, 'mine']);
$router->get('/conversations', [ConversationController::class, 'index']);
$router->get('/conversations/{id}', [ConversationController::class, 'show']);
$router->post('/conversations/{id}/messages', [ConversationController::class, 'send']);

// --- Authentification ---
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/forgot-password', [PasswordController::class, 'showForgot']);
$router->post('/forgot-password', [PasswordController::class, 'sendLink']);
$router->get('/reset-password', [PasswordController::class, 'showReset']);
$router->post('/reset-password', [PasswordController::class, 'reset']);

// --- Mon compte (RGPD : export et suppression) ---
$router->get('/account', [AccountController::class, 'show']);
$router->post('/account/password', [AccountController::class, 'changePassword']);
$router->post('/account/notifications', [AccountController::class, 'notifications']);
$router->get('/account/export', [AccountController::class, 'export']);
$router->post('/account/delete', [AccountController::class, 'delete']);

// --- Pages légales ---
$router->get('/mentions-legales', [PageController::class, 'legal']);
$router->get('/confidentialite', [PageController::class, 'privacy']);
$router->get('/cgu', [PageController::class, 'terms']);

// --- Signalements et modération ---
$router->post('/items/{id}/report', [ReportController::class, 'store']);
$router->get('/admin', [AdminController::class, 'dashboard']);
$router->get('/admin/reports', [AdminController::class, 'reports']);
$router->post('/admin/reports/{id}/dismiss', [AdminController::class, 'dismiss']);
$router->get('/admin/items', [AdminController::class, 'items']);
$router->post('/admin/items/{id}/delete', [AdminController::class, 'deleteItem']);
$router->get('/admin/users', [AdminController::class, 'users']);
$router->post('/admin/users/{id}/toggle-admin', [AdminController::class, 'toggleAdmin']);
$router->post('/admin/users/{id}/delete', [AdminController::class, 'deleteUser']);

// --- Annonces ---
$router->get('/items/create', [ItemController::class, 'showCreate']);
$router->post('/items/create', [ItemController::class, 'create']);
$router->post('/items/{id}/status', [ItemController::class, 'updateStatus']);
$router->post('/items/{id}/delete', [ItemController::class, 'delete']);

// --- Vérification de l'email et alertes ---
$router->get('/verify-email', [VerificationController::class, 'show']);
$router->post('/verify-email', [VerificationController::class, 'verify']);
$router->post('/verify-email/resend', [VerificationController::class, 'resend']);
$router->get('/notifications/unsubscribe', [VerificationController::class, 'unsubscribeForm']);
$router->post('/notifications/unsubscribe', [VerificationController::class, 'unsubscribe']);

// --- Demandes de restitution (preuve de propriété) ---
$router->get('/items/{id}/claim', [ClaimController::class, 'create']);
$router->post('/items/{id}/claim', [ClaimController::class, 'store']);
$router->post('/claims/{id}/approve', [ClaimController::class, 'approve']);
$router->post('/claims/{id}/reject', [ClaimController::class, 'reject']);
$router->get('/claims/{id}/photo', [ClaimController::class, 'photo']);

// --- Livraison et paiement ---
$router->get('/claims/{id}/order', [OrderController::class, 'create']);
$router->post('/claims/{id}/order', [OrderController::class, 'store']);
$router->get('/orders/{id}', [OrderController::class, 'show']);
$router->get('/orders/{id}/pay', [OrderController::class, 'payForm']);
$router->post('/orders/{id}/pay', [OrderController::class, 'pay']);
$router->get('/orders/{id}/label', [OrderController::class, 'label']);
$router->post('/orders/{id}/shipped', [OrderController::class, 'shipped']);
$router->post('/orders/{id}/delivered', [OrderController::class, 'delivered']);
$router->post('/webhooks/stripe', [WebhookController::class, 'stripe']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
