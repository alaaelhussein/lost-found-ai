<?php
/**
 * Tests de l'algorithme de correspondance (sans base de données).
 *
 * Lancement :
 *     php tests/MatchingServiceTest.php
 *   ou avec Docker :
 *     docker compose exec app php tests/MatchingServiceTest.php
 *
 * Mini-framework de test maison : check() affiche OK / ÉCHEC pour chaque cas,
 * et le script se termine avec le code 1 si un test échoue (utilisable en CI).
 */

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../src/' . str_replace(['App\\', '\\'], ['', '/'], $class) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Services\MatchingService;

$failures = 0;

function check(string $label, bool $condition, string $detail = ''): void
{
    global $failures;
    echo ($condition ? '  OK     ' : '  ÉCHEC  ') . $label . ($detail !== '' ? "  ({$detail})" : '') . PHP_EOL;
    if (!$condition) {
        $failures++;
    }
}

/** Annonce de test : seuls les champs utiles au calcul */
function item(string $type, string $title, string $category, string $color, string $location, string $date, string $description = ''): array
{
    return [
        'type' => $type, 'title' => $title, 'description' => $description, 'category' => $category,
        'color' => $color, 'location_label' => $location, 'date_event' => $date,
        'latitude' => null, 'longitude' => null,
    ];
}

$matching = new MatchingService();

echo PHP_EOL . 'Normalisation du texte' . PHP_EOL;
check('accents et majuscules', MatchingService::normalize('Bibliothèque Universitaire !') === 'bibliotheque universitaire');
check('mots vides retirés et pluriel', $matching->tokens('Mes clés dans le sac') === ['cle', 'sac']);
check('expression « porte-monnaie »', $matching->tokens('Porte-monnaie') === ['portefeuille']);
check('synonyme « smartphone »', $matching->tokens('Smartphone') === ['telephone']);
check('abréviation « BU »', $matching->tokens('BU') === ['bibliotheque']);
check('famille de couleur « bleu marine »', $matching->colorFamilies('Bleu marine') === ['bleu']);
check('« argenté » = gris', $matching->colorFamilies('argenté') === ['gris']);

echo PHP_EOL . 'Exemple du README : sac noir à la bibliothèque' . PHP_EOL;
$lost = item('lost', "J'ai perdu mon sac noir", 'Sac', 'noir', 'Bibliothèque', '2026-10-10', 'Sac à dos noir Eastpak avec des cahiers');
$found = item('found', "J'ai trouvé un sac noir", 'Sac', 'Noir', 'Bibliothèque universitaire', '2026-10-11', 'Sac à dos Eastpak noir');
$result = $matching->score($lost, $found);
check('score très élevé (>= 90)', $result['score'] >= 90, 'score ' . $result['score'] . ' ' . json_encode($result['breakdown']));
check('même catégorie', $result['breakdown']['category'] === 25);
check('même couleur', $result['breakdown']['color'] === 20);
check('même lieu (BU = bibliothèque)', $result['breakdown']['location'] === 25);
check('raisons expliquées', in_array('Même catégorie', $result['reasons'], true), implode(' | ', $result['reasons']));
check('ordre des annonces sans importance', $matching->score($found, $lost)['score'] === $result['score']);

echo PHP_EOL . 'Synonymes : mots différents, même objet' . PHP_EOL;
$lost = item('lost', 'Porte-monnaie marron', 'Porte-monnaie', 'marron', 'Gare centrale', '2026-10-01');
$found = item('found', 'Portefeuille en cuir brun', 'Portefeuille', 'brun', 'Gare', '2026-10-02');
$result = $matching->score($lost, $found);
check('porte-monnaie / portefeuille, marron / brun', $result['score'] >= 75, 'score ' . $result['score'] . ' ' . json_encode($result['breakdown']));

echo PHP_EOL . 'Objets différents' . PHP_EOL;
$lost = item('lost', 'iPhone 13 bleu', 'Téléphone', 'bleu', 'Cafétéria', '2026-10-01');
$found = item('found', 'Trousseau de clés', 'Clés', 'rouge', 'Parking nord', '2026-10-02');
$result = $matching->score($lost, $found);
check('score bas (< 60, sous le seuil)', $result['score'] < 60, 'score ' . $result['score']);

echo PHP_EOL . 'Règles sur les dates' . PHP_EOL;
$lost = item('lost', 'Sac noir', 'Sac', 'noir', 'Gare', '2026-10-10');
check('trouvé 5 jours AVANT la perte => 0', $matching->score($lost, item('found', 'Sac noir', 'Sac', 'noir', 'Gare', '2026-10-05'))['score'] === 0);
check('trouvé la veille (tolérance) => accepté', $matching->score($lost, item('found', 'Sac noir', 'Sac', 'noir', 'Gare', '2026-10-09'))['score'] > 0);
$sameDay = $matching->score($lost, item('found', 'Sac noir', 'Sac', 'noir', 'Gare', '2026-10-10'))['breakdown']['date'];
$later = $matching->score($lost, item('found', 'Sac noir', 'Sac', 'noir', 'Gare', '2026-11-20'))['breakdown']['date'];
check('plus l\'écart est long, moins de points', $sameDay === 15 && $later === 0, "même jour {$sameDay}, 41 jours après {$later}");

echo PHP_EOL . 'Cas particuliers' . PHP_EOL;
check('deux annonces « perdu » => 0', $matching->score($lost, $lost)['score'] === 0);
$noColor = $matching->score(item('lost', 'Sac', 'Sac', '', 'Gare', '2026-10-10'), item('found', 'Sac', 'Sac', 'noir', 'Gare', '2026-10-10'));
check('couleur inconnue : pas de pénalité totale', $noColor['breakdown']['color'] === 6);
$inferred = $matching->score(item('lost', 'Sac rouge', 'Sac', '', 'Gare', '2026-10-10'), item('found', 'Sac', 'Sac', 'rouge', 'Gare', '2026-10-10'));
check('couleur trouvée dans le titre', $inferred['breakdown']['color'] === 16);
check('distance GPS Paris - Paris (0 km)', MatchingService::distanceKm(48.8566, 2.3522, 48.8566, 2.3522) < 0.001);
$km = MatchingService::distanceKm(48.8566, 2.3522, 45.7640, 4.8357);
check('distance GPS Paris - Lyon (~392 km)', abs($km - 392) < 5, round($km) . ' km');

echo PHP_EOL . ($failures === 0 ? 'Tous les tests passent.' : "{$failures} test(s) en échec.") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
