<?php
/**
 * Email à la personne qui a trouvé l'objet : la livraison est payée.
 * Variables : $name, $itemTitle, $carrier, $tracking, $labelLink, $orderLink, $tip
 */
?>
<h1 style="margin:0 0 12px;font-size:20px;">Merci <?= e($name) ?> !</h1>
<p style="margin:0 0 16px;">
    Le propriétaire de <strong>« <?= e($itemTitle) ?> »</strong> a payé la livraison.
    Il ne reste plus qu’à envoyer le colis :
</p>
<ol style="margin:0 0 16px;padding-left:20px;">
    <li>Emballez l’objet (enveloppe matelassée ou petit carton).</li>
    <li>Imprimez l’étiquette prépayée <?= e($carrier) ?> et collez-la sur le colis.</li>
    <li>Déposez-le dans un point relais ou un bureau <?= e($carrier) ?>, puis cliquez sur « J’ai déposé le colis ».</li>
</ol>
<?= email_button($labelLink, 'Imprimer l’étiquette') ?>
<p style="margin:0 0 8px;">Numéro de suivi : <strong><?= e($tracking) ?></strong></p>
<p style="margin:0 0 16px;">Votre pourboire de <strong><?= e($tip) ?></strong> sera disponible dès que le propriétaire aura reçu son objet.</p>
<p style="margin:0;color:#5b6478;font-size:13px;">Votre adresse n’apparaît pas sur l’étiquette : l’expéditeur indiqué est Lost&amp;Found AI. <a href="<?= e($orderLink) ?>" style="color:#5b6478;">Suivre la commande</a></p>
