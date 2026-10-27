<?php
/**
 * Page d'accueil.
 * Les boutons « Déclarer » ouvrent le formulaire de création d'une annonce.
 */

use App\Core\Auth;
?>
<section class="hero">
    <div class="hero-content">
        <p class="hero-kicker"><?= icon('sparkles') ?>Correspondance automatique des annonces</p>
        <h1>Perdu ou trouvé&nbsp;? Retrouvons son propriétaire.</h1>
        <p class="hero-lead">
            Déclarez l'objet en quelques secondes. Lost&amp;Found AI compare les
            annonces et vous met en relation, sans jamais dévoiler votre email.
        </p>

        <div class="hero-actions">
            <?php if (Auth::check()): ?>
                <a class="btn btn-hero-primary btn-lg" href="/items/create?type=lost"><?= icon('search') ?>J'ai perdu un objet</a>
                <a class="btn btn-hero-secondary btn-lg" href="/items/create?type=found"><?= icon('package') ?>J'ai trouvé un objet</a>
            <?php else: ?>
                <a class="btn btn-hero-primary btn-lg" href="/register">Créer un compte gratuit</a>
                <a class="btn btn-hero-secondary btn-lg" href="/items"><?= icon('search') ?>Parcourir les annonces</a>
            <?php endif; ?>
        </div>
    </div>

    <form class="hero-search" method="get" action="/items" role="search">
        <label for="hero-q" class="visually-hidden">Rechercher une annonce</label>
        <?= icon('search') ?>
        <input type="search" id="hero-q" name="q" maxlength="100" placeholder="Sac noir, clés, bibliothèque…">
        <button type="submit" class="btn btn-primary">Rechercher</button>
    </form>
</section>

<section class="steps" aria-labelledby="steps-title">
    <h2 id="steps-title" class="visually-hidden">Comment ça marche</h2>
    <div class="step">
        <span class="step-icon"><?= icon('package') ?></span>
        <div>
            <h3>1. Déclarez</h3>
            <p>Photo, lieu, date et description de l'objet perdu ou trouvé.</p>
        </div>
    </div>
    <div class="step">
        <span class="step-icon"><?= icon('sparkles') ?></span>
        <div>
            <h3>2. Correspondance</h3>
            <p>Un score sur 100 compare catégorie, couleur, lieu, date et mots-clés.</p>
        </div>
    </div>
    <div class="step">
        <span class="step-icon"><?= icon('shield') ?></span>
        <div>
            <h3>3. Contact sécurisé</h3>
            <p>Échangez par messagerie interne, sans dévoiler votre email.</p>
        </div>
    </div>
</section>

<section class="section-heading">
    <div>
        <p class="eyebrow">En ce moment</p>
        <h2>Les dernières annonces</h2>
    </div>
    <a class="link-arrow" href="/items">Toutes les annonces<?= icon('arrow-right') ?></a>
</section>

<?php if ($items === []): ?>
    <section class="empty-state">
        <span class="empty-icon"><?= icon('package') ?></span>
        <h2>Aucune annonce pour le moment</h2>
        <p>Soyez le premier à publier une annonce et aidez la communauté.</p>
    </section>
<?php else: ?>
    <section class="item-grid">
        <?php foreach ($items as $item): ?>
            <?php require __DIR__ . '/items/_card.php'; ?>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
