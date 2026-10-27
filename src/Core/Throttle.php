<?php
/**
 * Throttle : coupe une action répétée trop souvent (spam, abus, robots).
 *
 * Utilisation au début d'une action POST, après Csrf::verify() :
 *
 *     Throttle::check('message', 'user:' . Auth::id(), 20, 300);
 *     // au-delà de 20 messages en 5 minutes => page 429 et arrêt du script
 *
 * Chaque appel compte comme une tentative, même si la suite échoue (formulaire
 * invalide...) : un robot qui envoie des données fausses en boucle est freiné
 * lui aussi. Les compteurs sont stockés en base (voir Models/RateLimit.php).
 */

declare(strict_types=1);

namespace App\Core;

use App\Models\RateLimit;

final class Throttle
{
    public static function check(string $action, string $key, int $max, int $windowSeconds): void
    {
        $limits = new RateLimit();

        if ($limits->tooMany($action, $key, $max, $windowSeconds)) {
            header('Retry-After: ' . $windowSeconds);
            View::error(429, 'Trop de demandes en peu de temps. Patientez quelques minutes avant de réessayer.');
            exit;
        }

        $limits->hit($action, $key);
    }
}
