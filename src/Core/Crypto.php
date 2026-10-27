<?php
/**
 * Crypto : chiffrement des données sensibles et signature des liens.
 *
 * Tout repose sur la clé secrète app.key (config/config.php), jamais stockée
 * en base : quelqu'un qui volerait une copie de la base ne pourrait ni lire
 * les adresses, ni fabriquer de faux liens.
 *
 *   Crypto::encrypt('12 rue...')  => texte illisible à stocker en base
 *   Crypto::decrypt($stored)      => '12 rue...' (ou null si altéré)
 *   Crypto::sign('unsubscribe:12') / Crypto::verify(...) : signature HMAC
 *
 * Chiffrement : libsodium « secretbox » (XSalsa20-Poly1305), inclus dans PHP.
 * Il est AUTHENTIFIÉ : un texte modifié en base n'est pas déchiffré en un
 * résultat faux, il est simplement refusé (null).
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Crypto
{
    /**
     * Clé dérivée de app.key. On dérive une clé différente par usage
     * (chiffrement, signature) : compromettre l'une n'expose pas l'autre.
     */
    private static function key(string $purpose): string
    {
        $appKey = (string) Config::get('app.key', '');
        if (strlen($appKey) < 32) {
            throw new RuntimeException(
                'app.key manquante ou trop courte dans config/config.php. '
                . 'Générez-en une : php -r "echo bin2hex(random_bytes(32));"'
            );
        }

        return sodium_crypto_generichash($purpose . '|' . $appKey, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    /** Chiffre un texte. Résultat en base64 : nonce aléatoire + texte chiffré. */
    public static function encrypt(string $plain): string
    {
        // Le nonce (nombre aléatoire à usage unique) garantit que chiffrer deux
        // fois la même adresse donne deux résultats différents.
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key('encryption')));
    }

    /** Déchiffre un texte produit par encrypt(), ou retourne null s'il a été altéré. */
    public static function decrypt(string $stored): ?string
    {
        $raw = base64_decode($stored, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, self::key('encryption'));

        return $plain === false ? null : $plain;
    }

    /** Signature HMAC-SHA256 (hexadécimal) d'un message. */
    public static function sign(string $message): string
    {
        return hash_hmac('sha256', $message, self::key('signature'));
    }

    /** Vérifie une signature en temps constant (voir Csrf::verify). */
    public static function verify(string $message, string $signature): bool
    {
        return hash_equals(self::sign($message), $signature);
    }
}
