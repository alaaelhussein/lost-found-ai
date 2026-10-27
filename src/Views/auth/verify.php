<?php
/**
 * Saisie du code de vérification envoyé par email.
 * Variables : $email, $errors (['code' => message]), $resendWait (secondes avant renvoi possible)
 *
 * Le champ utilise autocomplete="one-time-code" : sur téléphone, le clavier
 * propose directement le code reçu par email/SMS.
 */

use App\Core\Csrf;
?>
<section class="auth">
    <div class="auth-card card">
        <header class="auth-header">
            <span class="auth-icon"><?= icon('shield') ?></span>
            <h1>Vérifiez votre email</h1>
            <p>Nous avons envoyé un code à 6 chiffres à <strong><?= e($email) ?></strong>.</p>
        </header>

        <form method="post" action="/verify-email" novalidate>
            <?= Csrf::field() ?>
            <div class="field">
                <label for="code">Code de vérification</label>
                <input type="text" id="code" name="code" class="code-input" required
                       inputmode="numeric" autocomplete="one-time-code" maxlength="7"
                       pattern="[0-9 ]*" placeholder="123456" autofocus<?= field_attrs($errors, 'code', 'code-hint') ?>>
                <p class="field-hint" id="code-hint">Valable 15 minutes. Pensez à regarder dans les spams.</p>
                <?= field_error($errors, 'code') ?>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Valider</button>
        </form>

        <form method="post" action="/verify-email/resend" class="resend-form">
            <?= Csrf::field() ?>
            <p class="auth-switch">
                Pas reçu ?
                <?php if ($resendWait > 0): ?>
                    <span>Nouveau code possible dans <?= (int) $resendWait ?> s.</span>
                <?php else: ?>
                    <button type="submit" class="btn-link-inline">Renvoyer un code</button>
                <?php endif; ?>
            </p>
        </form>
    </div>
</section>
