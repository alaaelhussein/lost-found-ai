<?php
/** Email : lien « mot de passe oublié ». Variables : $name, $link, $minutes */
?>
<h1 style="margin:0 0 12px;font-size:20px;">Bonjour <?= e($name) ?>,</h1>
<p style="margin:0 0 16px;">Vous avez demandé à changer le mot de passe de votre compte Lost&amp;Found AI. Cliquez sur le bouton ci-dessous pour en choisir un nouveau :</p>
<?= email_button($link, 'Choisir un nouveau mot de passe') ?>
<p style="margin:0 0 8px;color:#5b6478;">Ce lien est valable <?= (int) $minutes ?> minutes et ne fonctionne qu’une seule fois.</p>
<p style="margin:0;color:#5b6478;font-size:13px;">Vous n’êtes pas à l’origine de cette demande ? Ignorez cet email : votre mot de passe actuel reste valable et personne ne peut le changer sans ce lien.</p>
