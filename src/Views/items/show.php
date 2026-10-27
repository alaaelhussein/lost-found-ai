<?php
/**
 * Détail public d'une annonce.
 *
 * Variables : $item, $isOwner (l'utilisateur connecté est l'auteur),
 * $matches (correspondances calculées, seulement pour l'auteur),
 * $claims (demandes de restitution reçues, objets trouvés, seulement pour l'auteur),
 * $myClaim (demande de l'utilisateur connecté sur cet objet, ou null).
 */

use App\Core\Auth;
use App\Core\Csrf;

$isLost = $item['type'] === 'lost';
$isOpen = $item['status'] === 'open';
// Livraison payante proposée ? Sinon : remise en main propre (voir ShippingService::isEnabled)
$deliveryEnabled = \App\Services\ShippingService::isEnabled();
?>
<a class="back-link" href="/items"><?= icon('arrow-left') ?>Retour aux annonces</a>

<article class="detail">
    <div class="detail-media">
        <?php if (!empty($item['photo_path'])): ?>
            <img class="detail-image" src="/<?= e($item['photo_path']) ?>" alt="Photo de l'objet : <?= e($item['title']) ?>">
        <?php else: ?>
            <div class="detail-placeholder <?= $isLost ? 'is-lost' : 'is-found' ?>">
                <?= icon($isLost ? 'search' : 'package', 'icon icon-xxl') ?>
                <span>Pas de photo</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="detail-body">
        <div class="detail-badges">
            <span class="badge <?= $isLost ? 'badge-lost' : 'badge-found' ?>"><?= $isLost ? 'Objet perdu' : 'Objet trouvé' ?></span>
            <?php if (!$isOpen): ?>
                <span class="badge badge-neutral"><?= $item['status'] === 'resolved' ? 'Résolu' : 'En cours de restitution' ?></span>
            <?php endif; ?>
        </div>

        <h1><?= e($item['title']) ?></h1>
        <p class="detail-description"><?= nl2br(e($item['description'])) ?></p>

        <dl class="detail-info">
            <div><dt><?= icon('package') ?>Catégorie</dt><dd><?= e($item['category']) ?></dd></div>
            <?php if (!empty($item['color'])): ?>
                <div><dt><span class="icon-dot" aria-hidden="true"></span>Couleur</dt><dd><?= e($item['color']) ?></dd></div>
            <?php endif; ?>
            <div><dt><?= icon('map-pin') ?>Lieu</dt><dd><?= e($item['location_label']) ?></dd></div>
            <div><dt><?= icon('calendar') ?><?= $isLost ? 'Perdu le' : 'Trouvé le' ?></dt><dd><time datetime="<?= e($item['date_event']) ?>"><?= e(date('d/m/Y', strtotime($item['date_event']))) ?></time></dd></div>
            <div><dt><?= icon('user') ?>Publié par</dt><dd><?= e($item['user_name']) ?></dd></div>
        </dl>

        <?php if ($isOwner): ?>
            <div class="contact-box">
                <h2>C'est votre annonce</h2>
                <p>
                    <?php if ($isOpen && $matches !== []): ?>
                        <a href="#correspondances"><?= count($matches) ?> correspondance<?= count($matches) > 1 ? 's' : '' ?> possible<?= count($matches) > 1 ? 's' : '' ?></a> plus bas.
                    <?php endif; ?>
                    Gérez-la (marquer comme résolue, supprimer) depuis votre espace.
                </p>
                <a class="btn btn-secondary" href="/my-items">Gérer mes annonces</a>
            </div>
        <?php elseif ($myClaim !== null): ?>
            <?php /* L'utilisateur a déjà demandé cet objet : on affiche où en est sa demande */ ?>
            <div class="contact-box claim-status claim-status-<?= e($myClaim['status']) ?>">
                <?php if ($myClaim['status'] === 'pending'): ?>
                    <h2><?= icon('shield') ?>Demande envoyée</h2>
                    <p><?= e($item['user_name']) ?> compare vos réponses avec l’objet. Vous serez prévenu par email.</p>
                <?php elseif ($myClaim['status'] === 'approved'): ?>
                    <h2><?= icon('check') ?>Demande acceptée !</h2>
                    <?php if ($myClaim['order_id'] === null && $deliveryEnabled): ?>
                        <p>Faites-vous livrer : point relais (adresse privée) ou domicile.</p>
                        <a class="btn btn-primary btn-block" href="/claims/<?= e($myClaim['id']) ?>/order"><?= icon('package') ?>Choisir la livraison</a>
                    <?php elseif ($myClaim['order_id'] === null): ?>
                        <?php /* Livraison désactivée : remise en main propre via la messagerie */ ?>
                        <p>Convenez avec <?= e($item['user_name']) ?> d’un rendez-vous dans un lieu public pour récupérer votre objet.</p>
                        <form method="post" action="/items/<?= e($item['id']) ?>/contact">
                            <?= Csrf::field() ?>
                            <button class="btn btn-primary btn-block" type="submit"><?= icon('message') ?>Organiser la remise</button>
                        </form>
                    <?php else: ?>
                        <p>Votre livraison est en cours.</p>
                        <a class="btn btn-primary btn-block" href="/orders/<?= e($myClaim['order_id']) ?>">Suivre ma livraison</a>
                    <?php endif; ?>
                <?php else: ?>
                    <h2>Demande refusée</h2>
                    <p>Les détails donnés ne correspondaient pas à l’objet. En cas d’erreur, contactez <?= e($item['user_name']) ?> par la messagerie.</p>
                <?php endif; ?>
            </div>
        <?php elseif (!$isOpen): ?>
            <div class="contact-box contact-box-muted">
                <p>Cette annonce n'accepte plus de nouveaux contacts.</p>
            </div>
        <?php elseif (Auth::check()): ?>
            <div class="contact-box">
                <h2><?= $isLost ? 'Vous avez retrouvé cet objet ?' : 'C\'est votre objet ?' ?></h2>
                <?php if (!$isLost): ?>
                    <?php if ($deliveryEnabled): ?>
                        <p>Prouvez qu’il est à vous, puis faites-vous livrer : vous ne payez que la livraison et <?= e(money((int) \App\Core\Config::get('delivery.tip_cents', 500))) ?> de pourboire.</p>
                    <?php else: ?>
                        <p>Prouvez qu’il est à vous : une fois votre demande acceptée, vous conviendrez d’un rendez-vous pour le récupérer.</p>
                    <?php endif; ?>
                    <a class="btn btn-primary btn-block" href="/items/<?= e($item['id']) ?>/claim"><?= icon('shield') ?>C’est à moi : demander la restitution</a>
                <?php else: ?>
                    <p>Contactez <?= e($item['user_name']) ?> dans une conversation privée pour organiser la remise.</p>
                <?php endif; ?>
                <form method="post" action="/items/<?= e($item['id']) ?>/contact" class="contact-form">
                    <?= Csrf::field() ?>
                    <button class="btn <?= $isLost ? 'btn-primary' : 'btn-ghost' ?> btn-block" type="submit">
                        <?= icon('message') ?><?= $isLost ? 'Démarrer la conversation' : 'Poser une question' ?>
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="contact-box">
                <h2><?= $isLost ? 'Vous avez retrouvé cet objet ?' : 'C\'est votre objet ?' ?></h2>
                <p>Connectez-vous pour contacter <?= e($item['user_name']) ?> en toute confidentialité.</p>
                <div class="contact-actions">
                    <a class="btn btn-primary" href="/login">Se connecter</a>
                    <a class="btn btn-ghost" href="/register">Créer un compte</a>
                </div>
            </div>
        <?php endif; ?>

        <p class="privacy-note"><?= icon('shield') ?>Les coordonnées personnelles ne sont jamais affichées publiquement.</p>

        <?php if (Auth::check() && !$isOwner): ?>
            <?php /* Signalement à la modération. <details> : formulaire replié par défaut, sans JavaScript */ ?>
            <details class="report-box" id="signaler">
                <summary>Signaler cette annonce</summary>
                <form method="post" action="/items/<?= e($item['id']) ?>/report">
                    <?= Csrf::field() ?>
                    <div class="field">
                        <label for="report-reason">Motif</label>
                        <select id="report-reason" name="reason" required>
                            <option value="">Choisir…</option>
                            <?php foreach (\App\Models\Report::REASONS as $code => $label): ?>
                                <option value="<?= e($code) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="report-details">Précisions <span class="optional">(facultatif)</span></label>
                        <textarea id="report-details" name="details" rows="3" maxlength="500"></textarea>
                    </div>
                    <button type="submit" class="btn btn-ghost btn-sm">Envoyer le signalement</button>
                </form>
            </details>
        <?php endif; ?>
    </div>
