<?php
/**
 * Choix de la livraison d'un objet dont la restitution a été acceptée.
 *
 * Variables : $claim, $mode, $zip, $modes, $quotes (offres du mode choisi),
 *             $relayPoints, $tipCents, $isSimulated, $errors, $old
 *
 * Deux formulaires, pour fonctionner même sans JavaScript :
 *   1. GET  : mode de livraison + code postal => recharge la page avec les
 *             offres et les points relais correspondants ;
 *   2. POST : transporteur, point relais (ou adresse), nom => commande.
 * Avec JavaScript, changer de mode recharge la page automatiquement
 * (attribut data-autosubmit, voir app.js).
 */

use App\Core\Csrf;

$needsPoint = $mode === 'relay' || $mode === 'locker';
$selectedCarrier = (string) ($old['carrier'] ?? array_key_first($quotes));
$selectedPoint = (string) ($old['relay_point'] ?? '');
?>
<a class="back-link" href="/items/<?= e($claim['item_id']) ?>"><?= icon('arrow-left') ?>Retour à l’annonce</a>

<section class="form-page">
    <header class="form-header">
        <p class="eyebrow">Livraison</p>
        <h1>Recevoir « <?= e($claim['item_title']) ?> »</h1>
        <p><?= e($claim['finder_name']) ?> a confirmé que l’objet est à vous. Vous payez la livraison et un pourboire de <?= e(money($tipCents)) ?> pour le remercier.</p>
    </header>

    <ol class="steps-inline" aria-label="Étapes">
        <li class="is-done"><span><?= icon('check') ?></span>Preuve</li>
        <li class="is-done"><span><?= icon('check') ?></span>Validation</li>
        <li class="is-current"><span>3</span>Livraison</li>
    </ol>

    <?php if ($errors !== []): ?>
        <div class="alert alert-error" role="alert"><?= icon('shield') ?><span>Certains champs sont à corriger.</span></div>
    <?php endif; ?>

    <!-- Étape A : mode de livraison et code postal (GET : recharge les offres) -->
    <form class="card form-card" method="get" action="/claims/<?= e($claim['id']) ?>/order">
        <fieldset>
            <legend>1. Où voulez-vous le recevoir ?</legend>
            <div class="option-grid">
                <?php foreach ($modes as $code => $info): ?>
                    <label class="option-card<?= $code === $mode ? ' is-selected' : '' ?>">
                        <input type="radio" name="mode" value="<?= e($code) ?>"<?= $code === $mode ? ' checked' : '' ?> data-autosubmit>
                        <span class="option-title"><?= icon($code === 'home' ? 'user' : ($code === 'locker' ? 'package' : 'map-pin')) ?><?= e($info['label']) ?></span>
                        <span class="option-hint"><?= e($info['hint']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="field zip-field">
                <label for="zip">Code postal <?= $needsPoint ? 'pour trouver un point proche' : 'de livraison' ?></label>
                <div class="inline-input">
                    <input type="text" id="zip" name="zip" inputmode="numeric" maxlength="5" pattern="[0-9]{5}"
                           autocomplete="postal-code" placeholder="75001" value="<?= e($zip) ?>"<?= field_attrs($errors, 'zip') ?>>
                    <button type="submit" class="btn btn-secondary"><?= icon('search') ?><?= $needsPoint ? 'Chercher' : 'Valider' ?></button>
                </div>
                <?= field_error($errors, 'zip') ?>
            </div>
        </fieldset>
    </form>

    <!-- Étape B : transporteur, point de retrait / adresse, confirmation (POST) -->
    <form class="card form-card" method="post" action="/claims/<?= e($claim['id']) ?>/order" novalidate>
        <?= Csrf::field() ?>
        <input type="hidden" name="mode" value="<?= e($mode) ?>">
        <input type="hidden" name="zip" value="<?= e($zip) ?>">

        <fieldset>
            <legend>2. Transporteur</legend>
            <div class="carrier-list">
                <?php foreach ($quotes as $code => $quote): ?>
                    <label class="carrier-option">
                        <input type="radio" name="carrier" value="<?= e($code) ?>"<?= $code === $selectedCarrier ? ' checked' : '' ?>>
                        <span class="carrier-name"><strong><?= e($quote['carrier']) ?></strong> <?= e($quote['service']) ?></span>
                        <span class="carrier-delay"><?= e($quote['delay']) ?></span>
                        <span class="carrier-price">
                            <?= e(money($quote['cents'])) ?>
                            <small>+ <?= e(money($tipCents)) ?> = <strong><?= e(money($quote['cents'] + $tipCents)) ?></strong></small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?= field_error($errors, 'carrier') ?>
            <?php if ($isSimulated): ?>
                <p class="field-hint">Tarifs indicatifs (mode démonstration). En production, les prix réels sont fournis par le transporteur.</p>
            <?php endif; ?>
        </fieldset>

        <fieldset>
            <legend>3. <?= $needsPoint ? 'Point de retrait' : 'Adresse de livraison' ?></legend>

            <?php if ($needsPoint): ?>
                <?php if ($relayPoints === []): ?>
                    <p class="field-hint">Saisissez votre code postal ci-dessus puis cliquez sur « Chercher ».</p>
                <?php else: ?>
                    <div class="carrier-list">
                        <?php foreach ($relayPoints as $id => $label): ?>
                            <label class="carrier-option">
                                <input type="radio" name="relay_point" value="<?= e($id) ?>"<?= $id === $selectedPoint ? ' checked' : '' ?>>
                                <span class="carrier-name"><?= icon('map-pin') ?><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?= field_error($errors, 'relay_point') ?>
                <p class="privacy-note"><?= icon('shield') ?>Aucune adresse personnelle : seul ce point de retrait et votre prénom figurent sur l’étiquette.</p>
            <?php else: ?>
                <div class="field">
                    <label for="street">Adresse</label>
                    <input type="text" id="street" name="street" maxlength="200" autocomplete="street-address"
                           value="<?= e($old['street'] ?? '') ?>"<?= field_attrs($errors, 'street') ?>>
                    <?= field_error($errors, 'street') ?>
                </div>
                <div class="field">
                    <label for="city">Ville</label>
                    <input type="text" id="city" name="city" maxlength="100" autocomplete="address-level2"
                           value="<?= e($old['city'] ?? '') ?>"<?= field_attrs($errors, 'city') ?>>
                    <?= field_error($errors, 'city') ?>
                </div>
                <p class="alert alert-info"><?= icon('shield') ?><span>Votre adresse est chiffrée sur nos serveurs et effacée après la livraison. Elle sera toutefois imprimée sur l’étiquette, donc visible par la personne qui expédie. Pour rester anonyme, choisissez un point relais.</span></p>
            <?php endif; ?>

            <div class="field">
                <label for="recipient_name"><?= $needsPoint ? 'Prénom (pour retirer le colis)' : 'Nom et prénom' ?></label>
                <input type="text" id="recipient_name" name="recipient_name" maxlength="100"
                       autocomplete="<?= $needsPoint ? 'given-name' : 'name' ?>"
                       value="<?= e($old['recipient_name'] ?? '') ?>"<?= field_attrs($errors, 'recipient_name') ?>>
                <?= field_error($errors, 'recipient_name') ?>
            </div>
        </fieldset>

        <div class="field checkbox-field">
            <input type="checkbox" id="accept" name="accept" value="1"<?= ($old['accept'] ?? '') === '1' ? ' checked' : '' ?><?= field_attrs($errors, 'accept') ?>>
            <label for="accept">Je comprends que je paie les frais de livraison et un pourboire de <?= e(money($tipCents)) ?> reversé à <?= e($claim['finder_name']) ?> une fois l’objet reçu.</label>
        </div>
        <?= field_error($errors, 'accept') ?>

        <div class="form-actions">
            <a class="btn btn-ghost" href="/items/<?= e($claim['item_id']) ?>">Annuler</a>
            <button type="submit" class="btn btn-primary btn-lg"<?= $quotes === [] ? ' disabled' : '' ?>>Continuer vers le paiement</button>
        </div>
    </form>
</section>
