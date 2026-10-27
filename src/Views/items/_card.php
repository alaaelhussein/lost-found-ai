<?php
/**
 * Carte d'une annonce (accueil et liste des annonces).
 * Variable : $item
 */
$isLost = $item['type'] === 'lost';
?>
<article class="item-card">
    <div class="item-card-media">
        <?php if (!empty($item['photo_path'])): ?>
            <img class="item-card-image" src="/<?= e($item['photo_path']) ?>" alt="" loading="lazy" decoding="async">
        <?php else: ?>
            <div class="item-card-placeholder <?= $isLost ? 'is-lost' : 'is-found' ?>">
                <?= icon($isLost ? 'search' : 'package', 'icon icon-xl') ?>
            </div>
        <?php endif; ?>
        <span class="badge <?= $isLost ? 'badge-lost' : 'badge-found' ?>">
            <?= $isLost ? 'Perdu' : 'Trouvé' ?>
        </span>
    </div>
    <div class="item-card-body">
        <h3>
            <!-- Le lien couvre toute la carte (voir .item-card-link::after dans le CSS) -->
            <a class="item-card-link" href="/items/<?= e($item['id']) ?>"><?= e($item['title']) ?></a>
        </h3>
        <p class="item-description"><?= e($item['description']) ?></p>
        <ul class="item-facts">
            <li><?= icon('map-pin') ?><?= e($item['location_label']) ?></li>
            <li><?= icon('calendar') ?><time datetime="<?= e($item['date_event']) ?>"><?= e(date('d/m/Y', strtotime($item['date_event']))) ?></time></li>
        </ul>
    </div>
</article>
