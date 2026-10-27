<?php
/**
 * Config : accès en lecture à la configuration de l'application.
 *
 * Le fichier config/config.php retourne un tableau PHP. Cette classe le charge
 * une seule fois (au démarrage, dans src/bootstrap.php) puis permet de lire une
 * valeur avec une « clé à points » :
 *
 *     Config::get('db.host')          // => '127.0.0.1'
 *     Config::get('app.debug', false) // => true, ou false si la clé n'existe pas
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Config
{
    /** Contenu de config/config.php, null tant qu'il n'est pas chargé. */
    private static ?array $items = null;

    /**
     * Charge le fichier de configuration. Appelé une seule fois par bootstrap.php.
     */
    public static function load(string $file): void
    {
        if (!is_file($file)) {
            throw new RuntimeException(
                'Fichier de configuration manquant : copiez config/config.example.php en config/config.php.'
            );
        }
        self::$items = require $file;
    }

    /**
     * Retourne la valeur associée à une clé à points, ou $default si elle n'existe pas.
     *
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $value = self::$items ?? [];

        // 'db.host' => on descend dans ['db'] puis ['host']
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
