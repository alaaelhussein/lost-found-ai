<?php
/**
 * Mise en page commune à tous les emails.
 * Variables : $subject, $content (HTML du gabarit de l'email, déjà échappé).
 *
 * Contraintes propres aux emails :
 *   - styles écrits directement dans les balises (style="...") : la plupart
 *     des messageries ignorent les <style> et les fichiers CSS ;
 *   - mise en page en <table> : c'est la seule qui s'affiche correctement
 *     partout (Outlook notamment) ;
 *   - largeur maximale 560 px, lisible sur téléphone.
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($subject) ?></title>
</head>
<body style="margin:0;padding:0;background:#f6f7fb;font-family:Arial,Helvetica,sans-serif;color:#141a2b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
                    <tr>
                        <td style="padding:0 0 16px;font-size:18px;font-weight:bold;color:#141a2b;">
                            <span style="display:inline-block;width:28px;height:28px;line-height:28px;text-align:center;border-radius:8px;background:#4f46e5;color:#ffffff;font-size:14px;">LF</span>
                            &nbsp;Lost&amp;Found <span style="color:#4f46e5;">AI</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#ffffff;border:1px solid #e2e6ef;border-radius:16px;padding:28px 24px;font-size:15px;line-height:1.6;">
                            <?= $content /* déjà échappé par le gabarit */ ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 8px 0;font-size:12px;line-height:1.5;color:#5b6478;">
                            Lost&amp;Found AI · Vos coordonnées ne sont jamais communiquées aux autres utilisateurs.<br>
                            Ne répondez pas à cet email : utilisez la messagerie du site.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
