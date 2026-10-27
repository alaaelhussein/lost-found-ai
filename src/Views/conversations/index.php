<?php
/**
 * Liste des conversations privées de l'utilisateur.
 */
?>
<section class="page-intro">
    <p class="eyebrow">Contact sécurisé</p>
    <h1>Messages</h1>
    <p>Retrouvez ici les échanges liés à vos annonces. Votre email n'est jamais partagé.</p>
</section>

<?php if ($conversations === []): ?>
    <section class="empty-state">
        <span class="empty-icon"><?= icon('message') ?></span>
        <h2>Aucune conversation</h2>
        <p>Quand vous contacterez l'auteur d'une annonce, ou qu'on vous contactera, l'échange apparaîtra ici.</p>
        <a class="btn btn-primary" href="/items">Voir les annonces</a>
    </section>
<?php else: ?>
    <ul class="conversation-list">
        <?php foreach ($conversations as $conversation): ?>
            <?php
            // Contact direct : les deux titres désignent la même annonce
            $subject = $conversation['lost_title'] === $conversation['found_title']
                ? $conversation['lost_title']
                : $conversation['lost_title'] . ' · ' . $conversation['found_title'];
            ?>
            <li>
                <a class="conversation-row" href="/conversations/<?= e($conversation['id']) ?>">
                    <span class="avatar avatar-lg" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $conversation['other_user_name'], 0, 1))) ?></span>
                    <span class="conversation-text">
                        <strong><?= e($conversation['other_user_name']) ?></strong>
                        <span><?= e($subject) ?></span>
                    </span>
                    <time datetime="<?= e(date('c', strtotime($conversation['created_at']))) ?>"><?= e(date('d/m/Y', strtotime($conversation['created_at']))) ?></time>
                    <?= icon('arrow-right') ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
