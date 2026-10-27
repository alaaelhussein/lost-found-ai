<?php
/** Email : code de vérification. Variables : $name, $code, $minutes, $link */
?>
<h1 style="margin:0 0 12px;font-size:20px;">Bienvenue <?= e($name) ?> !</h1>
<p style="margin:0 0 16px;">Pour activer votre compte, saisissez ce code sur Lost&amp;Found AI :</p>
<p style="margin:0 0 16px;text-align:center;">
    <span style="display:inline-block;padding:14px 24px;border-radius:12px;background:#eef0ff;color:#312e81;font-size:32px;font-weight:bold;letter-spacing:8px;font-family:'Courier New',monospace;"><?= e($code) ?></span>
</p>
<p style="margin:0 0 8px;color:#5b6478;">Ce code est valable <?= (int) $minutes ?> minutes.</p>
<?= email_button($link, 'Saisir mon code') ?>
<p style="margin:0;color:#5b6478;font-size:13px;">Vous n’avez pas créé de compte ? Ignorez cet email : sans ce code, personne ne peut utiliser votre adresse.</p>
