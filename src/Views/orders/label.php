<?php
/**
 * Étiquette d'envoi à imprimer (personne qui a trouvé l'objet).
 * Variables : $order, $address (domicile, déchiffrée) ou null, $carrierName, $sender
 *
 * Anonymat :
 *   - EXPÉDITEUR : l'adresse de la plateforme, jamais celle de la personne qui expédie ;
 *   - DESTINATAIRE : en point relais / casier, seulement le prénom et le point.
 *
 * SIMULATION : en production, l'étiquette est un PDF officiel fourni par le
 * transporteur (avec son code-barres), via l'agrégateur (Boxtal, Sendcloud...).
 */
?>
<div class="label-actions no-print">
    <a class="back-link" href="/orders/<?= e($order['id']) ?>"><?= icon('arrow-left') ?>Retour à la commande</a>
    <button type="button" class="btn btn-primary" data-print><?= icon('package') ?>Imprimer</button>
</div>

<article class="shipping-label" aria-label="Étiquette d'envoi">
    <header class="label-header">
        <strong><?= e($carrierName) ?></strong>
        <span>Port payé · LF-<?= str_pad((string) $order['id'], 5, '0', STR_PAD_LEFT) ?></span>
    </header>

    <section class="label-block">
        <h2>Expéditeur</h2>
        <p><?= e($sender) ?></p>
    </section>

    <section class="label-block label-recipient">
        <h2>Destinataire</h2>
        <p class="label-name"><?= e($order['recipient_name']) ?></p>
        <?php if ($address !== null): ?>
            <p><?= e($address['street']) ?><br><?= e($address['zip']) ?> <?= e($address['city']) ?></p>
        <?php else: ?>
            <p><?= e($order['relay_point_label']) ?></p>
            <p class="label-note">Retrait en <?= $order['delivery_mode'] === 'locker' ? 'casier' : 'point relais' ?></p>
        <?php endif; ?>
    </section>

    <!-- Code-barres décoratif : le vrai est généré par le transporteur -->
    <div class="label-barcode" aria-hidden="true"></div>
    <p class="label-tracking"><?= e($order['tracking_number']) ?></p>
    <p class="label-sim no-print">Étiquette de démonstration : non valable chez le transporteur.</p>
</article>
