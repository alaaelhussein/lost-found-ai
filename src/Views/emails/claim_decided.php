<?php
/**
 * Email : décision sur une demande de restitution.
 * Variables : $name, $approved, $itemTitle, $orderLink, $itemLink, $tip,
 *             $conversationLink (remise en main propre quand la livraison est désactivée, sinon null)
 */
?>
<h1 style="margin:0 0 12px;font-size:20px;">Bonjour <?= e($name) ?>,</h1>
<?php if ($approved): ?>
    <p style="margin:0 0 16px;">
        Votre demande pour <strong>« <?= e($itemTitle) ?> »</strong> est <strong style="color:#066b58;">acceptée</strong> :
        la personne qui l’a trouvé confirme qu’il s’agit bien de votre objet.
    </p>
    <?php if ($conversationLink !== null): ?>
        <p style="margin:0 0 16px;">
            Une conversation privée vient d’être ouverte avec elle : convenez d’un rendez-vous
            dans un lieu public (gare, café, accueil d’un bâtiment…) pour récupérer votre objet.
        </p>
        <?= email_button($conversationLink, 'Organiser la remise') ?>
        <p style="margin:0;color:#5b6478;font-size:13px;">Ne communiquez jamais vos coordonnées bancaires ; aucun paiement n’est nécessaire pour une remise en main propre.</p>
    <?php else: ?>
        <p style="margin:0 0 16px;">
            Faites-vous livrer en point relais (votre adresse reste privée) ou à domicile.
            Vous payez seulement la livraison et un pourboire de <?= e($tip) ?> pour remercier la personne.
        </p>
        <?= email_button($orderLink, 'Choisir la livraison') ?>
        <p style="margin:0;color:#5b6478;font-size:13px;">Vous préférez une remise en main propre ? Organisez-la par la messagerie du site, dans un lieu public.</p>
    <?php endif; ?>
<?php else: ?>
    <p style="margin:0 0 16px;">
        Votre demande pour <strong>« <?= e($itemTitle) ?> »</strong> a été refusée : les détails donnés
        ne correspondent pas à l’objet trouvé.
    </p>
    <?= email_button($itemLink, 'Voir l’annonce') ?>
    <p style="margin:0;color:#5b6478;font-size:13px;">S’il s’agit d’une erreur, contactez la personne par la messagerie du site.</p>
<?php endif; ?>
