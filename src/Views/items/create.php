<?php
/**
 * Formulaire de création d'une annonce.
 *
 * Chaque champ est relié à son message d'erreur par field_attrs() /
 * field_error() (src/helpers.php) : les lecteurs d'écran annoncent l'erreur
 * quand le champ reçoit le focus.
 */

use App\Core\Csrf;

$isLost = $type === 'lost';
?>
<section class="form-page">
    <header class="form-header">
        <p class="eyebrow">Nouvelle annonce</p>
        <h1><?= $isLost ? 'Déclarer un objet perdu' : 'Déclarer un objet trouvé' ?></h1>
        <p>Plus la description est précise, meilleure sera la correspondance. Votre email reste privé.</p>
    </header>

    <nav class="segmented" aria-label="Type d'annonce">
        <a href="/items/create?type=lost" class="segmented-option<?= $isLost ? ' is-active' : '' ?>"<?= $isLost ? ' aria-current="page"' : '' ?>>
            <?= icon('search') ?>J'ai perdu
        </a>
        <a href="/items/create?type=found" class="segmented-option<?= !$isLost ? ' is-active' : '' ?>"<?= !$isLost ? ' aria-current="page"' : '' ?>>
            <?= icon('package') ?>J'ai trouvé
        </a>
    </nav>

    <?php if ($errors !== []): ?>
        <div class="alert alert-error" role="alert">
            <?= icon('shield') ?><span>Certains champs sont à corriger. Les erreurs sont indiquées sous chaque champ.</span>
        </div>
    <?php endif; ?>

    <form class="card form-card" method="post" action="/items/create" enctype="multipart/form-data" novalidate>
        <?= Csrf::field() ?>
        <input type="hidden" name="type" value="<?= e($type) ?>">

        <fieldset>
            <legend>L'objet</legend>

            <div class="field">
                <label for="title">Titre <span class="required" aria-hidden="true">*</span></label>
                <input type="text" id="title" name="title" required maxlength="150"
                       placeholder="<?= $isLost ? 'Ex. Sac à dos noir Eastpak' : 'Ex. Trousseau de clés avec porte-clé rouge' ?>"
                       value="<?= e($old['title'] ?? '') ?>"<?= field_attrs($errors, 'title') ?>>
                <?= field_error($errors, 'title') ?>
            </div>

            <div class="field">
                <label for="description">Description <span class="required" aria-hidden="true">*</span></label>
                <textarea id="description" name="description" required rows="5" maxlength="2000"
                          data-counter="description-count"
                          <?= field_attrs($errors, 'description', 'description-hint') ?>><?= e($old['description'] ?? '') ?></textarea>
                <div class="field-row">
                    <p class="field-hint" id="description-hint">Marque, signes distinctifs, contenu… Gardez un détail secret pour vérifier le propriétaire.</p>
                    <span class="char-count" id="description-count" aria-live="polite"></span>
                </div>
                <?= field_error($errors, 'description') ?>
            </div>

            <div class="field-grid">
                <div class="field">
                    <label for="category">Catégorie <span class="required" aria-hidden="true">*</span></label>
                    <input type="text" id="category" name="category" required maxlength="50"
                           list="category-suggestions" placeholder="Ex. sac, téléphone, clés"
                           value="<?= e($old['category'] ?? '') ?>"<?= field_attrs($errors, 'category') ?>>
                    <datalist id="category-suggestions">
                        <option value="Sac"><option value="Téléphone"><option value="Clés">
                        <option value="Portefeuille"><option value="Papiers d'identité"><option value="Lunettes">
                        <option value="Vêtement"><option value="Bijou"><option value="Électronique"><option value="Animal">
                    </datalist>
                    <?= field_error($errors, 'category') ?>
                </div>

                <div class="field">
                    <label for="color">Couleur <span class="optional">(facultatif)</span></label>
                    <input type="text" id="color" name="color" maxlength="30" placeholder="Ex. noir"
                           value="<?= e($old['color'] ?? '') ?>"<?= field_attrs($errors, 'color') ?>>
                    <?= field_error($errors, 'color') ?>
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Où et quand&nbsp;?</legend>

            <div class="field-grid">
                <div class="field">
                    <label for="location_label">Lieu <span class="required" aria-hidden="true">*</span></label>
                    <input type="text" id="location_label" name="location_label" required maxlength="150"
                           placeholder="Ex. bibliothèque universitaire"
                           value="<?= e($old['location_label'] ?? '') ?>"<?= field_attrs($errors, 'location_label') ?>>
                    <?= field_error($errors, 'location_label') ?>
                </div>

                <div class="field">
                    <label for="date_event"><?= $isLost ? 'Date de la perte' : 'Date de la découverte' ?> <span class="required" aria-hidden="true">*</span></label>
                    <input type="date" id="date_event" name="date_event" required max="<?= e(date('Y-m-d')) ?>"
                           value="<?= e($old['date_event'] ?? '') ?>"<?= field_attrs($errors, 'date_event') ?>>
                    <?= field_error($errors, 'date_event') ?>
                </div>
            </div>
        </fieldset>

        <?php if (!$isLost): ?>
            <?php /* Objet trouvé : question posée à toute personne qui le réclame */ ?>
            <fieldset>
                <legend>Vérification du propriétaire <span class="optional">(recommandé)</span></legend>
                <div class="field">
                    <label for="verification_question">Question que seul le propriétaire saura résoudre</label>
                    <input type="text" id="verification_question" name="verification_question" maxlength="255"
                           placeholder="Ex. Quel porte-clé est accroché dessus ? Quelle est la couleur de la doublure ?"
                           value="<?= e($old['verification_question'] ?? '') ?>"<?= field_attrs($errors, 'verification_question', 'question-hint') ?>>
                    <p class="field-hint" id="question-hint">Elle n’apparaît pas sur l’annonce : elle est posée uniquement aux personnes qui réclament l’objet. Ne mettez pas la réponse dans la description !</p>
                    <?= field_error($errors, 'verification_question') ?>
                </div>
            </fieldset>
        <?php endif; ?>

        <fieldset>
            <legend>Photo <span class="optional">(facultatif)</span></legend>

            <div class="field">
                <label for="photo" class="dropzone">
                    <img class="dropzone-preview" alt="" hidden>
                    <span class="dropzone-empty">
                        <?= icon('image', 'icon icon-lg') ?>
                        <strong>Choisir une photo</strong>
                        <span>JPG, PNG ou WebP · 3 Mo maximum</span>
                    </span>
                </label>
                <input type="file" id="photo" name="photo" class="dropzone-input"
                       accept="image/jpeg,image/png,image/webp"<?= field_attrs($errors, 'photo', 'photo-hint') ?>>
                <p class="field-hint" id="photo-hint">
                    <?= extension_loaded('gd')
                        ? 'Les données de localisation (GPS) contenues dans la photo sont supprimées à l’envoi.'
                        : 'Évitez les photos prises chez vous : elles peuvent contenir votre position GPS.' ?>
                </p>
                <?= field_error($errors, 'photo') ?>
            </div>
        </fieldset>

        <div class="form-actions">
            <a class="btn btn-ghost" href="/my-items">Annuler</a>
            <button type="submit" class="btn btn-primary btn-lg">Publier l'annonce</button>
        </div>
    </form>
</section>
