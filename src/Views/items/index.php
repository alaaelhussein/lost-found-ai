<?php
/**
 * Liste publique des annonces ouvertes.
 */
$hasFilters = $filters['q'] !== '' || $filters['type'] !== '' || $filters['category'] !== '';
?>
<section class="page-intro">
    <p class="eyebrow">La communauté</p>
    <h1>Objets perdus et trouvés</h1>
    <p>Recherchez une annonce ou parcourez les objets signalés récemment.</p>
</section>

<form class="filters" method="get" action="/items" role="search">
    <div class="field field-search">
        <label for="q">Recherche</label>
        <div class="input-icon">
            <?= icon('search') ?>
            <input type="search" id="q" name="q" maxlength="100" placeholder="Sac noir, clés, bibliothèque…"
                   value="<?= e($filters['q']) ?>">
        </div>
    </div>
    <div class="field">
        <label for="type">Type</label>
        <select id="type" name="type">
            <option value="">Tous</option>
            <option value="lost" <?= $filters['type'] === 'lost' ? 'selected' : '' ?>>Objets perdus</option>
            <option value="found" <?= $filters['type'] === 'found' ? 'selected' : '' ?>>Objets trouvés</option>
        </select>
    </div>
    <div class="field">
        <label for="category">Catégorie</label>
        <input type="text" id="category" name="category" maxlength="50" placeholder="Ex. sac"
               value="<?= e($filters['category']) ?>">
    </div>
    <div class="filters-actions">
        <button type="submit" class="btn btn-primary">Rechercher</button>
        <?php if ($hasFilters): ?>
            <a class="btn btn-ghost" href="/items">Réinitialiser</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($items === []): ?>
    <section class="empty-state">
        <span class="empty-icon"><?= icon('search') ?></span>
        <h2>Aucune annonce trouvée</h2>
        <p><?= $hasFilters ? 'Essayez d’élargir votre recherche ou de retirer un filtre.' : 'Aucune annonce n’est en ligne pour le moment.' ?></p>
        <a class="btn btn-primary" href="/items/create?type=lost"><?= icon('plus') ?>Publier une annonce</a>
    </section>
<?php else: ?>
    <p class="results-count" role="status">
        <?= count($items) ?> annonce<?= count($items) > 1 ? 's' : '' ?><?= $hasFilters ? ' correspondant à votre recherche' : '' ?>
    </p>
    <section class="item-grid">
        <?php foreach ($items as $item): ?>
            <?php require __DIR__ . '/_card.php'; ?>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
