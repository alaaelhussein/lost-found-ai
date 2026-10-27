<?php
/**
 * Nettoyage quotidien de la base (ligne de commande uniquement).
 *
 * À lancer une fois par jour par une tâche planifiée (« cron ») :
 *     php bin/cleanup.php
 *     docker compose exec app php bin/cleanup.php
 * Sur Hostinger : hPanel > Avancé > Tâches Cron, commande
 *     /usr/bin/php /home/<utilisateur>/<dossier>/bin/cleanup.php
 *
 * Ce qu'il fait (durées annoncées dans la politique de confidentialité :
 * si vous les changez ici, changez aussi src/Views/pages/privacy.php) :
 *   - codes de vérification et liens « mot de passe oublié » expirés ;
 *   - journaux de sécurité (IP, tentatives de connexion) de plus de 24 h ;
 *   - comptes jamais vérifiés créés il y a plus de 7 jours (robots, fautes de frappe).
 *
 * Ce fichier est hors de public/ : il n'est pas accessible depuis le web.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

use App\Models\EmailVerification;
use App\Models\LoginAttempt;
use App\Models\PasswordReset;
use App\Models\RateLimit;
use App\Models\User;
use App\Services\AccountService;

const UNVERIFIED_ACCOUNT_DAYS = 7;

$report = [
    'codes de vérification expirés'      => (new EmailVerification())->purgeExpired(),
    'liens de mot de passe expirés'      => (new PasswordReset())->purgeExpired(),
    'tentatives de connexion (> 24 h)'   => (new LoginAttempt())->purgeOld(),
    'compteurs anti-spam (> 24 h)'       => (new RateLimit())->purgeOld(),
];

// Comptes non vérifiés : supprimés via AccountService pour effacer aussi
// leurs éventuels fichiers (même s'ils n'ont normalement rien pu publier).
$accounts = new AccountService();
$unverified = (new User())->unverifiedOlderThan(UNVERIFIED_ACCOUNT_DAYS);
foreach ($unverified as $userId) {
    $accounts->delete($userId);
}
$report['comptes jamais vérifiés (> ' . UNVERIFIED_ACCOUNT_DAYS . ' jours)'] = count($unverified);

echo '[' . date('Y-m-d H:i:s') . "] Nettoyage terminé\n";
foreach ($report as $label => $count) {
    printf("  %-42s %d supprimé(s)\n", $label, $count);
}
