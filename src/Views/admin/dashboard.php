<?php
/**
 * Tableau de bord administrateur : statistiques clés de la plateforme.
 * Variables : $userStats, $itemStats, $claimStats, $orderStats
 * (Report::countOpen est lu directement par _nav.php pour le badge « Signalements ».)
 */
?>
<section class="page-intro">
    <p class="eyebrow">Administration</p>
    <h1>Tableau de bord</h1>
    <p>Vue d’ensemble de la plateforme : comptes, annonces, demandes de restitution et livraisons.</p>
</section>

<?php require __DIR__ . '/_nav.php'; ?>

<div class="stat-grid">
    <div class="stat-card">
        <span class="stat-icon"><?= icon('user') ?></span>
        <span class="stat-value"><?= $userStats['total'] ?></span>
        <span class="stat-label">Utilisateurs</span>
        <p class="stat-detail">
            <?= $userStats['verified'] ?> email vérifié<?= $userStats['verified'] > 1 ? 's' : '' ?>
            · <?= $userStats['admins'] ?> administrateur<?= $userStats['admins'] > 1 ? 's' : '' ?>
            · +<?= $userStats['new_7d'] ?> cette semaine
        </p>
        <a class="link-arrow" href="/admin/users">Voir les comptes<?= icon('arrow-right') ?></a>
    </div>

    <div class="stat-card">
        <span class="stat-icon"><?= icon('package') ?></span>
        <span class="stat-value"><?= $itemStats['total'] ?></span>
        <span class="stat-label">Annonces</span>
        <p class="stat-detail">
            <?= $itemStats['lost'] ?> perdues · <?= $itemStats['found'] ?> trouvées<br>
            <?= $itemStats['open'] ?> ouvertes · <?= $itemStats['matched'] ?> en cours · <?= $itemStats['resolved'] ?> résolues
        </p>
        <a class="link-arrow" href="/admin/items">Voir les annonces<?= icon('arrow-right') ?></a>
    </div>

    <div class="stat-card">
        <span class="stat-icon"><?= icon('shield') ?></span>
        <span class="stat-value"><?= (new \App\Models\Report())->countOpen() ?></span>
        <span class="stat-label">Signalements en attente</span>
        <a class="link-arrow" href="/admin/reports">Voir les signalements<?= icon('arrow-right') ?></a>
    </div>

    <div class="stat-card">
        <span class="stat-icon"><?= icon('check') ?></span>
        <span class="stat-value"><?= $claimStats['pending'] ?></span>
        <span class="stat-label">Demandes de restitution en attente</span>
        <p class="stat-detail">
            <?= $claimStats['approved'] ?> acceptée<?= $claimStats['approved'] > 1 ? 's' : '' ?>
            · <?= $claimStats['rejected'] ?> refusée<?= $claimStats['rejected'] > 1 ? 's' : '' ?>
        </p>
    </div>

    <div class="stat-card">
        <span class="stat-icon"><?= icon('send') ?></span>
        <span class="stat-value"><?= $orderStats['delivered'] ?></span>
        <span class="stat-label">Livraisons terminées</span>
        <p class="stat-detail"><?= $orderStats['in_progress'] ?> en cours (payée ou expédiée)</p>
    </div>

    <div class="stat-card">
        <span class="stat-icon"><?= icon('sparkles') ?></span>
        <span class="stat-value"><?= number_format($orderStats['tips_available_cents'] / 100, 2, ',', ' ') ?> €</span>
        <span class="stat-label">Pourboires disponibles</span>
        <p class="stat-detail"><?= number_format($orderStats['tips_paid_cents'] / 100, 2, ',', ' ') ?> € déjà versés</p>
    </div>
</div>
