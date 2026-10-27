<?php
/**
 * Email : nouvelle correspondance.
 * Variables : $name, $score, $yourTitle, $yourType ('lost'|'found'), $matchTitle, $link, $unsubscribe
 */
?>
<h1 style="margin:0 0 12px;font-size:20px;">Bonne nouvelle, <?= e($name) ?> !</h1>
<p style="margin:0 0 16px;">
    Une nouvelle annonce ressemble à votre objet <?= $yourType === 'lost' ? 'perdu' : 'trouvé' ?>
    <strong>« <?= e($yourTitle) ?> »</strong>.
</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e6ef;border-radius:12px;">
    <tr>
        <td style="padding:14px 16px;"><?= e($matchTitle) ?></td>
        <td style="padding:14px 16px;text-align:right;white-space:nowrap;">
            <strong style="color:#066b58;font-size:20px;"><?= (int) $score ?> %</strong>
        </td>
    </tr>
</table>
<?= email_button($link, 'Voir la correspondance') ?>
<p style="margin:0;color:#5b6478;font-size:13px;">
    Le score compare la catégorie, la couleur, le lieu, la date et les mots-clés.
    Vous ne voulez plus recevoir ces alertes ? <a href="<?= e($unsubscribe) ?>" style="color:#5b6478;">Se désinscrire</a>
</p>
