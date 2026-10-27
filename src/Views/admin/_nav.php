<?php
/**
 * Sous-navigation commune aux pages /admin/* (inclus en haut de chaque vue).
 */

$adminPath = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';
$adminOpenReports = (new \App\Models\Report())->countOpen();

$adminLink = static function (string $path, string $label) use ($adminPath): string {
    $current = $adminPath === $path ? ' aria-current="page"' : '';

    return '<a href="' . e($path) . '"' . $current . '>' . $label . '</a>';
};
?>
<nav class="admin-nav" aria-label="Administration">
    <?= $adminLink('/admin', 'Tableau de bord') ?>
    <?= $adminLink('/admin/reports', 'Signalements' . ($adminOpenReports > 0 ? ' (' . $adminOpenReports . ')' : '')) ?>
    <?= $adminLink('/admin/users', 'Utilisateurs') ?>
    <?= $adminLink('/admin/items', 'Annonces') ?>
</nav>
