<?php
/**
 * EmailVerifier : vérification de l'adresse email par code à 6 chiffres.
 *
 * Parcours :
 *   1. inscription => sendCode() : un code aléatoire est envoyé par email ;
 *   2. l'utilisateur le saisit sur /verify-email => check() ;
 *   3. code correct => users.email_verified_at est rempli.
 *
 * Sécurité :
 *   - code tiré avec random_int (générateur cryptographique), 1 million de possibilités ;
 *   - valable 15 minutes, 5 essais maximum : au-delà, il faut en redemander un.
 *     Avec 5 essais, la probabilité de deviner est de 5 / 1 000 000 ;
 *   - stocké sous forme de HMAC lié au compte : même avec la base, impossible
 *     de retrouver le code ou de l'utiliser pour un autre compte ;
 *   - un nouvel envoi au plus toutes les 60 secondes (anti-spam de la boîte mail).
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Models\EmailVerification;
use App\Models\User;

final class EmailVerifier
{
    public const VALID_MINUTES = 15;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_DELAY = 60; // secondes

    // Résultats possibles de check()
    public const OK = 'ok';
    public const WRONG = 'wrong';
    public const EXPIRED = 'expired';
    public const TOO_MANY = 'too_many';

    /**
     * Génère un code, l'enregistre (haché) et l'envoie par email.
     * Retourne false si l'email n'a pas pu partir.
     */
    public function sendCode(array $user): bool
    {
        // str_pad : 42 devient « 000042 », le code a toujours 6 chiffres
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        (new EmailVerification())->store((int) $user['id'], $this->hash((int) $user['id'], $code), self::VALID_MINUTES);

        return (new Notifier())->verificationCode($user, $code);
    }

    /** Secondes à attendre avant de pouvoir redemander un code (0 = possible tout de suite). */
    public function secondsBeforeResend(int $userId): int
    {
        $pending = (new EmailVerification())->find($userId);
        if ($pending === null) {
            return 0;
        }

        return max(0, self::RESEND_DELAY - (int) $pending['seconds_since_sent']);
    }

    /** Vérifie le code saisi. Retourne une des constantes OK, WRONG, EXPIRED, TOO_MANY. */
    public function check(int $userId, string $code): string
    {
        $verifications = new EmailVerification();
        $pending = $verifications->find($userId);

        if ($pending === null || (int) $pending['expired'] === 1) {
            return self::EXPIRED;
        }
        if ((int) $pending['attempts'] >= self::MAX_ATTEMPTS) {
            return self::TOO_MANY;
        }

        // Comparaison en temps constant (hash_equals) : le temps de réponse ne
        // révèle pas combien de caractères sont corrects.
        if (!hash_equals($pending['code_hash'], $this->hash($userId, $code))) {
            $verifications->addAttempt($userId);

            return (int) $pending['attempts'] + 1 >= self::MAX_ATTEMPTS ? self::TOO_MANY : self::WRONG;
        }

        $verifications->delete($userId);
        (new User())->markEmailVerified($userId);

        return self::OK;
    }

    /** HMAC du code lié au compte : « 123456 » n'a pas le même hash pour deux comptes. */
    private function hash(int $userId, string $code): string
    {
        return Crypto::sign('email-verification:' . $userId . ':' . $code);
    }
}
