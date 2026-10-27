<?php
/**
 * Formulaire de connexion.
 *
 * Variables :
 *   $errors : ['form' => 'message'] erreur globale (identifiants faux, trop de tentatives)
 *   $old    : ['email' => ...] email déjà saisi
 */

use App\Core\Csrf;
?>
<section class="auth">
    <div class="auth-card card">
        <header class="auth-header">
            <span class="auth-icon"><?= icon('user') ?></span>
            <h1>Bon retour parmi nous</h1>
            <p>Connectez-vous pour gérer vos annonces et vos messages.</p>
        </header>

        <?php if (isset($errors['form'])): ?>
            <div class="alert alert-error" role="alert" id="form-error"><?= icon('shield') ?><span><?= e($errors['form']) ?></span></div>
        <?php endif; ?>

        <form method="post" action="/login" novalidate>
            <?= Csrf::field() ?>

            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required maxlength="190" autocomplete="email"
                       value="<?= e($old['email'] ?? '') ?>"<?= isset($errors['form']) ? ' aria-describedby="form-error"' : '' ?>
                       <?= isset($errors['form']) ? '' : 'autofocus' ?>>
            </div>

            <div class="field">
                <label for="password">Mot de passe</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required maxlength="72" autocomplete="current-password">
                    <button type="button" class="btn-toggle-password" data-target="password" aria-pressed="false">Afficher</button>
                </div>
                <p class="field-hint"><a href="/forgot-password">Mot de passe oublié&nbsp;?</a></p>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">Se connecter</button>
        </form>

        <p class="auth-switch">Pas encore de compte&nbsp;? <a href="/register">Créer un compte</a></p>
    </div>
</section>
