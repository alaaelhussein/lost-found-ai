<?php
/**
 * Formulaire « mot de passe oublié » : saisie de l'email.
 * Variables : $errors (['email' => message]), $old (['email' => ...])
 */

use App\Core\Csrf;
?>
<section class="auth">
    <div class="auth-card card">
        <header class="auth-header">
            <span class="auth-icon"><?= icon('shield') ?></span>
            <h1>Mot de passe oublié</h1>
            <p>Indiquez l’email de votre compte : nous vous enverrons un lien pour choisir un nouveau mot de passe.</p>
        </header>

        <form method="post" action="/forgot-password" novalidate>
            <?= Csrf::field() ?>
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required maxlength="190" autocomplete="email" autofocus
                       value="<?= e($old['email'] ?? '') ?>"<?= field_attrs($errors, 'email') ?>>
                <?= field_error($errors, 'email') ?>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Recevoir le lien</button>
        </form>

        <p class="auth-switch"><a href="/login">Retour à la connexion</a></p>
    </div>
</section>
