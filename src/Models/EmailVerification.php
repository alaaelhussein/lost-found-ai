<?php
/**
 * Modèle EmailVerification : code à 6 chiffres envoyé par email pour
 * prouver que l'utilisateur possède bien l'adresse saisie à l'inscription.
 *
 * Une seule ligne par compte (clé primaire user_id) : demander un nouveau
 * code remplace l'ancien, qui devient inutilisable.
 *
 * Le code n'est JAMAIS stocké en clair, seulement son HMAC (voir
 * EmailVerifier) : une fuite de la base ne permettrait pas de valider un compte.
 */

declare(strict_types=1);

namespace App\Models;

use PDO;

final class EmailVerification
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    /** Enregistre (ou remplace) le code d'un compte. */
    public function store(int $userId, string $codeHash, int $validMinutes): void
    {
        $stmt = $this->db->prepare(
            'REPLACE INTO email_verifications (user_id, code_hash, expires_at, attempts, sent_at)
             VALUES (:user_id, :code_hash, NOW() + INTERVAL :minutes MINUTE, 0, NOW())'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('code_hash', $codeHash);
        $stmt->bindValue('minutes', $validMinutes, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Code en cours d'un compte, avec deux informations calculées par MySQL
     * (même horloge que expires_at) : expiré ou non, secondes depuis l'envoi.
     */
    public function find(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT user_id, code_hash, attempts,
                    expires_at < NOW() AS expired,
                    TIMESTAMPDIFF(SECOND, sent_at, NOW()) AS seconds_since_sent
             FROM email_verifications
             WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetch() ?: null;
    }

    /** Compte un essai raté. */
    public function addAttempt(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE email_verifications SET attempts = attempts + 1 WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    /** Supprime les codes expirés (bin/cleanup.php). Retourne le nombre de lignes effacées. */
    public function purgeExpired(): int
    {
        return (int) $this->db->exec('DELETE FROM email_verifications WHERE expires_at < NOW()');
    }

    /** Supprime le code (après validation réussie). */
    public function delete(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM email_verifications WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }
}
