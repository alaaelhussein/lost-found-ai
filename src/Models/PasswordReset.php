<?php
/**
 * Modèle PasswordReset : liens « mot de passe oublié » envoyés par email.
 *
 * Une seule ligne par compte (clé primaire user_id) : redemander un lien
 * remplace l'ancien, qui devient inutilisable.
 *
 * Le jeton du lien n'est JAMAIS stocké en clair, seulement son HMAC (voir
 * PasswordController::hash) : une fuite de la base ne permettrait pas de
 * réinitialiser le mot de passe de quelqu'un.
 */

declare(strict_types=1);

namespace App\Models;

use PDO;

final class PasswordReset
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    /** Enregistre (ou remplace) le lien d'un compte. */
    public function store(int $userId, string $tokenHash, int $validMinutes): void
    {
        $stmt = $this->db->prepare(
            'REPLACE INTO password_resets (user_id, token_hash, expires_at, created_at)
             VALUES (:user_id, :token_hash, NOW() + INTERVAL :minutes MINUTE, NOW())'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('token_hash', $tokenHash);
        $stmt->bindValue('minutes', $validMinutes, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Compte associé à un jeton encore valide, ou null (inconnu ou expiré).
     * L'expiration est comparée par MySQL (même horloge que expires_at).
     */
    public function findValidUserId(string $tokenHash): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT user_id FROM password_resets
             WHERE token_hash = :token_hash AND expires_at > NOW()'
        );
        $stmt->execute(['token_hash' => $tokenHash]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** Secondes écoulées depuis le dernier lien envoyé à ce compte (null = aucun). */
    public function secondsSinceLast(int $userId): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) FROM password_resets WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        $seconds = $stmt->fetchColumn();

        return $seconds === false ? null : (int) $seconds;
    }

    /** Supprime le lien d'un compte (après utilisation : un lien ne sert qu'une fois). */
    public function delete(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    /** Supprime les liens expirés (bin/cleanup.php). Retourne le nombre de lignes effacées. */
    public function purgeExpired(): int
    {
        return (int) $this->db->exec('DELETE FROM password_resets WHERE expires_at < NOW()');
    }
}
