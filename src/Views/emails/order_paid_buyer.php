<?php
/**
 * Email au propriétaire : paiement confirmé.
 * Variables : $name, $itemTitle, $carrier, $tracking, $total, $orderLink
 */
?>
<h1 style="margin:0 0 12px;font-size:20px;">Paiement confirmé</h1>
<p style="margin:0 0 16px;">
    Bonjour <?= e($name) ?>, nous avons bien reçu votre paiement de <strong><?= e($total) ?></strong>
    pour la livraison de <strong>« <?= e($itemTitle) ?> »</strong> avec <?= e($carrier) ?>.
</p>
<p style="margin:0 0 16px;">
    La personne qui a trouvé votre objet a reçu l’étiquette. Vous serez prévenu dès que le colis sera déposé.
</p>
<p style="margin:0 0 8px;">Numéro de suivi : <strong><?= e($tracking) ?></strong></p>
<?= email_button($orderLink, 'Suivre ma livraison') ?>
