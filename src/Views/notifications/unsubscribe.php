<?php
/**
 * Désinscription des emails de correspondance (lien signé reçu par email).
 * Variables : $valid (signature correcte), $done (désinscription effectuée),
 *             $userId et $signature (si !$done, pour le formulaire de confirmation)
 */

use App\Core\Csrf;
?>
<section class="auth">
    <div class="auth-card card">
        <header class="auth-header">
            <span class="auth-icon"><?= icon('message') ?></span>
            <?php if (!$valid): ?>
                <h1>Lien invalide</h1>
                <p>Ce lien de désinscription est incomplet ou a été modifié.</p>
            <?php elseif ($done): ?>
                <h1>C’est fait</h1>
                <p>Vous ne recevrez plus d’emails pour les nouvelles correspondances. Les emails liés à vos demandes et livraisons continuent d’arriver.</p>
            <?php else: ?>
                <h1>Se désinscrire ?</h1>
                <p>Vous ne serez plus prévenu par email quand une annonce correspond à la vôtre.</p>
            <?php endif; ?>
        </header>

        <?php if ($valid && !$done): ?>
            <form method="post" action="/notifications/unsubscribe">
                <?= Csrf::field() ?>
                <input type="hidden" name="u" value="<?= e($userId) ?>">
                <input type="hidden" name="s" value="<?= e($signature) ?>">
                <button type="submit" class="btn btn-primary btn-block">Confirmer la désinscription</button>
            </form>
        <?php endif; ?>
        <p class="auth-switch"><a href="/">Retour à l’accueil</a></p>
    </div>
</section>
