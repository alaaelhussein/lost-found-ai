<?php
/**
 * Modèle LoginAttempt : limite le nombre d'essais de mot de passe
 * (protection contre le « brute force », qui consiste à essayer des milliers
 * de mots de passe à la suite).
 *
 * Chaque échec de connexion est enregistré dans `login_attempts`.
 * Deux limites sur une fenêtre glissante de 15 minutes :
 *   - 5 échecs pour un même email  => protège un compte précis ;
 *   - 20 échecs depuis une même IP => freine un attaquant qui essaie beaucoup
 *     d'emails différents. Cette limite est plus haute car plusieurs
 *     utilisateurs légitimes peuvent partager la même IP (wifi d'une université).
 *
 * Le stockage est en base et non en session : un attaquant pourrait sinon
 * contourner la limite en supprimant simplement son cookie.
 */

declare(strict_types=1);

namespace App\Models;

use PDO;

final class LoginAttempt
{
    public const MAX_PER_EMAIL  = 5;
    public const MAX_PER_IP     = 20;
    public const WINDOW_MINUTES = 15;

    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    /**
     * Vrai si l'email ou l'IP a dépassé sa limite d'échecs.
     */
    public function isBlocked(string $email, string $ip): bool
    {
        return $this->countRecent('email', $email) >= self::MAX_PER_EMAIL
            || $this->countRecent('ip_address', $ip) >= self::MAX_PER_IP;
    }

    public function record(string $email, string $ip): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO login_attempts (email, ip_address) VALUES (:email, :ip)'
        );
        $stmt->execute(['email' => $email, 'ip' => $ip]);
    }

    /**
     * Après une connexion réussie : on efface les échecs de cet email, et on
     * en profite pour supprimer les lignes de plus de 24 h (la table reste petite).
     */
    public function clear(string $email): void
    {
        $stmt = $this->db->prepare('DELETE FROM login_attempts WHERE email = :email');
        $stmt->execute(['email' => $email]);

        $this->purgeOld();
    }

    /**
     * Supprime les échecs de plus de 24 h (adresses IP comprises : durée
     * annoncée dans la politique de confidentialité). Retourne le nombre de lignes effacées.
     */
    public function purgeOld(): int
    {
        return (int) $this->db->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
    }

    /**
     * Nombre d'échecs récents pour une colonne donnée ('email' ou 'ip_address').
     */
    private function countRecent(string $column, string $value): int
    {
        // $column ne vient jamais de l'utilisateur (seulement des deux appels
        // ci-dessus) : l'insérer dans la requête est donc sans risque.
        // WINDOW_MINUTES est une constante entière, même raisonnement.
        // On utilise NOW() côté MySQL pour être cohérent avec la valeur
        // par défaut de attempted_at, quel que soit le fuseau horaire de PHP.
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE {$column} = :value
               AND attempted_at > NOW() - INTERVAL " . self::WINDOW_MINUTES . ' MINUTE'
        );
        $stmt->execute(['value' => $value]);

        return (int) $stmt->fetchColumn();
    }
}
