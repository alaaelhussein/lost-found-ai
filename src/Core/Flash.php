<?php
/**
 * Flash : messages à usage unique affichés sur la page SUIVANTE.
 *
 * Cas typique : après une inscription réussie, on redirige vers l'accueil et
 * on veut y afficher « Bienvenue ! ». Le message est stocké en session avant la
 * redirection, puis lu et effacé par le layout à l'affichage suivant.
 *
 *     Flash::add('success', 'Votre compte a été créé.');
 *     redirect('/');
 *
 * Types utilisés par le CSS : 'success', 'error', 'info'.
 */

declare(strict_types=1);

namespace App\Core;

final class Flash
{
    private const SESSION_KEY = '_flash';

    public static function add(string $type, string $message): void
    {
        $_SESSION[self::SESSION_KEY][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Retourne tous les messages en attente et les supprime de la session.
     *
     * @return array<int, array{type: string, message: string}>
     */
    public static function pull(): array
    {
        $messages = $_SESSION[self::SESSION_KEY] ?? [];
        unset($_SESSION[self::SESSION_KEY]);

        return $messages;
    }
}
