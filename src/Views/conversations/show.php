<?php
/**
 * Conversation privée entre l'auteur d'une annonce et la personne qui l'a contacté.
 */

use App\Core\Auth;
use App\Core\Csrf;
use App\Controllers\ConversationController;

$me = (int) Auth::id();
?>
<div class="chat">
    <header class="chat-header">
        <a class="back-link" href="/conversations"><?= icon('arrow-left') ?>Messages</a>
        <div class="chat-title">
            <span class="avatar avatar-lg" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $conversation['other_user_name'], 0, 1))) ?></span>
            <div>
                <h1><?= e($conversation['other_user_name']) ?></h1>
                <a href="/items/<?= e($conversation['lost_item_id']) ?>">À propos de : <?= e($conversation['lost_title']) ?></a>
            </div>
        </div>
    </header>

    <p class="privacy-note"><?= icon('shield') ?>Les échanges restent dans Lost&amp;Found AI. Pour la remise, préférez un lieu public et ne partagez pas de données sensibles.</p>

    <section class="messages" id="messages" aria-label="Messages" tabindex="0">
        <?php if ($messages === []): ?>
            <p class="empty-messages">Commencez la conversation en décrivant l'objet ou en proposant un lieu et un moment pour l'échange.</p>
        <?php else: ?>
            <?php foreach ($messages as $message): ?>
                <?php $own = (int) $message['sender_id'] === $me; ?>
                <article class="message<?= $own ? ' message-own' : '' ?>">
                    <p class="message-author"><?= $own ? 'Vous' : e($message['sender_name']) ?></p>
                    <p class="message-text"><?= nl2br(e($message['content'])) ?></p>
                    <time datetime="<?= e(date('c', strtotime($message['created_at']))) ?>"><?= e(date('d/m/Y à H:i', strtotime($message['created_at']))) ?></time>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <form class="message-form" method="post" action="/conversations/<?= e($conversation['id']) ?>/messages">
        <?= Csrf::field() ?>
        <label for="content" class="visually-hidden">Votre message</label>
        <textarea id="content" name="content" rows="2" maxlength="<?= ConversationController::MESSAGE_MAX ?>" required
                  data-submit-shortcut aria-describedby="content-hint"
                  placeholder="Écrivez votre message…"></textarea>
        <button class="btn btn-primary" type="submit"><?= icon('send') ?><span>Envoyer</span></button>
        <p class="field-hint" id="content-hint">Ctrl + Entrée pour envoyer.</p>
    </form>
</div>
