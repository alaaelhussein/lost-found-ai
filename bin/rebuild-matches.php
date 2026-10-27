<?php
/**
 * Recalcule toutes les correspondances (ligne de commande uniquement).
 *
 * Utile après avoir modifié l'algorithme (poids, synonymes, seuil) ou pour
 * traiter des annonces publiées avant l'arrivée du calcul automatique.
 *
 *     php bin/rebuild-matches.php
 *     docker compose exec app php bin/rebuild-matches.php
 *
 * Ce fichier est hors de public/ : il n'est pas accessible depuis le web.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

use App\Models\Item;
use App\Services\MatchingService;

$matching = new MatchingService();
$items = (new Item())->findAllOpen();
$total = 0;

foreach ($items as $item) {
    $results = $matching->refreshForItem($item);
    $total += count($results);
    printf("#%-5d %-6s %-40s %d correspondance(s)%s\n",
        $item['id'], $item['type'], mb_strimwidth($item['title'], 0, 40, '…'), count($results),
        $results === [] ? '' : ' (meilleur score : ' . $results[0]['score'] . ' %)');
}

// Chaque couple est vu deux fois (depuis l'annonce perdue et depuis la trouvée)
printf("\n%d annonce(s) ouvertes analysées, %d correspondance(s) distincte(s).\n", count($items), intdiv($total, 2));
