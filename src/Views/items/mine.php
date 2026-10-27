<?php
/**
 * Tableau de bord des annonces de l'utilisateur connecté.
 *
 * Variables : $items, $matchCounts ([id de l'annonce => nombre de correspondances]),
 *             $myClaims (mes demandes de restitution), $shipments (colis que je dois envoyer).
 *
 * La confirmation avant suppression passe par l'attribut data-confirm, lu par
 * public/js/app.js : un attribut onsubmit="..." serait bloqué par la
 * Content-Security-Policy (aucun JavaScript inline autorisé).
 */

use App\Core\Csrf;

$openCount = count(array_filter($items, static fn (array $item): bool => $item['status'] !== 'resolved'));
?>
<section class="page-intro page-intro-row">
    <div>
        <p class="eyebrow">Votre espace</p>
        <h1>Mes annonces</h1>
        <p>
            <?php if ($items === []): ?>
                Gérez vos déclarations et marquez un objet comme résolu quand il est retrouvé.
            <?php else: ?>
                <?= $openCount ?> en ligne · <?= count($items) - $openCount ?> résolue<?= count($items) - $openCount > 1 ? 's' : '' ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="/items/create?type=lost"><?= icon('search') ?>Objet perdu</a>
        <a class="btn btn-secondary" href="/items/create?type=found"><?= icon('package') ?>Objet trouvé</a>
    </div>
</section>

<?php if ($items === []): ?>
    <section class="empty-state">
        <span class="empty-icon"><?= icon('package') ?></span>
        <h2>Vous n'avez encore aucune annonce</h2>
        <p>Déclarez un objet perdu ou trouvé pour commencer.</p>
    </section>
<?php else: ?>
    <ul class="my-items-list">
        <?php foreach ($items as $item): ?>
            <?php $isLost = $item['type'] === 'lost'; $isResolved = $item['status'] === 'resolved'; ?>
            <li class="my-item<?= $isResolved ? ' is-resolved' : '' ?>">
                <div class="my-item-thumb">
                    <?php if (!empty($item['photo_path'])): ?>
                        <img src="/<?= e($item['photo_path']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <span class="<?= $isLost ? 'is-lost' : 'is-found' ?>"><?= icon($isLost ? 'search' : 'package') ?></span>
                    <?php endif; ?>
                </div>
                <div class="my-item-main">
                    <div class="item-meta">
                        <span class="badge <?= $isLost ? 'badge-lost' : 'badge-found' ?>"><?= $isLost ? 'Perdu' : 'Trouvé' ?></span>
                        <span class="status status-<?= e($item['status']) ?>"><?= $isResolved ? 'Résolu' : 'En ligne' ?></span>
                        <?php $matchCount = $matchCounts[(int) $item['id']] ?? 0; ?>
                        <?php if (!$isResolved && $matchCount > 0): ?>
                            <a class="badge badge-match" href="/items/<?= e($item['id']) ?>#correspondances">
                                <?= icon('sparkles') ?><?= $matchCount ?> correspondance<?= $matchCount > 1 ? 's' : '' ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <h2><a href="/items/<?= e($item['id']) ?>"><?= e($item['title']) ?></a></h2>
                    <p><?= e($item['location_label']) ?> · <time datetime="<?= e($item['date_event']) ?>"><?= e(date('d/m/Y', strtotime($item['date_event']))) ?></time></p>
                </div>
                <div class="my-item-actions">
                    <form method="post" action="/items/<?= e($item['id']) ?>/status">
                        <?= Csrf::field() ?>
                        <?php if ($isResolved): ?>
                            <input type="hidden" name="status" value="open">
                            <button class="btn btn-ghost btn-sm" type="submit"><?= icon('rotate') ?>Remettre en ligne</button>
                        <?php else: ?>
                            <input type="hidden" name="status" value="resolved">
                            <button class="btn btn-secondary btn-sm" type="submit"><?= icon('check') ?>Marquer résolu</button>
                        <?php endif; ?>
                    </form>
                    <form method="post" action="/items/<?= e($item['id']) ?>/delete"
                          data-confirm="Supprimer définitivement l'annonce « <?= e($item['title']) ?> » ? Les conversations liées seront aussi supprimées.">
                        <?= Csrf::field() ?>
                        <button class="btn btn-danger btn-sm" type="submit" aria-label="Supprimer l'annonce <?= e($item['title']) ?>">
                            <?= icon('trash') ?><span>Supprimer</span>
                        </button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($myClaims !== []): ?>
    <?php /* Objets que j'ai réclamés : état de la demande et de la livraison */ ?>
    <section class="dashboard-section" aria-labelledby="my-claims-title">
        <h2 id="my-claims-title" class="section-title"><?= icon('shield') ?>Mes demandes de restitution</h2>
        <ul class="task-list">
            <?php foreach ($myClaims as $claim): ?>
                <li class="task">
                    <div>
                        <a href="/items/<?= e($claim['item_id']) ?>"><?= e($claim['item_title']) ?></a>
                        <span class="status status-claim-<?= e($claim['status']) ?>">
                            <?= ['pending' => 'En attente', 'approved' => 'Acceptée', 'rejected' => 'Refusée'][$claim['status']] ?>
                        </span>
                    </div>
                    <?php if ($claim['status'] === 'approved' && $claim['order_id'] === null && \App\Services\ShippingService::isEnabled()): ?>
                        <a class="btn btn-primary btn-sm" href="/claims/<?= e($claim['id']) ?>/order"><?= icon('package') ?>Choisir la livraison</a>
                    <?php elseif ($claim['status'] === 'approved' && $claim['order_id'] === null): ?>
                        <?php /* Livraison désactivée : remise en main propre */ ?>
                        <a class="btn btn-primary btn-sm" href="/conversations"><?= icon('message') ?>Organiser la remise</a>
                    <?php elseif ($claim['order_id'] !== null): ?>
                        <a class="btn btn-ghost btn-sm" href="/orders/<?= e($claim['order_id']) ?>">Suivre la livraison</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if ($shipments !== []): ?>
    <?php /* Objets que j'ai trouvés et que je dois envoyer (ou déjà envoyés) */ ?>
    <section class="dashboard-section" aria-labelledby="shipments-title">
        <h2 id="shipments-title" class="section-title"><?= icon('package') ?>Colis à envoyer</h2>
        <ul class="task-list">
            <?php foreach ($shipments as $shipment): ?>
                <li class="task">
                    <div>
                        <a href="/orders/<?= e($shipment['id']) ?>"><?= e($shipment['item_title']) ?></a>
                        <span class="status status-order-<?= e($shipment['status']) ?>">
                            <?= ['paid' => 'À déposer', 'shipped' => 'En route', 'delivered' => 'Livré', 'cancelled' => 'Annulé'][$shipment['status']] ?? e($shipment['status']) ?>
                        </span>
                        <span class="task-tip">Pourboire <?= e(money((int) $shipment['tip_cents'])) ?><?= $shipment['tip_status'] === 'available' ? ' · disponible' : '' ?></span>
                    </div>
                    <?php if ($shipment['status'] === 'paid'): ?>
                        <a class="btn btn-primary btn-sm" href="/orders/<?= e($shipment['id']) ?>"><?= icon('package') ?>Envoyer</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
