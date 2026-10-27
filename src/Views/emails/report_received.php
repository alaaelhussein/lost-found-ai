<?php
/**
 * Email à l'équipe de modération : une annonce a été signalée.
 * Variables : $itemTitle, $reason, $details (ou null), $itemLink, $adminLink
 */
?>
<h1 style="margin:0 0 12px;font-size:20px;">Nouvelle annonce signalée</h1>
<p style="margin:0 0 8px;"><strong>Annonce :</strong> « <?= e($itemTitle) ?> »</p>
<p style="margin:0 0 8px;"><strong>Motif :</strong> <?= e($reason) ?></p>
<?php if ($details !== null && $details !== ''): ?>
    <p style="margin:0 0 16px;"><strong>Précisions :</strong><br><?= nl2br(e($details)) ?></p>
<?php endif; ?>
<?= email_button($adminLink, 'Traiter les signalements') ?>
<p style="margin:0;color:#5b6478;font-size:13px;">Voir l’annonce : <a href="<?= e($itemLink) ?>" style="color:#4f46e5;"><?= e($itemLink) ?></a></p>