</article>

<?php if ($isOwner && !$isLost): ?>
    <?php /* Objet trouvé : demandes de restitution reçues, à accepter ou refuser */ ?>
    <section class="claims" id="demandes" aria-labelledby="claims-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Demandes de restitution</p>
                <h2 id="claims-title"><?= $claims === [] ? 'Aucune demande pour l’instant' : count($claims) . ' demande' . (count($claims) > 1 ? 's' : '') ?></h2>
            </div>
        </div>

        <?php if ($claims === []): ?>
            <p class="matches-intro">
                Quand quelqu’un pensera que cet objet est le sien, sa demande apparaîtra ici avec sa preuve.
                Vous recevrez aussi un email.
            </p>
        <?php else: ?>
            <ul class="claim-list">
                <?php foreach ($claims as $claim): ?>
                    <li class="claim card">
                        <div class="claim-head">
                            <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($claim['claimant_name'], 0, 1))) ?></span>
                            <strong><?= e($claim['claimant_name']) ?></strong>
                            <span class="status status-claim-<?= e($claim['status']) ?>">
                                <?= ['pending' => 'En attente', 'approved' => 'Acceptée', 'rejected' => 'Refusée'][$claim['status']] ?>
                            </span>
                        </div>
                        <?php if ($claim['answer'] !== null): ?>
                            <p class="claim-answer"><span>Réponse à votre question :</span> <?= e($claim['answer']) ?></p>
                        <?php endif; ?>
                        <p class="claim-proof"><?= nl2br(e($claim['proof'])) ?></p>
                        <?php if ($claim['proof_photo'] !== null): ?>
                            <a class="claim-photo" href="/claims/<?= e($claim['id']) ?>/photo" target="_blank" rel="noopener">
                                <img src="/claims/<?= e($claim['id']) ?>/photo" alt="Photo de preuve envoyée par <?= e($claim['claimant_name']) ?>" loading="lazy">
                            </a>
                        <?php endif; ?>

                        <?php if ($claim['status'] === 'pending'): ?>
                            <div class="claim-actions">
                                <form method="post" action="/claims/<?= e($claim['id']) ?>/approve"
                                      data-confirm="Confirmez-vous que l’objet appartient à <?= e($claim['claimant_name']) ?> ?">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="btn btn-secondary btn-sm"><?= icon('check') ?>C’est bien son objet</button>
                                </form>
                                <form method="post" action="/claims/<?= e($claim['id']) ?>/reject">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="btn btn-danger btn-sm">Refuser</button>
                                </form>
                            </div>
                        <?php elseif ($claim['order_id'] !== null): ?>
                            <a class="btn btn-primary btn-sm" href="/orders/<?= e($claim['order_id']) ?>"><?= icon('package') ?>Voir la livraison</a>
                        <?php elseif ($claim['status'] === 'approved' && $deliveryEnabled): ?>
                            <p class="field-hint">En attente de la commande de livraison par <?= e($claim['claimant_name']) ?>.</p>
                        <?php elseif ($claim['status'] === 'approved'): ?>
                            <a class="btn btn-primary btn-sm" href="/conversations"><?= icon('message') ?>Organiser la remise</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($isOwner && $isOpen): ?>
    <section class="matches" id="correspondances" aria-labelledby="matches-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Correspondances</p>
                <h2 id="matches-title">
                    <?= $matches === []
                        ? 'Aucune correspondance pour l’instant'
                        : count($matches) . ' annonce' . (count($matches) > 1 ? 's' : '') . ' pourrai' . (count($matches) > 1 ? 'ent' : 't') . ' correspondre' ?>
                </h2>
            </div>
        </div>

        <?php if ($matches === []): ?>
            <div class="empty-state">
                <span class="empty-icon"><?= icon('sparkles') ?></span>
                <p>Chaque nouvelle annonce <?= $isLost ? '« trouvé »' : '« perdu »' ?> est comparée à la vôtre (catégorie, couleur, lieu, date, mots-clés). Revenez voir cette page de temps en temps.</p>
            </div>
        <?php else: ?>
            <p class="matches-intro">
                Classées de la plus probable à la moins probable. Ouvrez une annonce pour contacter son auteur
                <?= $isLost ? 'et vérifier qu’il s’agit bien de votre objet' : 'et vérifier qu’il s’agit bien de son objet' ?>.
            </p>
            <ul class="match-list">
                <?php foreach ($matches as $match): ?>
                    <?php require __DIR__ . '/_match.php'; ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php endif; ?>
