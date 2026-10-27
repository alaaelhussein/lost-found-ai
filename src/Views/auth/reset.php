<?php
/**
 * Choix d'un nouveau mot de passe (lien reçu par email).
 * Variables : $valid (jeton valide), $token, $errors
 *
 * Le jeton est renvoyé dans un champ caché : il n'apparaît dans l'URL qu'à
 * l'ouverture du lien (Referrer-Policy: same-origin empêche de le transmettre
 * à un site externe).
 */

use App\Core\Csrf;
?>
<section class="auth">
    <div class="auth-card card">
        <header class="auth-header">
            <span class="auth-icon"><?= icon('shield') ?></span>
            <?php if (!$valid): ?>
                <h1>Lien expiré ou déjà utilisé</h1>
                <p>Les liens de réinitialisation sont valables 1 heure et ne servent qu’une fois. Demandez-en un nouveau.</p>
            <?php else: ?>
                <h1>Nouveau mot de passe</h1>
                <p>Choisissez un nouveau mot de passe. Vous serez déconnecté de tous vos appareils.</p>
            <?php endif; ?>
        </header>

        <?php if ($valid): ?>
            <form method="post" action="/reset-password" novalidate>
                <?= Csrf::field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="field">
                    <label for="password">Nouveau mot de passe</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required minlength="8" maxlength="72" autofocus
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

                <button type="submit" class="btn btn-primary btn-block btn-lg">Enregistrer</button>
            </form>
        <?php else: ?>
            <a class="btn btn-primary btn-block" href="/forgot-password">Demander un nouveau lien</a>
        <?php endif; ?>

        <p class="auth-switch"><a href="/login">Retour à la connexion</a></p>
    </div>
</section>
