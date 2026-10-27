<?php
/**
 * ShippingService : modes de livraison, transporteurs, tarifs et points relais.
 *
 * TROIS MODES, du plus anonyme au moins anonyme :
 *   - relay  : point relais (tabac, supérette...). Le propriétaire n'indique
 *              aucune adresse : seul le point relais figure sur l'étiquette ;
 *   - locker : casier automatique (InPost, Mondial Relay Lockers), même principe, 24 h/24 ;
 *   - home   : domicile. L'adresse est CHIFFRÉE en base et figure sur l'étiquette :
 *              la personne qui expédie la verra (le propriétaire est prévenu).
 *
 * Dans tous les cas, l'expéditeur imprimé est la plateforme (delivery.sender) :
 * la personne qui a trouvé l'objet reste anonyme elle aussi.
 *
 * ⚠ MODE SIMULATION (delivery.provider = 'simulation')
 *   Les tarifs ci-dessous sont indicatifs (ordre de grandeur des prix publics
 *   pour un petit colis de moins de 1 kg) et les points relais sont fictifs.
 *   En production, ces informations viennent d'un agrégateur de transporteurs
 *   (Boxtal, Sendcloud...) : une seule API pour comparer les prix réels,
 *   chercher les vrais points relais et générer les étiquettes PDF.
 *   Seules les méthodes quotes(), relayPoints() et createShipment() sont à
 *   remplacer : le reste de l'application ne change pas.
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class ShippingService
{
    public const MODES = [
        'relay'  => ['label' => 'Point relais', 'hint' => 'Recommandé : votre adresse reste privée'],
        'locker' => ['label' => 'Casier automatique', 'hint' => 'Anonyme et accessible 24 h/24'],
        'home'   => ['label' => 'À domicile', 'hint' => 'Votre adresse sera visible sur l’étiquette'],
    ];

    /**
     * Offres par mode : code => [transporteur, service, prix en centimes, délai].
     * Transporteurs parmi les plus utilisés et les plus rapides en France.
     */
    private const OFFERS = [
        'relay' => [
            'mondial_relay'    => ['carrier' => 'Mondial Relay', 'service' => 'Point Relais', 'cents' => 449, 'delay' => '3 à 5 jours'],
            'chronopost_relay' => ['carrier' => 'Chronopost', 'service' => 'Shop2Shop', 'cents' => 590, 'delay' => '1 à 2 jours'],
            'colissimo_relay'  => ['carrier' => 'Colissimo', 'service' => 'Point Retrait', 'cents' => 625, 'delay' => '2 à 3 jours'],
        ],
        'locker' => [
            'inpost_locker'    => ['carrier' => 'InPost', 'service' => 'Casier', 'cents' => 399, 'delay' => '2 à 3 jours'],
            'mondial_locker'   => ['carrier' => 'Mondial Relay', 'service' => 'Locker', 'cents' => 449, 'delay' => '3 à 5 jours'],
        ],
        'home' => [
            'colissimo_home'   => ['carrier' => 'Colissimo', 'service' => 'Domicile', 'cents' => 815, 'delay' => '2 jours'],
            'chronopost_home'  => ['carrier' => 'Chronopost', 'service' => 'Chrono 13', 'cents' => 1490, 'delay' => 'Le lendemain avant 13 h'],
        ],
    ];

    /**
     * La livraison payante est-elle proposée aux utilisateurs ?
     *
     * Deux conditions :
     *   1. delivery.enabled = true dans la configuration (interrupteur général) ;
     *   2. en PRODUCTION (app.debug = false), il faut un vrai paiement Stripe ET
     *      un vrai transporteur (delivery.provider différent de 'simulation').
     *      Sinon les visiteurs verraient un faux bouton « Payer » et recevraient
     *      de fausses étiquettes : la fonctionnalité est donc masquée d'office.
     *
     * Livraison désactivée : la restitution se fait en main propre, organisée par
     * la messagerie (ClaimController ouvre la conversation à l'acceptation).
     */
    public static function isEnabled(): bool
    {
        if (!Config::get('delivery.enabled', false)) {
            return false;
        }
        if (Config::get('app.debug', false)) {
            return true; // en développement, la simulation complète reste disponible
        }

        return (new PaymentService())->provider() === 'stripe'
            && Config::get('delivery.provider', 'simulation') !== 'simulation';
    }

    /** Mode valide ? */
    public function isMode(string $mode): bool
    {
        return isset(self::MODES[$mode]);
    }

    /** Offres disponibles pour un mode de livraison. */
    public function quotes(string $mode): array
    {
        return self::OFFERS[$mode] ?? [];
    }

    /** Une offre précise, ou null si ce transporteur n'existe pas pour ce mode. */
    public function quote(string $mode, string $code): ?array
    {
        return self::OFFERS[$mode][$code] ?? null;
    }

    /** « Mondial Relay – Point Relais », à partir du code (pour l'affichage). */
    public function carrierName(string $code): string
    {
        foreach (self::OFFERS as $offers) {
            if (isset($offers[$code])) {
                return $offers[$code]['carrier'] . ' – ' . $offers[$code]['service'];
            }
        }

        return $code;
    }

    /**
     * Points relais (ou casiers) proches d'un code postal.
     *
     * SIMULATION : génère 3 points fictifs mais stables (toujours les mêmes
     * pour un code postal donné). L'identifiant contient le code postal :
     * le serveur peut donc vérifier qu'un point envoyé par le formulaire
     * existe bien, au lieu de faire confiance au navigateur.
     *
     * @return array<string, string> [identifiant => description]
     */
    public function relayPoints(string $mode, string $postalCode): array
    {
        if (!preg_match('/^\d{5}$/', $postalCode) || !in_array($mode, ['relay', 'locker'], true)) {
            return [];
        }

        $places = $mode === 'locker'
            ? ['Casier – Gare', 'Casier – Centre commercial', 'Casier – Station-service']
            : ['Tabac Presse du Centre', 'Supérette Proxi', 'Librairie Papeterie'];
        $streets = ['place de la Gare', 'avenue de la République', 'rue Victor Hugo'];

        $points = [];
        foreach ($places as $index => $place) {
            $number = (intdiv((int) $postalCode, 7) + $index * 13) % 90 + 1;
            $points[$mode . '-' . $postalCode . '-' . ($index + 1)] =
                $place . ', ' . $number . ' ' . $streets[$index] . ', ' . $postalCode;
        }

        return $points;
    }

    /**
     * Crée l'envoi chez le transporteur et retourne le numéro de suivi.
     * SIMULATION : numéro fictif au format « LF » + 10 chiffres + « FR ».
     */
    public function createShipment(int $orderId): string
    {
        return 'LF' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT) . random_int(100000, 999999) . 'FR';
    }
}
