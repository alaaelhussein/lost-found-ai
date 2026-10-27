<?php
/**
 * Modèle Match : correspondances entre une annonce perdue et une trouvée.
 *
 * Une ligne de `matches` = un couple (perdu, trouvé) avec son score sur 100,
 * calculé par App\Services\MatchingService. Le couple est unique en base
 * (uq_matches_pair) : recalculer un score met simplement la ligne à jour.
 *
 * Statuts : 'suggested' (proposée par l'algorithme), 'accepted', 'rejected'
 * (réservés à une future validation par les utilisateurs).
 */

declare(strict_types=1);

namespace App\Models;

use PDO;

final class MatchModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    /**
     * Enregistre les correspondances calculées pour une annonce.
     *
     *   - chaque correspondance est insérée, ou son score mis à jour ;
     *   - les anciennes suggestions qui ne passent plus le seuil sont
     *     supprimées (sauf si une conversation y est rattachée).
     *
     * @param list<array{item: array, score: int}> $results
     */
    public function saveForItem(int $itemId, string $itemType, array $results): void
    {
        $upsert = $this->db->prepare(
            'INSERT INTO matches (lost_item_id, found_item_id, score)
             VALUES (:lost_item_id, :found_item_id, :score)
             ON DUPLICATE KEY UPDATE score = VALUES(score)'
        );

        $keptIds = [];
        foreach ($results as $result) {
            $otherId = (int) $result['item']['id'];
            [$lostId, $foundId] = $itemType === 'lost' ? [$itemId, $otherId] : [$otherId, $itemId];
            $upsert->execute(['lost_item_id' => $lostId, 'found_item_id' => $foundId, 'score' => $result['score']]);
            $keptIds[] = $otherId;
        }

        // Suggestions devenues obsolètes. $keptIds ne contient que des entiers
        // venant de la base : les insérer dans la requête est sans risque.
        $column = $itemType === 'lost' ? 'lost_item_id' : 'found_item_id';
        $other  = $itemType === 'lost' ? 'found_item_id' : 'lost_item_id';
        $exclude = $keptIds === [] ? '' : ' AND ' . $other . ' NOT IN (' . implode(',', array_map('intval', $keptIds)) . ')';

        $stmt = $this->db->prepare(
            "DELETE FROM matches
             WHERE {$column} = :item_id
               AND status = 'suggested'{$exclude}
               AND NOT EXISTS (SELECT 1 FROM conversations c WHERE c.match_id = matches.id)"
        );
        $stmt->execute(['item_id' => $itemId]);
    }

    /**
     * Correspondances d'une annonce dont l'auteur de l'annonce D'EN FACE n'a
     * pas encore été prévenu par email (notified_at IS NULL).
     *
     * Exemple : Bob publie « sac trouvé » ; on calcule ses correspondances et
     * on trouve « sac perdu » d'Alice. C'est Alice qu'il faut prévenir (Bob voit
     * déjà le résultat à l'écran).
     */
    public function pendingNotifications(int $itemId, string $itemType, int $minScore): array
    {
        $column = $itemType === 'lost' ? 'lost_item_id' : 'found_item_id';
        $other  = $itemType === 'lost' ? 'found_item_id' : 'lost_item_id';

        $stmt = $this->db->prepare(
            "SELECT m.id AS match_id, m.score,
                    other.id AS other_item_id, other.title AS other_title, other.type AS other_type,
                    owner.id AS owner_id, owner.name AS owner_name, owner.email AS owner_email,
                    owner.notify_matches, owner.email_verified_at
             FROM matches m
             INNER JOIN items other ON other.id = m.{$other}
             INNER JOIN users owner ON owner.id = other.user_id
             WHERE m.{$column} = :item_id
               AND m.notified_at IS NULL
               AND m.score >= :min_score
               AND other.status = 'open'"
        );
        $stmt->execute(['item_id' => $itemId, 'min_score' => $minScore]);

        return $stmt->fetchAll();
    }

    /** Marque une correspondance comme notifiée (même si l'email n'a pas été envoyé). */
    public function markNotified(int $matchId): void
    {
        $stmt = $this->db->prepare('UPDATE matches SET notified_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $matchId]);
    }

    /**
     * Nombre de correspondances par annonce d'un utilisateur, pour « Mes
     * annonces ». Ne compte que les annonces d'en face encore ouvertes.
     *
     * @return array<int, int> [id de l'annonce => nombre de correspondances]
     */
    public function countsForUser(int $userId, int $minScore): array
    {
        $stmt = $this->db->prepare(
            "SELECT i.id, COUNT(*) AS total
             FROM items i
             INNER JOIN matches m
                     ON (i.type = 'lost' AND m.lost_item_id = i.id)
                     OR (i.type = 'found' AND m.found_item_id = i.id)
             INNER JOIN items other
                     ON other.id = IF(i.type = 'lost', m.found_item_id, m.lost_item_id)
             WHERE i.user_id = :user_id
               AND m.score >= :min_score
               AND m.status != 'rejected'
               AND other.status = 'open'
             GROUP BY i.id"
        );
        $stmt->execute(['user_id' => $userId, 'min_score' => $minScore]);

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int) $row['id']] = (int) $row['total'];
        }

        return $counts;
    }
}
