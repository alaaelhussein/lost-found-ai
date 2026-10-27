<?php
/**
 * MatchingService : calcule un score de correspondance (sur 100) entre une
 * annonce « perdu » et une annonce « trouvé ».
 *
 * Algorithme classique, sans IA : chaque critère rapporte des points.
 *
 *   Critère                                       Points max
 *   Catégorie identique (ou synonyme)                    25
 *   Couleur identique (ou même famille de couleur)       20
 *   Lieu proche (mêmes mots, ou distance GPS)            25
 *   Date cohérente (trouvé peu après la perte)           15
 *   Mots-clés communs (titre + description)              15
 *
 * Règle éliminatoire : un objet ne peut pas être trouvé AVANT d'avoir été
 * perdu (tolérance d'un jour pour les dates approximatives) => score 0.
 *
 * Le texte est d'abord normalisé (minuscules, sans accents, sans mots vides
 * comme « le », « dans »...), puis les synonymes sont ramenés à un même mot :
 * « porte-monnaie », « portefeuille » et « wallet » deviennent tous
 * « portefeuille ». Ainsi, deux personnes qui décrivent le même objet avec des
 * mots différents obtiennent quand même un bon score.
 *
 * Deux usages :
 *   - score($a, $b)          : calcul pur entre deux annonces (testable sans base)
 *   - refreshForItem($item)  : cherche les candidats en base, calcule, enregistre
 *                              et prévient par email les auteurs concernés
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\Item;
use App\Models\MatchModel;
use DateTimeImmutable;

final class MatchingService
{
    public const WEIGHT_CATEGORY = 25;
    public const WEIGHT_COLOR    = 20;
    public const WEIGHT_LOCATION = 25;
    public const WEIGHT_DATE     = 15;
    public const WEIGHT_KEYWORDS = 15;

    /** Lettres accentuées => lettre simple (« bibliothèque » == « bibliotheque ») */
    private const ACCENTS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y',
        'œ' => 'oe', 'æ' => 'ae',
    ];

    /** Mots trop courants pour aider à distinguer deux annonces */
    private const STOPWORDS = [
        'le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'et', 'ou', 'au', 'aux', 'en', 'dans',
        'sur', 'sous', 'avec', 'sans', 'pour', 'par', 'pres', 'devant', 'derriere', 'chez', 'vers',
        'entre', 'mon', 'ma', 'mes', 'ton', 'ta', 'tes', 'son', 'sa', 'ses', 'notre', 'votre', 'leur',
        'leurs', 'ce', 'cet', 'cette', 'ces', 'qui', 'que', 'quoi', 'dont', 'il', 'elle', 'on', 'je',
        'ai', 'est', 'etait', 'suis', 'sont', 'ete', 'ne', 'pas', 'plus', 'tres', 'avant', 'apres',
        'hier', 'aujourd', 'hui', 'matin', 'soir', 'midi', 'quelqu', 'quelque', 'trouve', 'trouvee',
        'trouves', 'perdu', 'perdue', 'perdus', 'objet', 'objets', 'avoir', 'peut', 'etre', 'cote',
    ];

    /** Expressions de plusieurs mots ramenées à un seul mot (avant découpage) */
    private const PHRASES = [
        'porte monnaie' => 'portefeuille', 'porte cartes' => 'portefeuille', 'porte carte' => 'portefeuille',
        'porte feuille' => 'portefeuille', 'sac a dos' => 'sac', 'sac a main' => 'sac',
        'carte d identite' => 'papier', 'carte identite' => 'papier', 'carte vitale' => 'papier',
        'permis de conduire' => 'papier', 'trousseau de cles' => 'cle', 'trousseau de cle' => 'cle',
        'bibliotheque universitaire' => 'bibliotheque', 'restaurant universitaire' => 'restaurant',
    ];

    /** Synonymes d'un seul mot (forme déjà réduite au singulier) => mot de référence */
    private const SYNONYMS = [
        'portable' => 'telephone', 'smartphone' => 'telephone', 'iphone' => 'telephone',
        'mobile' => 'telephone', 'tel' => 'telephone', 'gsm' => 'telephone', 'samsung' => 'telephone',
        'android' => 'telephone',
        'wallet' => 'portefeuille', 'portemonnaie' => 'portefeuille',
        'clef' => 'cle', 'trousseau' => 'cle', 'key' => 'cle',
        'sacoche' => 'sac', 'cartable' => 'sac', 'valise' => 'sac', 'bagage' => 'sac', 'backpack' => 'sac',
        'passeport' => 'papier', 'permi' => 'papier', 'cni' => 'papier', 'identite' => 'papier',
        'pc' => 'ordinateur', 'laptop' => 'ordinateur', 'macbook' => 'ordinateur',
        'airpod' => 'ecouteur', 'oreillette' => 'ecouteur',
        'bu' => 'bibliotheque', 'bibli' => 'bibliotheque', 'biblio' => 'bibliotheque',
        'fac' => 'universite', 'univ' => 'universite', 'campu' => 'universite',
        'ru' => 'restaurant', 'resto' => 'restaurant', 'cantine' => 'restaurant',
    ];

    /** Mot de couleur => famille de couleur (« argenté » et « gris » se correspondent) */
    private const COLOR_FAMILIES = [
        'noir' => 'noir', 'noire' => 'noir', 'black' => 'noir',
        'blanc' => 'blanc', 'blanche' => 'blanc', 'white' => 'blanc', 'creme' => 'blanc', 'ivoire' => 'blanc',
        'gris' => 'gris', 'grise' => 'gris', 'argent' => 'gris', 'argente' => 'gris', 'argentee' => 'gris',
        'silver' => 'gris', 'anthracite' => 'gris', 'grey' => 'gris', 'gray' => 'gris',
        'bleu' => 'bleu', 'bleue' => 'bleu', 'marine' => 'bleu', 'turquoise' => 'bleu', 'cyan' => 'bleu',
        'blue' => 'bleu',
        'rouge' => 'rouge', 'bordeaux' => 'rouge', 'red' => 'rouge',
        'rose' => 'rose', 'fuchsia' => 'rose', 'pink' => 'rose',
        'vert' => 'vert', 'verte' => 'vert', 'kaki' => 'vert', 'green' => 'vert',
        'jaune' => 'jaune', 'yellow' => 'jaune', 'moutarde' => 'jaune',
        'dore' => 'or', 'doree' => 'or', 'gold' => 'or',
        'marron' => 'marron', 'brun' => 'marron', 'brune' => 'marron', 'chocolat' => 'marron',
        'camel' => 'marron', 'cognac' => 'marron', 'brown' => 'marron', 'caramel' => 'marron',
        'beige' => 'beige', 'taupe' => 'beige',
        'orange' => 'orange',
        'violet' => 'violet', 'violette' => 'violet', 'mauve' => 'violet', 'lilas' => 'violet', 'purple' => 'violet',
    ];

    // ------------------------------------------------------------------
    // Usage avec la base de données
    // ------------------------------------------------------------------

    /**
     * Recherche les correspondances d'une annonce, les enregistre dans la table
     * `matches` et les retourne, de la plus probable à la moins probable.
     *
     * @return list<array{item: array, score: int, breakdown: array<string, int>, reasons: list<string>}>
     */
    public function refreshForItem(array $item): array
    {
        $minScore = (int) Config::get('matching.min_score', 60);
        $results = [];

        foreach ((new Item())->findMatchCandidates($item) as $candidate) {
            $result = $this->score($item, $candidate);
            if ($result['score'] >= $minScore) {
                $results[] = ['item' => $candidate] + $result;
            }
        }

        usort($results, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        (new MatchModel())->saveForItem((int) $item['id'], (string) $item['type'], $results);

        // Prévient par email les auteurs des annonces d'en face (une seule fois
        // par correspondance, voir matches.notified_at)
        (new Notifier())->newMatches($item);

        return $results;
    }

    // ------------------------------------------------------------------
    // Calcul du score (fonctions pures, sans base de données)
    // ------------------------------------------------------------------

    /**
     * Score entre deux annonces de types opposés (l'ordre n'a pas d'importance).
     *
     * @return array{score: int, breakdown: array<string, int>, reasons: list<string>}
     */
    public function score(array $a, array $b): array
    {
        $empty = ['score' => 0, 'breakdown' => [], 'reasons' => []];
        if (($a['type'] ?? '') === ($b['type'] ?? '')) {
            return $empty; // deux « perdu » ou deux « trouvé » : aucun sens
        }
        [$lost, $found] = $a['type'] === 'lost' ? [$a, $b] : [$b, $a];

        $date = $this->dateScore((string) $lost['date_event'], (string) $found['date_event']);
        if ($date === null) {
            return $empty; // trouvé avant d'avoir été perdu : impossible
        }

        $reasons = [];
        $keywords = $this->keywordScore($lost, $found, $common);

        $breakdown = [
            'category' => $this->categoryScore($lost, $found),
            'color'    => $this->colorScore($lost, $found),
            'location' => $this->locationScore($lost, $found),
            'date'     => $date,
            'keywords' => $keywords,
        ];

        if ($breakdown['category'] === self::WEIGHT_CATEGORY) {
            $reasons[] = 'Même catégorie';
        } elseif ($breakdown['category'] > 0) {
            $reasons[] = 'Catégorie proche';
        }
        if ($breakdown['color'] >= 16) {
            $reasons[] = 'Même couleur';
        }
        if ($breakdown['location'] >= 20) {
            $reasons[] = 'Même lieu';
        } elseif ($breakdown['location'] >= 10) {
            $reasons[] = 'Lieu proche';
        }
        if ($breakdown['date'] >= 12) {
            $reasons[] = 'Dates très proches';
        } elseif ($breakdown['date'] >= 6) {
            $reasons[] = 'Dates compatibles';
        }
        if ($keywords >= 8 && $common !== []) {
            $reasons[] = 'Mots communs : ' . implode(', ', array_slice($common, 0, 3));
        }

        return [
            'score'     => min(100, array_sum($breakdown)),
            'breakdown' => $breakdown,
            'reasons'   => $reasons,
        ];
    }

    /** Catégorie : identique (après synonymes) = 25, l'une contient l'autre = 15. */
    private function categoryScore(array $lost, array $found): int
    {
        $a = $this->tokens((string) $lost['category']);
        $b = $this->tokens((string) $found['category']);
        if ($a === [] || $b === []) {
            return 0;
        }
        if ($a === $b || array_intersect($a, $b) === $a || array_intersect($b, $a) === $b) {
            return self::WEIGHT_CATEGORY;
        }

        return array_intersect($a, $b) !== [] ? 15 : 0;
    }

    /**
     * Couleur : même famille = 20. Si la couleur n'est pas renseignée, on la
     * cherche dans le titre et la description (16 si elle correspond).
     * Couleur inconnue d'un côté = 6 (on ne pénalise pas totalement).
     */
    private function colorScore(array $lost, array $found): int
    {
        $a = $this->colorFamilies((string) ($lost['color'] ?? ''));
        $b = $this->colorFamilies((string) ($found['color'] ?? ''));
        if ($a !== [] && $b !== []) {
            return array_intersect($a, $b) !== [] ? self::WEIGHT_COLOR : 0;
        }

        $a = $a ?: $this->colorFamilies($lost['title'] . ' ' . ($lost['description'] ?? ''));
        $b = $b ?: $this->colorFamilies($found['title'] . ' ' . ($found['description'] ?? ''));
        if ($a !== [] && $b !== []) {
            return array_intersect($a, $b) !== [] ? 16 : 0;
        }

        return 6;
    }

    /**
     * Lieu : distance GPS si les deux annonces ont des coordonnées (phase 2,
     * carte), sinon comparaison des mots du lieu (« gare » / « gare centrale »).
     */
    private function locationScore(array $lost, array $found): int
    {
        if (isset($lost['latitude'], $lost['longitude'], $found['latitude'], $found['longitude'])) {
            $km = self::distanceKm(
                (float) $lost['latitude'], (float) $lost['longitude'],
                (float) $found['latitude'], (float) $found['longitude']
            );

            return match (true) {
                $km <= 0.3 => self::WEIGHT_LOCATION,
                $km <= 1   => 21,
                $km <= 3   => 14,
                $km <= 10  => 7,
                default    => 0,
            };
        }

        $similarity = $this->similarity(
            $this->tokens((string) $lost['location_label']),
            $this->tokens((string) $found['location_label'])
        );

        return (int) round(self::WEIGHT_LOCATION * $similarity);
    }

    /**
     * Date : l'objet est trouvé après avoir été perdu. Plus l'écart est court,
     * plus le score est haut. Retourne null si c'est impossible (trouvé plus
     * d'un jour AVANT la perte).
     */
    private function dateScore(string $lostDate, string $foundDate): ?int
    {
        $lost = DateTimeImmutable::createFromFormat('!Y-m-d', $lostDate);
        $found = DateTimeImmutable::createFromFormat('!Y-m-d', $foundDate);
        if ($lost === false || $found === false) {
            return 0;
        }

        $days = (int) $lost->diff($found)->format('%r%a'); // négatif si trouvé avant la perte

        return match (true) {
            $days < -1  => null,
            $days <= 1  => self::WEIGHT_DATE,
            $days <= 3  => 12,
            $days <= 7  => 9,
            $days <= 14 => 6,
            $days <= 30 => 3,
            default     => 0,
        };
    }

    /**
     * Mots-clés : part des mots du texte le plus court que l'on retrouve dans
     * l'autre (titre + description).
     *
     * @param list<string> $common Reçoit les mots communs (pour l'affichage)
     */
    private function keywordScore(array $lost, array $found, ?array &$common): int
    {
        $a = $this->tokens($lost['title'] . ' ' . ($lost['description'] ?? ''));
        $b = $this->tokens($found['title'] . ' ' . ($found['description'] ?? ''));
        $common = array_values(array_intersect($a, $b));

        if ($a === [] || $b === []) {
            return 0;
        }

        $overlap = count($common) / min(count($a), count($b));

        return (int) round(self::WEIGHT_KEYWORDS * min(1.0, $overlap));
    }

    // ------------------------------------------------------------------
    // Outils de traitement du texte
    // ------------------------------------------------------------------

    /** « Bibliothèque Universitaire ! » => « bibliotheque universitaire » */
    public static function normalize(string $text): string
    {
        $text = strtr(mb_strtolower($text, 'UTF-8'), self::ACCENTS);
        $text = (string) preg_replace('/[^a-z0-9]+/', ' ', $text);

        return trim($text);
    }

    /**
     * Découpe un texte en mots utiles : normalisé, sans mots vides, au
     * singulier, synonymes remplacés. Résultat trié et sans doublon.
     *
     * « Mes clés dans un porte-monnaie » => ['cle', 'portefeuille']
     *
     * @return list<string>
     */
    public function tokens(string $text): array
    {
        $text = ' ' . self::normalize($text) . ' ';
        foreach (self::PHRASES as $phrase => $replacement) {
            $text = str_replace(' ' . $phrase . ' ', ' ' . $replacement . ' ', $text);
        }

        $tokens = [];
        foreach (explode(' ', trim($text)) as $word) {
            if (strlen($word) < 2 || in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            // Singulier simplifié : « cles » => « cle », « bijoux » => « bijou »
            if (strlen($word) > 3 && in_array(substr($word, -1), ['s', 'x'], true)) {
                $word = substr($word, 0, -1);
            }
            $tokens[] = self::SYNONYMS[$word] ?? $word;
        }

        $tokens = array_values(array_unique($tokens));
        sort($tokens);

        return $tokens;
    }

    /**
     * Familles de couleur citées dans un texte : « bleu marine » => ['bleu'].
     *
     * @return list<string>
     */
    public function colorFamilies(string $text): array
    {
        $families = [];
        foreach (explode(' ', self::normalize($text)) as $word) {
            if (isset(self::COLOR_FAMILIES[$word])) {
                $families[] = self::COLOR_FAMILIES[$word];
            }
        }

        return array_values(array_unique($families));
    }

    /**
     * Similarité entre deux listes de mots, de 0 à 1. On garde la meilleure
     * des deux mesures :
     *   - Jaccard : mots communs / mots au total ;
     *   - recouvrement : mots communs / taille de la plus petite liste (un
     *     peu réduit, car « gare » est inclus dans « gare centrale quai 3 »
     *     sans être exactement le même lieu).
     *
     * @param list<string> $a
     * @param list<string> $b
     */
    private function similarity(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }
        $common = count(array_intersect($a, $b));
        $jaccard = $common / count(array_unique(array_merge($a, $b)));
        $overlap = $common / min(count($a), count($b));

        return max($jaccard, 0.8 * $overlap);
    }

    /** Distance à vol d'oiseau entre deux points GPS (formule de haversine). */
    public static function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 6371 * 2 * asin(min(1.0, sqrt($h)));
    }
}
