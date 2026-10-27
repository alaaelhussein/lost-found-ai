<?php
/**
 * Formulaire d'inscription.
 *
 * Variables :
 *   $errors : ['champ' => 'message'] (vide au premier affichage)
 *   $old    : valeurs déjà saisies (nom, email, case CGU), jamais le mot de passe
 *
 * Les attributs HTML (required, minlength, type="email") donnent un retour
 * immédiat dans le navigateur, mais ne remplacent PAS la validation PHP :
 * ils se contournent facilement.
 */

use App\Core\Csrf;
?>
<section class="auth">
    <div class="auth-card card">
        <header class="auth-header">
            <span class="auth-icon"><?= icon('shield') ?></span>
            <h1>Créer un compte</h1>
            <p>Gratuit. Votre email ne sera jamais visible des autres utilisateurs.</p>
        </header>

        <form method="post" action="/register" novalidate>
            <?= Csrf::field() ?>

            <div class="field">
                <label for="name">Nom ou pseudo</label>
                <input type="text" id="name" name="name" required minlength="2" maxlength="100"
                       autocomplete="nickname" value="<?= e($old['name'] ?? '') ?>"<?= field_attrs($errors, 'name', 'name-hint') ?>>
                <p class="field-hint" id="name-hint">Affiché publiquement sur vos annonces.</p>
                <?= field_error($errors, 'name') ?>
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required maxlength="190"
                       autocomplete="email" value="<?= e($old['email'] ?? '') ?>"<?= field_attrs($errors, 'email') ?>>
                <?= field_error($errors, 'email') ?>
            </div>

            <div class="field">
                <label for="password">Mot de passe</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required minlength="8" maxlength="72"
                           autocomplete="new-password" data-strength="password-strength"<?= field_attrs($errors, 'password', 'password-hint') ?>>
                    <button type="button" class="btn-toggle-password" data-target="password" aria-pressed="false">Afficher</button>
                </div>
                <div class="strength" id="password-strength" hidden>
                    <span class="strength-bar"><span></span></span>
                    <span class="strength-label" aria-live="polite"></span>
                </div>
                <p class="field-hint" id="password-hint">8 caractères minimum, avec au moins une lettre et un chiffre.</p>
                <?= field_error($errors, 'password') ?>
            </div>

            <div class="field">
                <label for="password_confirm">Confirmer le mot de passe</label>
                <input type="password" id="password_confirm" name="password_confirm" required maxlength="72"
                       autocomplete="new-password"<?= field_attrs($errors, 'password_confirm') ?>>
                <?= field_error($errors, 'password_confirm') ?>
            </div>

            <?php /* Case obligatoire : la date d'acceptation est enregistrée (users.terms_accepted_at) */ ?>
            <div class="field checkbox-field">
                <input type="checkbox" id="terms" name="terms" value="1"<?= ($old['terms'] ?? '') === '1' ? ' checked' : '' ?><?= field_attrs($errors, 'terms') ?>>
                <label for="terms">
                    J’accepte les <a href="/cgu" target="_blank" rel="noopener">conditions d’utilisation</a>
                    et j’ai lu la <a href="/confidentialite" target="_blank" rel="noopener">politique de confidentialité</a>.
                </label>
            </div>
            <?= field_error($errors, 'terms') ?>

            <button type="submit" class="btn btn-primary btn-block btn-lg">Créer mon compte</button>
        </form>

        <p class="auth-switch">Déjà inscrit&nbsp;? <a href="/login">Se connecter</a></p>
    </div>
</section>
