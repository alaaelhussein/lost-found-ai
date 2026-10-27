<?php
/**
 * PageController : pages d'information légales, obligatoires pour un site
 * ouvert au public en France.
 *
 * Routes :
 *   GET /mentions-legales   mentions légales (loi LCEN de 2004, article 6-III)
 *   GET /confidentialite    politique de confidentialité (RGPD, articles 12 à 14)
 *   GET /cgu                conditions générales d'utilisation
 *
 * Les informations qui dépendent de VOUS (nom, adresse, hébergeur, email de
 * contact) ne sont pas écrites dans les vues : elles viennent de la section
 * 'legal' de config/config.php. Tant qu'une valeur est vide, la page affiche
 * « [À COMPLÉTER] » en évidence, impossible à oublier avant la mise en ligne.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\View;
use App\Services\ShippingService;

final class PageController
{
    /** Date affichée en haut des pages : à changer à chaque modification du texte. */
    private const UPDATED_AT = '15 octobre 2026';

    /** GET /mentions-legales */
    public function legal(): void
    {
        $this->render('pages/legal', 'Mentions légales');
    }

    /** GET /confidentialite */
    public function privacy(): void
    {
        $this->render('pages/privacy', 'Politique de confidentialité');
    }

    /** GET /cgu */
    public function terms(): void
    {
        $this->render('pages/terms', 'Conditions générales d’utilisation');
    }

    private function render(string $template, string $title): void
    {
        View::render($template, [
            'title'     => $title,
            'updatedAt' => self::UPDATED_AT,
            'legal'     => self::legalInfo(),
            'siteUrl'   => (string) Config::get('app.url', ''),
            'delivery'  => ShippingService::isEnabled(),
        ]);
    }

    /**
     * Informations de la section 'legal' de la configuration. Une valeur vide
     * est remplacée par null : la vue affiche alors le marqueur « À COMPLÉTER ».
     *
     * @return array<string, string|null>
     */
    private static function legalInfo(): array
    {
        $keys = ['publisher_name', 'publisher_status', 'publisher_address', 'publisher_siret',
                 'contact_email', 'director', 'host_name', 'host_address', 'host_phone'];

        $info = [];
        foreach ($keys as $key) {
            $value = trim((string) Config::get('legal.' . $key, ''));
            $info[$key] = $value === '' ? null : $value;
        }

        return $info;
    }
}
