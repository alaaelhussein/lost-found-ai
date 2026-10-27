<?php
/** Email à la personne qui a trouvé l'objet : bien reçu. Variables : $name, $buyer, $itemTitle, $tip, $orderLink */
?>
<h1 style="margin:0 0 12px;font-size:20px;">Mission accomplie, <?= e($name) ?> !</h1>
<p style="margin:0 0 16px;">
    <?= e($buyer) ?> a bien récupéré <strong>« <?= e($itemTitle) ?> »</strong> grâce à vous. Merci pour votre geste.
</p>
<p style="margin:0 0 16px;">Votre pourboire de <strong style="color:#066b58;"><?= e($tip) ?></strong> est maintenant disponible.</p>
<?= email_button($orderLink, 'Voir la commande') ?>
