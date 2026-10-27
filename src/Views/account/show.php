<?php
/**
 * Page « Mon compte ».
 *
 * Variables :
 *   $user           : utilisateur connecté (Auth::user())
 *   $passwordErrors : erreurs du formulaire de changement de mot de passe
 *   $deleteErrors   : erreurs du formulaire de suppression du compte
 *   $blocking       : raison empêchant la suppression (livraison en cours), ou null
 *
 * Quatre blocs indépendants, chacun avec son propre formulaire POST + CSRF.
 * La confirmation avant suppression passe par data-confirm (lu par app.js).
 */

use App\Core\Csrf;
?>
<section class="form-page">
    <header class="form-header">
        <p class="eyebrow">Votre espace</p>
        <h1>Mon compte</h1>
        <p>Membre depuis le <?= e(date('d/m/Y', strtotime($user['created_at']))) ?>.</p>
    </header>

    <?php /* 1. Informations */ ?>
    <div class="card form-card">
        <h2 class="section-title"><?= icon('user') ?>Informations</h2>
        <dl class="detail-info">
            <div><dt>Nom affiché</dt><dd><?= e($user['name']) ?></dd></div>
            <div><dt>Email</dt><dd><?= e($user['email']) ?></dd></div>
            <div>
                <dt>Email vérifié</dt>
                <dd><?= $user['email_verified_at'] !== null ? 'Oui' : 'Non, <a href="/verify-email">saisir mon code</a>' ?></dd>
            </div>
        </dl>
    </div>

    <?php /* 2. Changement de mot de passe */ ?>
    <form class="card form-card" method="post" action="/account/password" novalidate>
        <?= Csrf::field() ?>
        <h2 class="section-title"><?= icon('shield') ?>Changer de mot de passe</h2>

        <div class="field">
            <label for="current_password">Mot de passe actuel</label>
            <input type="password" id="current_password" name="current_password" required maxlength="72"
                   autocomplete="current-password"<?= field_attrs($passwordErrors, 'current_password') ?>>
            <?= field_error($passwordErrors, 'current_password') ?>
        </div>

        <div class="field">
            <label for="password">Nouveau mot de passe</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" required minlength="8" maxlength="72"
                       autocomplete="new-password" data-strength="password-strength"<?= field_attrs($passwordErrors, 'password', 'password-hint') ?>>
                <button type="button" class="btn-toggle-password" data-target="password" aria-pressed="false">Afficher</button>
            </div>
            <div class="strength" id="password-strength" hidden>
                <span class="strength-bar"><span></span></span>
                <span class="strength-label" aria-live="polite"></span>
            </div>
            <p class="field-hint" id="password-hint">8 caractères minimum, avec au moins une lettre et un chiffre. Vos autres appareils seront déconnectés.</p>
            <?= field_error($passwordErrors, 'password') ?>
        </div>

        <div class="field">
            <label for="password_confirm">Confirmer le nouveau mot de passe</label>
            <input type="password" id="password_confirm" name="password_confirm" required maxlength="72"
                   autocomplete="new-password"<?= field_attrs($passwordErrors, 'password_confirm') ?>>
            <?= field_error($passwordErrors, 'password_confirm') ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Enregistrer le mot de passe</button>
        </div>
    </form>

    <?php /* 3. Emails de correspondance */ ?>
    <form class="card form-card" method="post" action="/account/notifications">
        <?= Csrf::field() ?>
        <h2 class="section-title"><?= icon('message') ?>Alertes par email</h2>
        <?php /* Champ caché à 0 : une case décochée n'est pas envoyée par le navigateur */ ?>
        <input type="hidden" name="notify_matches" value="0">
        <div class="field checkbox-field">
            <input type="checkbox" id="notify_matches" name="notify_matches" value="1"<?= (int) $user['notify_matches'] === 1 ? ' checked' : '' ?>>
            <label for="notify_matches">Me prévenir par email quand une nouvelle annonce correspond à l’une des miennes.</label>
        </div>
        <p class="field-hint">Les emails liés à vos demandes de restitution et livraisons sont toujours envoyés.</p>
        <div class="form-actions">
            <button type="submit" class="btn btn-secondary">Enregistrer</button>
        </div>
    </form>

    <?php /* 4. Données personnelles (RGPD) */ ?>
    <div class="card form-card">
        <h2 class="section-title"><?= icon('package') ?>Mes données</h2>
        <p>Téléchargez une copie de toutes les informations liées à votre compte (annonces, demandes, messages envoyés, livraisons), au format JSON.</p>
        <div class="form-actions">
            <a class="btn btn-ghost" href="/account/export">Télécharger mes données</a>
        </div>
    </div>

    <?php /* 5. Suppression du compte */ ?>
    <form class="card form-card danger-zone" method="post" action="/account/delete" novalidate
          data-confirm="Supprimer définitivement votre compte ? Cette action est irréversible.">
        <?= Csrf::field() ?>
        <h2 class="section-title"><?= icon('trash') ?>Supprimer mon compte</h2>
        <p>Vos annonces, photos, conversations, demandes et messages seront <strong>définitivement effacés</strong>. Cette action est irréversible.</p>

        <?php if ($blocking !== null): ?>
            <div class="alert alert-info" role="status"><?= icon('shield') ?><span><?= e($blocking) ?></span></div>
        <?php else: ?>
            <div class="field">
                <label for="delete_password">Votre mot de passe</label>
                <input type="password" id="delete_password" name="delete_password" required maxlength="72"
                       autocomplete="current-password"<?= field_attrs($deleteErrors, 'delete_password') ?>>
                <?= field_error($deleteErrors, 'delete_password') ?>
            </div>
            <div class="field checkbox-field">
                <input type="checkbox" id="confirm_delete" name="confirm_delete" value="1"<?= field_attrs($deleteErrors, 'confirm_delete') ?>>
                <label for="confirm_delete">Je comprends que mon compte et mes données seront supprimés définitivement.</label>
            </div>
            <?= field_error($deleteErrors, 'confirm_delete') ?>
            <div class="form-actions">
                <button type="submit" class="btn btn-danger">Supprimer définitivement mon compte</button>
            </div>
        <?php endif; ?>
    </form>
</section>
