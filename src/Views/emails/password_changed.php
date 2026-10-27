<?php
/** Email : alerte de sécurité après un changement de mot de passe. Variables : $name, $link */
?>
<h1 style="margin:0 0 12px;font-size:20px;">Bonjour <?= e($name) ?>,</h1>
<p style="margin:0 0 16px;">Le mot de passe de votre compte Lost&amp;Found AI vient d’être modifié. Par sécurité, vos autres appareils ont été déconnectés.</p>
<p style="margin:0 0 16px;">C’était bien vous ? Vous n’avez rien à faire.</p>
<p style="margin:0 0 8px;"><strong>Ce n’était pas vous ?</strong> Reprenez immédiatement la main sur votre compte :</p>
<?= email_button($link, 'Réinitialiser mon mot de passe') ?>
