<?php
/**
 * Suivi d'une commande de livraison.
 * Variables : $order, $isBuyer (propriétaire) ou expéditeur sinon, $carrierName, $canPay
 *
 * Les actions affichées dépendent du rôle ET de l'étape :
 *   propriétaire : payer (pending_payment), confirmer la réception (shipped)
 *   expéditeur   : imprimer l'étiquette et confirmer le dépôt (paid)
 */

use App\Core\Csrf;

$steps = [
    'pending_payment' => 'Paiement',
    'paid'            => 'Colis à déposer',
    'shipped'         => 'En route',
    'delivered'       => 'Reçu',
];
$order_keys = array_keys($steps);
$currentIndex = array_search($order['status'], $order_keys, true);
$modeLabels = ['relay' => 'Point relais', 'locker' => 'Casier', 'home' => 'Domicile'];
?>
<a class="back-link" href="/my-items"><?= icon('arrow-left') ?>Mes annonces</a>

<section class="form-page">
    <header class="form-header">
        <p class="eyebrow">Commande LF-<?= str_pad((string) $order['id'], 5, '0', STR_PAD_LEFT) ?></p>
        <h1><?= e($order['item_title']) ?></h1>
        <p><?= $isBuyer ? 'Expédié par ' . e($order['finder_name']) : 'À envoyer à ' . e($order['buyer_name']) ?> · <?= e($carrierName) ?></p>
    </header>

    <!-- Frise des étapes : aria-current indique l'étape en cours aux lecteurs d'écran -->
    <ol class="timeline" aria-label="Avancement de la livraison">
        <?php foreach ($steps as $key => $label): ?>
            <?php $index = array_search($key, $order_keys, true); ?>
            <li class="<?= $index < $currentIndex || $order['status'] === 'delivered' ? 'is-done' : ($index === $currentIndex ? 'is-current' : '') ?>"
                <?= $index === $currentIndex ? 'aria-current="step"' : '' ?>>
                <span class="timeline-dot"><?= $index < $currentIndex || $order['status'] === 'delivered' ? icon('check') : $index + 1 ?></span>
                <?= e($label) ?>
            </li>
        <?php endforeach; ?>
    </ol>

    <div class="card order-card">
        <?php if ($order['status'] === 'pending_payment'): ?>
            <?php if ($isBuyer): ?>
                <h2>Paiement en attente</h2>
                <p>Finalisez le paiement pour que <?= e($order['finder_name']) ?> reçoive l’étiquette d’envoi.</p>
                <a class="btn btn-primary btn-lg" href="/orders/<?= e($order['id']) ?>/pay">Payer <?= e(money((int) $order['total_cents'])) ?></a>
            <?php else: ?>
                <h2>En attente du paiement</h2>
                <p>Vous recevrez l’étiquette par email dès que <?= e($order['buyer_name']) ?> aura payé.</p>
            <?php endif; ?>

        <?php elseif ($order['status'] === 'paid'): ?>
            <?php if ($isBuyer): ?>
                <h2>Paiement confirmé</h2>
                <p><?= e($order['finder_name']) ?> va déposer le colis. Vous serez prévenu par email.</p>
            <?php else: ?>
                <h2>À vous de jouer !</h2>
                <ol class="instructions">
                    <li>Emballez l’objet (enveloppe matelassée ou petit carton).</li>
                    <li>Imprimez l’étiquette et collez-la bien à plat.</li>
                    <li>Déposez le colis chez <?= e($carrierName) ?>, puis confirmez ci-dessous.</li>
                </ol>
                <div class="order-actions">
                    <a class="btn btn-secondary" href="/orders/<?= e($order['id']) ?>/label"><?= icon('package') ?>Imprimer l’étiquette</a>
                    <form method="post" action="/orders/<?= e($order['id']) ?>/shipped">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn-primary"><?= icon('check') ?>J’ai déposé le colis</button>
                    </form>
                </div>
            <?php endif; ?>

        <?php elseif ($order['status'] === 'shipped'): ?>
            <?php if ($isBuyer): ?>
                <h2>Votre objet est en route</h2>
                <p>Quand vous l’avez récupéré, confirmez la réception : c’est ce qui transmet le pourboire.</p>
                <form method="post" action="/orders/<?= e($order['id']) ?>/delivered"
                      data-confirm="Confirmez-vous avoir bien récupéré votre objet ?">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn-primary btn-lg"><?= icon('check') ?>J’ai bien reçu mon objet</button>
                </form>
            <?php else: ?>
                <h2>Colis déposé, merci !</h2>
                <p>Le pourboire sera disponible dès que <?= e($order['buyer_name']) ?> aura confirmé la réception.</p>
            <?php endif; ?>

        <?php elseif ($order['status'] === 'delivered'): ?>
            <h2><?= $isBuyer ? 'Objet récupéré 🎉' : 'Mission accomplie 🎉' ?></h2>
            <p><?= $isBuyer
                ? 'Merci d’avoir utilisé Lost&Found AI. ' . e($order['finder_name']) . ' reçoit votre pourboire.'
                : 'Votre pourboire de ' . e(money((int) $order['tip_cents'])) . ' est disponible.' ?></p>
        <?php endif; ?>
    </div>

    <dl class="detail-info order-summary">
        <div><dt>Mode</dt><dd><?= e($modeLabels[$order['delivery_mode']] ?? $order['delivery_mode']) ?></dd></div>
        <?php if ($isBuyer && $order['relay_point_label'] !== null): ?>
            <div><dt>Point de retrait</dt><dd><?= e($order['relay_point_label']) ?></dd></div>
        <?php endif; ?>
        <?php if ($order['tracking_number'] !== null): ?>
            <div><dt>N° de suivi</dt><dd class="mono"><?= e($order['tracking_number']) ?></dd></div>
        <?php endif; ?>
        <?php if ($isBuyer): ?>
            <div><dt>Livraison</dt><dd><?= e(money((int) $order['shipping_cents'])) ?></dd></div>
            <div><dt>Pourboire</dt><dd><?= e(money((int) $order['tip_cents'])) ?></dd></div>
            <div><dt>Total</dt><dd><strong><?= e(money((int) $order['total_cents'])) ?></strong></dd></div>
        <?php else: ?>
            <div><dt>Votre pourboire</dt><dd><?= e(money((int) $order['tip_cents'])) ?> · <?= $order['tip_status'] === 'pending' ? 'après réception' : 'disponible' ?></dd></div>
        <?php endif; ?>
    </dl>
</section>
