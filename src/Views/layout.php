<?php
/**
 * Layout commun à toutes les pages.
 *
 * Variables disponibles (fournies par View::render) :
 *   $title   : titre de la page (balise <title>)
 *   $content : HTML de la vue, déjà généré
 *
 * Contient : <head>, la barre de navigation (différente selon que l'utilisateur
 * est connecté ou non), les messages flash, puis le contenu de la page.
 *
 * Aucun <script> ni <style> inline : la Content-Security-Policy (bootstrap.php)
 * n'autorise que les fichiers /css et /js du site.
 */

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Flash;

$currentUser = Auth::user();
$appName = Config::get('app.name', 'Lost&Found AI');
$currentPath = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';

/** aria-current="page" sur le lien de la page en cours (style + lecteurs d'écran). */
$navCurrent = static function (string $path) use ($currentPath): string {
    $active = $currentPath !== '/items/create'
        && ($currentPath === $path || str_starts_with($currentPath, $path . '/'));

    return $active ? ' aria-current="page"' : '';
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Déclarez un objet perdu ou trouvé et retrouvez son propriétaire grâce à un score de correspondance, sans jamais exposer vos coordonnées.">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f6f7fb" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0e1220" media="(prefers-color-scheme: dark)">
    <title><?= e($title ?? '') ?> · <?= e($appName) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/css/style.css">
    <script src="/js/app.js" defer></script>
</head>
<body>
    <a class="skip-link" href="#main">Aller au contenu</a>

    <header class="site-header">
        <div class="container header-inner">
            <a href="/" class="logo" aria-label="<?= e($appName) ?>, accueil">
                <span class="logo-mark" aria-hidden="true"><?= icon('search', 'icon icon-logo') ?></span>
                <span class="logo-text">Lost<span class="logo-amp">&amp;</span>Found <span class="logo-ai">AI</span></span>
            </a>

            <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="site-nav" hidden>
                <?= icon('menu') ?><span>Menu</span>
            </button>

            <nav class="nav" id="site-nav" aria-label="Navigation principale">
                <a href="/items"<?= $navCurrent('/items') ?>>Annonces</a>
                <?php if ($currentUser !== null): ?>
                    <a href="/my-items"<?= $navCurrent('/my-items') ?>>Mes annonces</a>
                    <a href="/conversations"<?= $navCurrent('/conversations') ?>>Messages</a>
                    <a href="/items/create?type=lost" class="btn btn-primary btn-sm"><?= icon('plus') ?>Déclarer</a>
                    <?php if ($currentUser['role'] === 'admin'): ?>
                        <?php /* Lien de modération, visible uniquement des administrateurs */ ?>
                        <a href="/admin"<?= $navCurrent('/admin') ?>>Administration<?php
                            $openReports = (new \App\Models\Report())->countOpen();
                            echo $openReports > 0 ? ' (' . $openReports . ')' : '';
                        ?></a>
                    <?php endif; ?>
                    <div class="nav-account">
                        <?php /* aria-label : sur mobile le nom est masqué, le lien garde un intitulé pour les lecteurs d'écran */ ?>
                        <a href="/account" class="nav-account-link"<?= $navCurrent('/account') ?> title="Mon compte"
                           aria-label="Mon compte (<?= e($currentUser['name']) ?>)">
                            <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($currentUser['name'], 0, 1))) ?></span>
                            <span class="nav-user"><?= e($currentUser['name']) ?></span>
                        </a>
                        <!-- La déconnexion passe par un formulaire POST protégé par CSRF -->
                        <form method="post" action="/logout" class="inline-form">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-ghost btn-sm" title="Se déconnecter">
                                <?= icon('logout') ?><span>Déconnexion</span>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <a href="/login"<?= $navCurrent('/login') ?>>Connexion</a>
                    <a href="/register" class="btn btn-primary btn-sm">Créer un compte</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="container main" id="main" tabindex="-1">
        <?php if ($currentUser !== null && $currentUser['email_verified_at'] === null && $currentPath !== '/verify-email'): ?>
            <?php /* Rappel tant que l'email n'est pas vérifié (publier et contacter sont bloqués) */ ?>
            <div class="alert alert-info verify-banner" role="status">
                <?= icon('shield') ?>
                <span>Confirmez votre adresse email pour publier une annonce et contacter d’autres utilisateurs.</span>
                <a href="/verify-email">Saisir mon code</a>
            </div>
        <?php endif; ?>

        <?php foreach (Flash::pull() as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>">
                <?= icon($flash['type'] === 'success' ? 'check' : 'shield') ?>
                <span><?= e($flash['message']) ?></span>
            </div>
        <?php endforeach; ?>

        <?= $content /* déjà échappé par la vue, ne pas réappliquer e() */ ?>
    </main>

    <footer class="site-footer">
        <div class="container footer-inner">
            <p class="footer-brand"><?= icon('shield') ?><?= e($appName) ?></p>
            <p>Vos coordonnées ne sont jamais affichées publiquement. Les échanges passent par la messagerie interne.</p>
            <nav class="footer-links" aria-label="Informations légales">
                <a href="/mentions-legales">Mentions légales</a>
                <a href="/confidentialite">Confidentialité</a>
                <a href="/cgu">Conditions d’utilisation</a>
            </nav>
        </div>
    </footer>
</body>
</html>
