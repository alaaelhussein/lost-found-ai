<?php
/**
 * Liste et recherche des comptes utilisateurs.
 * Variables : $users (User::searchAdmin), $search
 */

use App\Core\Auth;
use App\Core\Csrf;

$myId = (int) Auth::id();
?>
<section class="page-intro">
    <p class="eyebrow">Administration</p>
    <h1>Utilisateurs</h1>
    <p>Rôles, vérification de l’email et suppression de compte.</p>
</section>

<?php require __DIR__ . '/_nav.php'; ?>

<form class="filters" method="get" action="/admin/users" role="search">
    <div class="field field-search">
        <label for="q">Recherche</label>
        <div class="input-icon">
            <?= icon('search') ?>
            <input type="search" id="q" name="q" maxlength="100" placeholder="Nom ou email…" value="<?= e($search) ?>">
        </div>
    </div>
    <div class="filters-actions">
        <button type="submit" class="btn btn-primary">Rechercher</button>
        <?php if ($search !== ''): ?>
            <a class="btn btn-ghost" href="/admin/users">Réinitialiser</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($users === []): ?>
    <section class="empty-state">
        <span class="empty-icon"><?= icon('user') ?></span>
        <p>Aucun compte ne correspond à cette recherche.</p>
    </section>
<?php else: ?>
    <p class="results-count" role="status"><?= count($users) ?> compte<?= count($users) > 1 ? 's' : '' ?></p>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Email vérifié</th>
                    <th>Annonces</th>
                    <th>Inscrit le</th>
                    <th class="admin-table-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= e($user['name']) ?></td>
                        <td><?= e($user['email']) ?></td>
                        <td>
                            <span class="status <?= $user['role'] === 'admin' ? 'status-admin' : 'status-resolved' ?>">
                                <?= $user['role'] === 'admin' ? 'Administrateur' : 'Utilisateur' ?>
                            </span>
                        </td>
                        <td><?= $user['email_verified_at'] !== null ? icon('check') : '—' ?></td>
                        <td><?= (int) $user['item_count'] ?></td>
                        <td><?= e(date('d/m/Y', strtotime($user['created_at']))) ?></td>
                        <td class="admin-table-actions">
                            <?php if ((int) $user['id'] === $myId): ?>
                                <span class="field-hint">C’est vous</span>
                            <?php else: ?>
                                <form method="post" action="/admin/users/<?= e($user['id']) ?>/toggle-admin">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="btn btn-secondary btn-sm">
                                        <?= $user['role'] === 'admin' ? 'Retirer l’admin' : 'Rendre admin' ?>
                                    </button>
                                </form>
                                <?php if ($user['role'] !== 'admin'): ?>
                                    <form method="post" action="/admin/users/<?= e($user['id']) ?>/delete"
                                          data-confirm="Supprimer le compte de <?= e($user['name']) ?> et TOUTES ses annonces ? Action irréversible.">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="back" value="/admin/users">
                                        <button type="submit" class="btn btn-ghost btn-sm"><?= icon('trash') ?>Supprimer</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
