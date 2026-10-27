<?php
/**
 * Modération : annonces signalées, regroupées par annonce.
 * Variables : $reports (Report::openGroupedByItem), $reasons (Report::REASONS)
 *
 * Trois décisions possibles par annonce :
 *   - « Conserver »            : l'annonce est conforme, les signalements sont classés ;
 *   - « Supprimer l'annonce »  : l'annonce, sa photo et les preuves associées sont effacées ;
 *   - « Supprimer le compte »  : l'auteur et TOUTES ses annonces sont effacés (spam, fraude).
 */

use App\Core\Csrf;
?>
<section class="page-intro">
    <p class="eyebrow">Administration</p>
    <h1>Annonces signalées</h1>
    <p><?= $reports === [] ? 'Aucun signalement en attente.' : count($reports) . ' annonce' . (count($reports) > 1 ? 's' : '') . ' à examiner.' ?></p>
</section>

<?php require __DIR__ . '/_nav.php'; ?>

<?php if ($reports === []): ?>
    <section class="empty-state">
        <span class="empty-icon"><?= icon('check') ?></span>
        <p>Tout est en ordre. Vous recevez un email à chaque nouveau signalement.</p>
    </section>
<?php else: ?>
    <ul class="claim-list">
        <?php foreach ($reports as $report): ?>
            <li class="claim card">
                <div class="claim-head">
                    <a href="/items/<?= e($report['item_id']) ?>" target="_blank" rel="noopener"><strong><?= e($report['title']) ?></strong></a>
                    <span class="status status-claim-pending">
                        <?= (int) $report['report_count'] ?> signalement<?= (int) $report['report_count'] > 1 ? 's' : '' ?>
                    </span>
                </div>
                <p class="claim-answer">
                    <span>Motifs :</span>
                    <?= e(implode(', ', array_map(
                        static fn (string $code): string => $reasons[$code] ?? $code,
                        explode(',', (string) $report['reasons'])
                    ))) ?>
                </p>
                <?php if (!empty($report['details'])): ?>
                    <p class="claim-proof"><?= nl2br(e($report['details'])) ?></p>
                <?php endif; ?>
                <p class="field-hint">
                    Publiée par <?= e($report['author_name']) ?> (<?= e($report['author_email']) ?>)
                    le <?= e(date('d/m/Y', strtotime($report['item_created_at']))) ?>.
                </p>

                <div class="claim-actions">
                    <form method="post" action="/admin/reports/<?= e($report['item_id']) ?>/dismiss">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn-secondary btn-sm"><?= icon('check') ?>Conserver</button>
                    </form>
                    <form method="post" action="/admin/items/<?= e($report['item_id']) ?>/delete"
                          data-confirm="Supprimer définitivement l’annonce « <?= e($report['title']) ?> » ?">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn-danger btn-sm"><?= icon('trash') ?>Supprimer l’annonce</button>
                    </form>
                    <form method="post" action="/admin/users/<?= e($report['author_id']) ?>/delete"
                          data-confirm="Supprimer le compte de <?= e($report['author_name']) ?> et TOUTES ses annonces ? Action irréversible.">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn-ghost btn-sm">Supprimer le compte de l’auteur</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
