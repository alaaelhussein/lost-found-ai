<?php
/** Email : demande de restitution reçue. Variables : $name, $claimant, $itemTitle, $link */
?>
<h1 style="margin:0 0 12px;font-size:20px;">Bonjour <?= e($name) ?>,</h1>
<p style="margin:0 0 16px;">
    <strong><?= e($claimant) ?></strong> pense que l’objet que vous avez trouvé,
    <strong>« <?= e($itemTitle) ?> »</strong>, lui appartient.
</p>
<p style="margin:0 0 16px;">
    Comparez sa réponse et les détails donnés avec l’objet que vous avez en main,
    puis acceptez ou refusez la demande.
</p>
<?= email_button($link, 'Examiner la demande') ?>
<p style="margin:0;color:#5b6478;font-size:13px;">En cas de doute, refusez ou posez des questions par la messagerie du site.</p>
