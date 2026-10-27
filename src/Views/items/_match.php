<?php
/**
 * Une correspondance proposée par MatchingService.
 * Variable : $match = ['item' => annonce d'en face, 'score' => 0-100, 'reasons' => [...]]
 *
 * Niveau du score (couleur du badge) : >= 85 très probable, >= 70 probable,
 * sinon possible (le seuil d'affichage est matching.min_score, 60 par défaut).
 */
$other = $match['item'];
$score = (int) $match['score'];
$otherIsLost = $other['type'] === 'lost';
$level = $score >= 85 ? 'high' : ($score >= 70 ? 'medium' : 'low');
$levelLabel = ['high' => 'Très probable', 'medium' => 'Probable', 'low' => 'Possible'][$level];
?>
<li class="match">
    <div class="match-thumb">
        <?php if (!empty($other['photo_path'])): ?>
            <img src="/<?= e($other['photo_path']) ?>" alt="" loading="lazy">
        <?php else: ?>
            <span class="<?= $otherIsLost ? 'is-lost' : 'is-found' ?>"><?= icon($otherIsLost ? 'search' : 'package') ?></span>
        <?php endif; ?>
    </div>

    <div class="match-body">
        <h3><a class="match-link" href="/items/<?= e($other['id']) ?>"><?= e($other['title']) ?></a></h3>
        <p class="match-meta">
            <?= icon('map-pin') ?><?= e($other['location_label']) ?>
            <span aria-hidden="true">·</span>
            <time datetime="<?= e($other['date_event']) ?>"><?= e(date('d/m/Y', strtotime($other['date_event']))) ?></time>
        </p>
        <?php if ($match['reasons'] !== []): ?>
            <ul class="match-reasons" aria-label="Points communs">
                <?php foreach ($match['reasons'] as $reason): ?>
                    <li><?= icon('check') ?><?= e($reason) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="match-score score-<?= $level ?>">
        <strong><?= $score ?>&nbsp;%</strong>
        <span><?= $levelLabel ?></span>
    </div>
</li>
