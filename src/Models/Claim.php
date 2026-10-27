<?php
/**
 * Modèle Claim : demandes de restitution d'un objet trouvé.
 *
 * Parcours :
 *   1. une personne pense qu'un objet trouvé est le sien : elle répond à la
 *      question de vérification et décrit un détail non visible sur l'annonce
 *      (demande « pending ») ;
 *   2. la personne qui a trouvé l'objet compare avec l'objet qu'elle a en main
 *      et accepte (« approved ») ou refuse (« rejected ») ;
 *   3. si la demande est acceptée, le propriétaire peut commander la livraison
 *      (voir Order) ou organiser une remise en main propre par messagerie.
 *
 * Une seule demande par personne et par objet (uq_claims_item_claimant), et
 * une seule demande acceptée par objet (vérifié par ClaimController).
 */

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Claim
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    public function create(int $itemId, int $claimantId, ?string $answer, string $proof, ?string $proofPhoto): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO claims (item_id, claimant_id, answer, proof, proof_photo)
             VALUES (:item_id, :claimant_id, :answer, :proof, :proof_photo)'
        );
        $stmt->execute([
            'item_id'     => $itemId,
            'claimant_id' => $claimantId,
            'answer'      => $answer,
            'proof'       => $proof,
            'proof_photo' => $proofPhoto,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Une demande avec l'annonce et les deux personnes concernées.
     * finder_id = auteur de l'annonce « trouvé » (celui qui décide).
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, i.title AS item_title, i.user_id AS finder_id, i.status AS item_status,
                    claimant.name AS claimant_name, claimant.email AS claimant_email,
                    finder.name AS finder_name, finder.email AS finder_email,
                    o.id AS order_id, o.status AS order_status
             FROM claims c
             INNER JOIN items i ON i.id = c.item_id
             INNER JOIN users claimant ON claimant.id = c.claimant_id
             INNER JOIN users finder ON finder.id = i.user_id
             LEFT JOIN orders o ON o.claim_id = c.id
             WHERE c.id = :id'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** Demande d'une personne pour un objet donné (pour afficher son statut). */
    public function findByItemAndClaimant(int $itemId, int $claimantId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.status, c.created_at, o.id AS order_id, o.status AS order_status
             FROM claims c
             LEFT JOIN orders o ON o.claim_id = c.id
             WHERE c.item_id = :item_id AND c.claimant_id = :claimant_id'
        );
        $stmt->execute(['item_id' => $itemId, 'claimant_id' => $claimantId]);

        return $stmt->fetch() ?: null;
    }

    /** Toutes les demandes reçues pour un objet (vue de la personne qui l'a trouvé). */
    public function forItem(int $itemId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.answer, c.proof, c.proof_photo, c.status, c.created_at,
                    u.name AS claimant_name, o.id AS order_id, o.status AS order_status
             FROM claims c
             INNER JOIN users u ON u.id = c.claimant_id
             LEFT JOIN orders o ON o.claim_id = c.id
             WHERE c.item_id = :item_id
             ORDER BY FIELD(c.status, \'approved\', \'pending\', \'rejected\'), c.created_at'
        );
        $stmt->execute(['item_id' => $itemId]);

        return $stmt->fetchAll();
    }

    /** Demandes faites par une personne (« Mes annonces » > « Mes demandes »). */
    public function forClaimant(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.status, c.created_at, i.id AS item_id, i.title AS item_title,
                    o.id AS order_id, o.status AS order_status
             FROM claims c
             INNER JOIN items i ON i.id = c.item_id
             LEFT JOIN orders o ON o.claim_id = c.id
             WHERE c.claimant_id = :user_id
             ORDER BY c.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    /** Nombre de demandes par statut, pour le tableau de bord administrateur. */
    public function countByStatus(): array
    {
        $stmt = $this->db->query(
            "SELECT SUM(status = 'pending') AS pending,
                    SUM(status = 'approved') AS approved,
                    SUM(status = 'rejected') AS rejected
             FROM claims"
        );

        return array_map('intval', $stmt->fetch());
    }

    /** Vrai si une demande a déjà été acceptée pour cet objet. */
    public function hasApproved(int $itemId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM claims WHERE item_id = :item_id AND status = 'approved' LIMIT 1");
        $stmt->execute(['item_id' => $itemId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Accepte ou refuse une demande encore en attente. La condition
     * « status = 'pending' » dans le UPDATE évite qu'un double clic ne
     * change une décision déjà prise.
     */
    public function decide(int $id, string $status): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE claims SET status = :status, decided_at = NOW() WHERE id = :id AND status = 'pending'"
        );
        $stmt->execute(['status' => $status, 'id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Photos de preuve (privées) des demandes reçues pour une annonce : à
     * effacer du disque quand l'annonce est supprimée (les lignes claims
     * partent en cascade, mais pas les fichiers).
     *
     * @return string[]
     */
    public function proofPhotosForItem(int $itemId): array
    {
        $stmt = $this->db->prepare(
            'SELECT proof_photo FROM claims WHERE item_id = :item_id AND proof_photo IS NOT NULL'
        );
        $stmt->execute(['item_id' => $itemId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Photos de preuve liées à un compte : celles qu'il a envoyées ET celles
     * reçues sur ses annonces (supprimées en cascade avec le compte).
     *
     * @return string[]
     */
    public function proofPhotosLinkedToUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.proof_photo
             FROM claims c
             INNER JOIN items i ON i.id = c.item_id
             WHERE (c.claimant_id = :claimant_id OR i.user_id = :owner_id) AND c.proof_photo IS NOT NULL'
        );
        $stmt->execute(['claimant_id' => $userId, 'owner_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
