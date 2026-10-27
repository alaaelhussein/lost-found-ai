<?php
/**
 * Formulaire de demande de restitution : prouver qu'un objet trouvé est à soi.
 * Variables : $item, $question (ou null), $errors, $old
 *
 * Conseil affiché à l'utilisateur : donner des détails qui NE SONT PAS dans
 * l'annonce publique (contenu d'un sac, rayure, fond d'écran, porte-clé...).
 * Seule la personne qui a l'objet en main peut les vérifier.
 */

use App\Core\Csrf;
?>
<a class="back-link" href="/items/<?= e($item['id']) ?>"><?= icon('arrow-left') ?>Retour à l’annonce</a>

<section class="form-page">
    <header class="form-header">
        <p class="eyebrow">Demande de restitution</p>
        <h1>« <?= e($item['title']) ?> » est à moi</h1>
        <p>Prouvez que l’objet vous appartient. <?= e($item['user_name']) ?> comparera vos réponses avec l’objet qu’il a en main.</p>
    </header>

    <ol class="steps-inline" aria-label="Étapes">
        <li class="is-current"><span>1</span>Preuve</li>
        <li><span>2</span>Validation</li>
        <li><span>3</span><?= \App\Services\ShippingService::isEnabled() ? 'Livraison' : 'Remise' ?></li>
    </ol>

    <?php if ($errors !== []): ?>
        <div class="alert alert-error" role="alert"><?= icon('shield') ?><span>Certains champs sont à corriger.</span></div>
    <?php endif; ?>

    <form class="card form-card" method="post" action="/items/<?= e($item['id']) ?>/claim" enctype="multipart/form-data" novalidate>
        <?= Csrf::field() ?>

        <?php if ($question !== null): ?>
            <fieldset>
                <legend>Question de vérification</legend>
                <div class="field">
                    <label for="answer"><?= e($question) ?> <span class="required" aria-hidden="true">*</span></label>
                    <input type="text" id="answer" name="answer" required maxlength="255"
                           value="<?= e($old['answer'] ?? '') ?>"<?= field_attrs($errors, 'answer', 'answer-hint') ?>>
                    <p class="field-hint" id="answer-hint">Question posée par la personne qui a trouvé l’objet.</p>
                    <?= field_error($errors, 'answer') ?>
                </div>
            </fieldset>
        <?php endif; ?>

        <fieldset>
            <legend>Votre preuve</legend>
            <div class="field">
                <label for="proof">Décrivez un détail que seul le propriétaire connaît <span class="required" aria-hidden="true">*</span></label>
                <textarea id="proof" name="proof" rows="5" required minlength="20" maxlength="1000"
                          data-counter="proof-count"
                          placeholder="Ex. Il y a une rayure sur le coin gauche, un ticket de cinéma dans la poche avant, mon prénom écrit à l’intérieur…"
                          <?= field_attrs($errors, 'proof', 'proof-hint') ?>><?= e($old['proof'] ?? '') ?></textarea>
                <div class="field-row">
                    <p class="field-hint" id="proof-hint">Ne répétez pas l’annonce : donnez des détails invisibles sur les photos.</p>
                    <span class="char-count" id="proof-count" aria-live="polite"></span>
                </div>
                <?= field_error($errors, 'proof') ?>
            </div>

            <div class="field">
                <label for="photo" class="dropzone">
                    <img class="dropzone-preview" alt="" hidden>
                    <span class="dropzone-empty">
                        <?= icon('image', 'icon icon-lg') ?>
                        <strong>Ajouter une photo de preuve (facultatif)</strong>
                        <span>Facture, ancienne photo de vous avec l’objet… · 3 Mo max.</span>
                    </span>
                </label>
                <input type="file" id="photo" name="photo" class="dropzone-input"
                       accept="image/jpeg,image/png,image/webp"<?= field_attrs($errors, 'photo', 'photo-hint') ?>>
                <p class="field-hint" id="photo-hint"><?= icon('shield') ?> Photo privée : seule la personne qui a trouvé l’objet pourra la voir.</p>
                <?= field_error($errors, 'photo') ?>
            </div>
        </fieldset>

        <div class="field checkbox-field">
            <input type="checkbox" id="certify" name="certify" value="1"<?= ($old['certify'] ?? '') === '1' ? ' checked' : '' ?><?= field_attrs($errors, 'certify') ?>>
            <label for="certify">Je certifie sur l’honneur que cet objet m’appartient. Une fausse déclaration peut constituer une escroquerie.</label>
        </div>
        <?= field_error($errors, 'certify') ?>

        <div class="form-actions">
            <a class="btn btn-ghost" href="/items/<?= e($item['id']) ?>">Annuler</a>
            <button type="submit" class="btn btn-primary btn-lg">Envoyer ma demande</button>
        </div>
    </form>
</section>
