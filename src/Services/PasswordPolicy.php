<?php
/**
 * PasswordPolicy : règles de choix d'un mot de passe, au même endroit.
 *
 * Utilisées à trois endroits qui doivent appliquer EXACTEMENT les mêmes règles :
 *   - l'inscription (AuthController) ;
 *   - la réinitialisation après « mot de passe oublié » (PasswordController) ;
 *   - le changement de mot de passe depuis « Mon compte » (AccountController).
 *
 *     $errors = PasswordPolicy::validate($password, $confirm);
 *     // [] si tout va bien, sinon ['password' => ...] ou ['password_confirm' => ...]
 */

declare(strict_types=1);

namespace App\Services;

final class PasswordPolicy
{
    public const MIN_LENGTH = 8;
    public const MAX_BYTES = 72; // bcrypt ignore tout ce qui dépasse 72 octets

    /**
     * @return array<string, string> Messages indexés par nom de champ (vide = valide)
     */
    public static function validate(string $password, string $confirm): array
    {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            return ['password' => 'Le mot de passe doit contenir au moins ' . self::MIN_LENGTH . ' caractères.'];
        }
        if (strlen($password) > self::MAX_BYTES) {
            return ['password' => 'Le mot de passe est trop long (72 caractères maximum).'];
        }
        // \pL = n'importe quelle lettre, y compris accentuée
        if (!preg_match('/\pL/u', $password) || !preg_match('/\d/', $password)) {
            return ['password' => 'Le mot de passe doit contenir au moins une lettre et un chiffre.'];
        }
        if ($password !== $confirm) {
            return ['password_confirm' => 'Les deux mots de passe ne correspondent pas.'];
        }

        return [];
    }
}
