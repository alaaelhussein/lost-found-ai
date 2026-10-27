<?php
/**
 * Donne (ou retire) le rôle d'administrateur à un compte existant
 * (ligne de commande uniquement : aucune page web ne permet de le faire,
 * donc aucune faille web ne peut créer un administrateur).
 *
 *     php bin/make-admin.php alice@exemple.fr           # donne le rôle
 *     php bin/make-admin.php alice@exemple.fr --remove  # le retire
 *     docker compose exec app php bin/make-admin.php alice@exemple.fr
 *
 * L'administrateur voit le lien « Modération » dans le menu et traite les
 * annonces signalées sur /admin/reports.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

use App\Models\User;

$email = mb_strtolower(trim((string) ($argv[1] ?? '')));
$remove = in_array('--remove', $argv, true);

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Usage : php bin/make-admin.php email@exemple.fr [--remove]\n");
    exit(1);
}

$users = new User();
$user = $users->findByEmail($email);
if ($user === null) {
    fwrite(STDERR, "Aucun compte avec l'email {$email}. Créez d'abord le compte sur le site.\n");
    exit(1);
}

$users->setAdmin((int) $user['id'], !$remove);
echo $remove
    ? "{$user['name']} ({$email}) n'est plus administrateur.\n"
    : "{$user['name']} ({$email}) est maintenant administrateur.\n";
