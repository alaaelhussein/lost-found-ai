<?php
/**
 * Liste et recherche de toutes les annonces (y compris résolues).
 * Variables : $items (Item::searchAdmin), $search
 */

use App\Core\Csrf;

$statusLabels = ['open' => 'Ouverte', 'matched' => 'En cours', 'resolved' => 'Résolue'];
?>
<section class="page-intro">
    <p class="eyebrow">Administration</p>
    <h1>Annonces</h1>
    <p>Toutes les annonces de la plateforme, y compris celles déjà résolues.</p>
</section>

<?php require __DIR__ . '/_nav.php'; ?>

<form class="filters" method="get" action="/admin/items" role="search">
    <div class="field field-search">
        <label for="q">Recherche</label>
        <div class="input-icon">
            <?= icon('search') ?>
            <input type="search" id="q" name="q" maxlength="100" placeholder="Titre, lieu, email de l’auteur…" value="<?= e($search) ?>">
        </div>
    </div>
    <div class="filters-actions">
        <button type="submit" class="btn btn-primary">Rechercher</button>
        <?php if ($search !== ''): ?>
            <a class="btn btn-ghost" href="/admin/items">Réinitialiser</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($items === []): ?>
    <section class="empty-state">
        <span class="empty-icon"><?= icon('package') ?></span>
        <p>Aucune annonce ne correspond à cette recherche.</p>
    </section>
<?php else: ?>
    <p class="results-count" role="status"><?= count($items) ?> annonce<?= count($items) > 1 ? 's' : '' ?></p>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Titre</th>
                    <th>Type</th>
                    <th>Statut</th>
                    <th>Lieu</th>
                    <th>Auteur</th>
                    <th>Publiée le</th>
                    <th class="admin-table-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><a href="/items/<?= e($item['id']) ?>" target="_blank" rel="noopener"><?= e($item['title']) ?></a></td>
                        <td><span class="badge badge-<?= e($item['type']) ?>"><?= $item['type'] === 'lost' ? 'Perdu' : 'Trouvé' ?></span></td>
                        <td><span class="status status-<?= e($item['status']) ?>"><?= e($statusLabels[$item['status']] ?? $item['status']) ?></span></td>
                        <td><?= e($item['location_label']) ?></td>
                        <td><?= e($item['author_name']) ?> (<?= e($item['author_email']) ?>)</td>
                        <td><?= e(date('d/m/Y', strtotime($item['created_at']))) ?></td>
                        <td class="admin-table-actions">
                            <form method="post" action="/admin/items/<?= e($item['id']) ?>/delete"
                                  data-confirm="Supprimer définitivement l’annonce « <?= e($item['title']) ?> » ?">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="back" value="/admin/items">
                                <button type="submit" class="btn btn-ghost btn-sm"><?= icon('trash') ?>Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
