<?php
/**
 * Paiement SIMULÉ (mode développement, aucune clé Stripe configurée).
 * Variables : $order, $carrierName
 *
 * Aucune donnée bancaire n'est demandée : en production, cette page est
 * remplacée par la page sécurisée de Stripe (PaymentService::startCheckout).
 */

use App\Core\Csrf;
?>
<section class="auth">
    <div class="auth-card card">
        <header class="auth-header">
            <span class="auth-icon"><?= icon('shield') ?></span>
            <h1>Paiement</h1>
            <p>Livraison de « <?= e($order['item_title']) ?> »</p>
        </header>

        <dl class="detail-info">
            <div><dt><?= e($carrierName) ?></dt><dd><?= e(money((int) $order['shipping_cents'])) ?></dd></div>
            <div><dt>Pourboire pour <?= e($order['finder_name']) ?></dt><dd><?= e(money((int) $order['tip_cents'])) ?></dd></div>
            <div><dt><strong>Total</strong></dt><dd><strong><?= e(money((int) $order['total_cents'])) ?></strong></dd></div>
        </dl>

        <p class="alert alert-info"><?= icon('shield') ?><span><strong>Mode démonstration :</strong> aucun argent n’est débité. En production, le paiement se fait sur la page sécurisée de Stripe.</span></p>

        <form method="post" action="/orders/<?= e($order['id']) ?>/pay">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Payer <?= e(money((int) $order['total_cents'])) ?> (simulation)</button>
        </form>
        <p class="auth-switch"><a href="/orders/<?= e($order['id']) ?>">Annuler</a></p>
    </div>
</section>
