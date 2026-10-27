<?php
/**
 * Modèle Order : commande de livraison d'un objet trouvé à son propriétaire.
 *
 * Cycle de vie (colonne status) :
 *
 *   pending_payment ──paiement──> paid ──dépôt du colis──> shipped ──réception──> delivered
 *
 *   - pending_payment : commande créée, en attente du paiement ;
 *   - paid      : payée ; la personne qui a trouvé l'objet reçoit l'étiquette ;
 *   - shipped   : colis déposé chez le transporteur (numéro de suivi) ;
 *   - delivered : le propriétaire confirme la réception ; le pourboire
 *                 devient disponible (tip_status = 'available').
 *
 * Chaque changement d'état vérifie l'état précédent dans le WHERE du UPDATE
 * (ex. « AND status = 'paid' ») : une commande ne peut pas sauter d'étape, et
 * un paiement reçu deux fois (double clic, webhook rejoué) n'est compté qu'une fois.
 *
 * Montants en centimes (entiers) : 4,49 € est stocké 449, sans erreur d'arrondi.
 */

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Order
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    /** @param array<string, mixed> $data Colonnes de la commande (voir OrderController::store) */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO orders
                (claim_id, buyer_id, finder_id, carrier, delivery_mode, relay_point_id, relay_point_label,
                 recipient_name, address_encrypted, shipping_cents, tip_cents, total_cents, payment_provider)
             VALUES
                (:claim_id, :buyer_id, :finder_id, :carrier, :delivery_mode, :relay_point_id, :relay_point_label,
                 :recipient_name, :address_encrypted, :shipping_cents, :tip_cents, :total_cents, :payment_provider)'
        );
        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }

    /** Commande avec l'objet et les deux personnes (noms et emails pour les notifications). */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT o.*, c.item_id, i.title AS item_title,
                    buyer.name AS buyer_name, buyer.email AS buyer_email,
                    finder.name AS finder_name, finder.email AS finder_email
             FROM orders o
             INNER JOIN claims c ON c.id = o.claim_id
             INNER JOIN items i ON i.id = c.item_id
             INNER JOIN users buyer ON buyer.id = o.buyer_id
             INNER JOIN users finder ON finder.id = o.finder_id
             WHERE o.id = :id'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** Retrouve une commande par la référence du paiement (webhook Stripe). */
    public function findIdByPaymentRef(string $ref): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM orders WHERE payment_ref = :ref LIMIT 1');
        $stmt->execute(['ref' => $ref]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** Colis à expédier ou expédiés par une personne (« Mes annonces »). */
    public function forFinder(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.id, o.status, o.tip_cents, o.tip_status, o.carrier, o.created_at, i.title AS item_title
             FROM orders o
             INNER JOIN claims c ON c.id = o.claim_id
             INNER JOIN items i ON i.id = c.item_id
             WHERE o.finder_id = :user_id AND o.status != 'pending_payment'
             ORDER BY o.created_at DESC"
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    /**
     * Livraisons payées mais pas encore reçues où le compte est acheteur ou
     * expéditeur. Tant qu'il y en a, le compte ne peut pas être supprimé
     * (voir AccountService::blockingReason).
     */
    public function countActiveForUser(int $userId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM orders
             WHERE (buyer_id = :buyer_id OR finder_id = :finder_id) AND status IN ('paid', 'shipped')"
        );
        $stmt->execute(['buyer_id' => $userId, 'finder_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /** Livraisons payées mais pas encore reçues pour une annonce (voir ItemController::delete). */
    public function countActiveForItem(int $itemId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM orders o
             INNER JOIN claims c ON c.id = o.claim_id
             WHERE c.item_id = :item_id AND o.status IN ('paid', 'shipped')"
        );
        $stmt->execute(['item_id' => $itemId]);

        return (int) $stmt->fetchColumn();
    }

    /** Statistiques des livraisons et pourboires, pour le tableau de bord administrateur. */
    public function stats(): array
    {
        $stmt = $this->db->query(
            "SELECT SUM(status = 'delivered') AS delivered,
                    SUM(status IN ('paid', 'shipped')) AS in_progress,
                    COALESCE(SUM(IF(tip_status = 'available', tip_cents, 0)), 0) AS tips_available_cents,
                    COALESCE(SUM(IF(tip_status = 'paid', tip_cents, 0)), 0) AS tips_paid_cents
             FROM orders"
        );

        return array_map('intval', $stmt->fetch());
    }

    public function setPaymentRef(int $id, string $ref): void
    {
        $stmt = $this->db->prepare('UPDATE orders SET payment_ref = :ref WHERE id = :id');
        $stmt->execute(['ref' => $ref, 'id' => $id]);
    }

    /** pending_payment => paid. Retourne false si la commande était déjà payée. */
    public function markPaid(int $id, string $trackingNumber): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE orders SET status = 'paid', paid_at = NOW(), tracking_number = :tracking
             WHERE id = :id AND status = 'pending_payment'"
        );
        $stmt->execute(['tracking' => $trackingNumber, 'id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /** paid => shipped */
    public function markShipped(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE orders SET status = 'shipped', shipped_at = NOW() WHERE id = :id AND status = 'paid'"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * shipped => delivered. Le pourboire devient disponible et l'adresse de
     * livraison est effacée : elle n'est plus utile (minimisation des données, RGPD).
     */
    public function markDelivered(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE orders
             SET status = 'delivered', delivered_at = NOW(), tip_status = 'available', address_encrypted = NULL
             WHERE id = :id AND status = 'shipped'"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
