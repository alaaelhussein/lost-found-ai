<?php
/**
 * Page d'erreur générique (404, 403, 405, 429, 500...).
 * Affichée par View::error($code, $message).
 */
$headlines = [
    403 => 'Action refusée',
    404 => 'Page introuvable',
    405 => 'Action non autorisée',
    422 => 'Demande invalide',
    429 => 'Doucement…',
    500 => 'Un problème est survenu',
];
?>
<section class="error-page">
    <p class="error-code"><?= e($code) ?></p>
    <h1><?= e($headlines[$code] ?? 'Erreur') ?></h1>
    <p><?= e($message) ?></p>
    <div class="error-actions">
        <a class="btn btn-primary" href="/">Retour à l'accueil</a>
        <a class="btn btn-ghost" href="/items">Voir les annonces</a>
    </div>
</section>
