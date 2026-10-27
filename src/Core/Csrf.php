<?php
/**
 * Csrf : protection contre les attaques CSRF (Cross-Site Request Forgery).
 *
 * Le risque : un site malveillant contient un formulaire caché qui envoie
 * une requête POST vers notre site. Le navigateur de la victime y joint
 * automatiquement son cookie de session : sans protection, l'action serait
 * exécutée en son nom (supprimer une annonce, envoyer un message...).
 *
 * La parade : chaque session reçoit un jeton secret aléatoire. Nos formulaires
 * le contiennent dans un champ caché. Un site externe ne peut pas le lire, donc
 * il ne peut pas forger une requête valide.
 *
 * Utilisation :
 *   - dans chaque <form method="post"> :  <?= Csrf::field() ?>
 *   - au début de chaque action POST :    Csrf::verify();
 */

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';
    private const FIELD_NAME  = '_csrf';

    /**
     * Retourne le jeton de la session et le crée au premier appel.
     */
    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            // random_bytes = générateur cryptographiquement sûr
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Remplace le jeton par un nouveau. Appelée à la connexion (Auth::login).
     */
    public static function rotate(): void
    {
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }

    /**
     * Champ caché à placer dans chaque formulaire POST.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD_NAME . '" value="' . e(self::token()) . '">';
    }

    /**
     * Vérifie le jeton envoyé avec le formulaire. S'il est absent ou faux, la
     * requête est rejetée (403) et le script s'arrête.
     */
    public static function verify(): void
    {
        $sent = $_POST[self::FIELD_NAME] ?? '';

        // hash_equals compare en temps constant : la durée de la comparaison
        // ne révèle pas combien de caractères sont corrects.
        if (!is_string($sent) || !hash_equals(self::token(), $sent)) {
            View::error(403, 'Votre session a expiré ou le formulaire est invalide. Rechargez la page et réessayez.');
            exit;
        }
    }
}
