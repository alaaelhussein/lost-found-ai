<?php
/** Email au propriétaire : colis déposé. Variables : $name, $itemTitle, $carrier, $tracking, $orderLink */
?>
<h1 style="margin:0 0 12px;font-size:20px;">Votre objet est en route !</h1>
<p style="margin:0 0 16px;">
    Bonjour <?= e($name) ?>, <strong>« <?= e($itemTitle) ?> »</strong> a été déposé chez <?= e($carrier) ?>.
</p>
<p style="margin:0 0 8px;">Numéro de suivi : <strong><?= e($tracking) ?></strong></p>
<?= email_button($orderLink, 'Confirmer la réception') ?>
<p style="margin:0;color:#5b6478;font-size:13px;">Quand vous aurez récupéré votre objet, confirmez la réception : c’est ce qui débloque le pourboire de la personne qui l’a trouvé.</p>
