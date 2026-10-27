<?php
/**
 * Modèle Conversation : accès aux conversations privées.
 */

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Conversation
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    public function findOrCreateForMatch(int $matchId): int
    {
        $stmt = $this->db->prepare('INSERT IGNORE INTO conversations (match_id) VALUES (:match_id)');
        $stmt->execute(['match_id' => $matchId]);

        $stmt = $this->db->prepare('SELECT id FROM conversations WHERE match_id = :match_id');
        $stmt->execute(['match_id' => $matchId]);

        return (int) $stmt->fetchColumn();
    }

    public function findOrCreateForItem(int $itemId, int $initiatorId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO conversations (item_id, initiator_id)
             VALUES (:item_id, :initiator_id)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        );
        $stmt->execute(['item_id' => $itemId, 'initiator_id' => $initiatorId]);

        return (int) $this->db->lastInsertId();
    }

    public function findForUser(int $conversationId, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.match_id, c.item_id, c.initiator_id,
                    COALESCE(m.lost_item_id, c.item_id) AS lost_item_id,
                    COALESCE(m.found_item_id, c.item_id) AS found_item_id,
                    COALESCE(lost.title, target.title) AS lost_title,
                    COALESCE(found.title, target.title) AS found_title,
                    CASE
                        WHEN c.item_id IS NOT NULL AND target.user_id = :direct_target_user
                            THEN c.initiator_id
                        WHEN c.item_id IS NOT NULL THEN target.user_id
                        WHEN lost.user_id = :current_user_select THEN found.user_id
                        ELSE lost.user_id
                    END AS other_user_id,
                    CASE
                        WHEN c.item_id IS NOT NULL AND target.user_id = :direct_target_name
                            THEN initiator.name
                        WHEN c.item_id IS NOT NULL THEN target_user.name
                        WHEN lost.user_id = :current_user_name THEN found_user.name
                        ELSE lost_user.name
                    END AS other_user_name
             FROM conversations c
             LEFT JOIN matches m ON m.id = c.match_id
             LEFT JOIN items lost ON lost.id = m.lost_item_id
             LEFT JOIN items found ON found.id = m.found_item_id
             LEFT JOIN items target ON target.id = c.item_id
             LEFT JOIN users lost_user ON lost_user.id = lost.user_id
             LEFT JOIN users found_user ON found_user.id = found.user_id
             LEFT JOIN users target_user ON target_user.id = target.user_id
             LEFT JOIN users initiator ON initiator.id = c.initiator_id
             WHERE c.id = :conversation_id
               AND (
                    lost.user_id = :user_id_lost
                    OR found.user_id = :user_id_found
                    OR target.user_id = :user_id_target
                    OR c.initiator_id = :user_id_initiator
               )
             LIMIT 1'
        );
        $stmt->execute([
            'direct_target_user' => $userId,
            'direct_target_name' => $userId,
            'current_user_select' => $userId,
            'current_user_name' => $userId,
            'conversation_id' => $conversationId,
            'user_id_lost' => $userId,
            'user_id_found' => $userId,
            'user_id_target' => $userId,
            'user_id_initiator' => $userId,
        ]);

        return $stmt->fetch() ?: null;
    }

    public function findAllForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.created_at,
                    COALESCE(lost.title, target.title) AS lost_title,
                    COALESCE(found.title, target.title) AS found_title,
                    CASE
                        WHEN c.item_id IS NOT NULL AND target.user_id = :direct_target_user
                            THEN initiator.name
                        WHEN c.item_id IS NOT NULL THEN target_user.name
                        WHEN lost.user_id = :current_user_name THEN found_user.name
                        ELSE lost_user.name
                    END AS other_user_name
             FROM conversations c
             LEFT JOIN matches m ON m.id = c.match_id
             LEFT JOIN items lost ON lost.id = m.lost_item_id
             LEFT JOIN items found ON found.id = m.found_item_id
             LEFT JOIN items target ON target.id = c.item_id
             LEFT JOIN users lost_user ON lost_user.id = lost.user_id
             LEFT JOIN users found_user ON found_user.id = found.user_id
             LEFT JOIN users target_user ON target_user.id = target.user_id
             LEFT JOIN users initiator ON initiator.id = c.initiator_id
             WHERE target.user_id = :user_id_target
                OR c.initiator_id = :user_id_initiator
                OR lost.user_id = :current_user_lost
                OR found.user_id = :current_user_found
             ORDER BY c.created_at DESC'
        );
        $stmt->execute([
            'direct_target_user' => $userId,
            'current_user_name' => $userId,
            'user_id_target' => $userId,
            'user_id_initiator' => $userId,
            'current_user_lost' => $userId,
            'current_user_found' => $userId,
        ]);

        return $stmt->fetchAll();
    }

    public function messages(int $conversationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.id, m.content, m.sender_id, m.created_at, u.name AS sender_name
             FROM messages m
             INNER JOIN users u ON u.id = m.sender_id
             WHERE m.conversation_id = :conversation_id
             ORDER BY m.created_at ASC'
        );
        $stmt->execute(['conversation_id' => $conversationId]);

        return $stmt->fetchAll();
    }

    public function addMessage(int $conversationId, int $senderId, string $content): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO messages (conversation_id, sender_id, content)
             VALUES (:conversation_id, :sender_id, :content)'
        );
        $stmt->execute([
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'content' => $content,
        ]);
    }
}
