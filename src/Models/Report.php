<?php
/**
 * Modèle Report : signalements d'annonces par les utilisateurs.
 *
 * Un utilisateur ne peut signaler une même annonce qu'une fois (clé unique
 * item_id + reporter_id) : impossible de faire disparaître une annonce en la
 * signalant 50 fois. Les signalements sont traités par un administrateur sur
 * /admin/reports (AdminController).
 */

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Report
{
    /** Motifs proposés dans le formulaire : code en base => libellé affiché. */
    public const REASONS = [
        'spam'          => 'Spam ou publicité',
        'fraud'         => 'Arnaque ou tentative de fraude',
        'inappropriate' => 'Contenu choquant ou illégal',
        'personal_data' => 'Données personnelles exposées (adresse, téléphone, papiers d’identité…)',
        'other'         => 'Autre raison',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    /**
     * Enregistre un signalement. Retourne false si cette personne avait déjà
     * signalé l'annonce (INSERT IGNORE ignore le doublon sans erreur).
     */
    public function create(int $itemId, int $reporterId, string $reason, ?string $details): bool
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO reports (item_id, reporter_id, reason, details)
             VALUES (:item_id, :reporter_id, :reason, :details)'
        );
        $stmt->execute([
            'item_id'     => $itemId,
            'reporter_id' => $reporterId,
            'reason'      => $reason,
            'details'     => $details,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Signalements à traiter, regroupés par annonce (une annonce signalée par
     * 3 personnes apparaît une seule fois, avec le nombre de signalements).
     */
    public function openGroupedByItem(): array
    {
        $stmt = $this->db->query(
            "SELECT i.id AS item_id, i.title, i.type, i.status, i.photo_path, i.created_at AS item_created_at,
                    i.user_id AS author_id, author.name AS author_name, author.email AS author_email,
                    COUNT(r.id) AS report_count,
                    GROUP_CONCAT(DISTINCT r.reason ORDER BY r.reason SEPARATOR ',') AS reasons,
                    GROUP_CONCAT(r.details ORDER BY r.created_at SEPARATOR '\n---\n') AS details,
                    MIN(r.created_at) AS first_reported_at
             FROM reports r
             INNER JOIN items i ON i.id = r.item_id
             INNER JOIN users author ON author.id = i.user_id
             WHERE r.status = 'open'
             GROUP BY i.id
             ORDER BY report_count DESC, first_reported_at"
        );

        return $stmt->fetchAll();
    }

    /** Classe les signalements d'une annonce (annonce vérifiée et conservée). */
    public function closeForItem(int $itemId): void
    {
        $stmt = $this->db->prepare("UPDATE reports SET status = 'closed' WHERE item_id = :item_id AND status = 'open'");
        $stmt->execute(['item_id' => $itemId]);
    }

    /** Nombre de signalements en attente (pastille dans le menu de l'administrateur). */
    public function countOpen(): int
    {
        return (int) $this->db->query("SELECT COUNT(DISTINCT item_id) FROM reports WHERE status = 'open'")->fetchColumn();
    }
}
