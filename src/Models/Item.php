<?php
/**
 * Modèle Item : requêtes SQL liées aux annonces perdues et trouvées.
 */

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Item
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    public function create(
        int $userId,
        string $type,
        string $title,
        string $description,
        string $category,
        string $color,
        string $location,
        string $dateEvent,
        ?string $photoPath,
        ?string $verificationQuestion = null // objets trouvés : question posée au propriétaire
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO items
                (user_id, type, title, description, category, color, location_label, date_event, photo_path,
                 verification_question)
             VALUES
                (:user_id, :type, :title, :description, :category, :color, :location_label, :date_event, :photo_path,
                 :verification_question)'
        );
        $stmt->execute([
            'user_id'       => $userId,
            'type'          => $type,
            'title'         => $title,
            'description'   => $description !== '' ? $description : null,
            'category'      => $category,
            'color'         => $color !== '' ? $color : null,
            'location_label'=> $location,
            'date_event'    => $dateEvent,
            'photo_path'    => $photoPath,
            'verification_question' => $verificationQuestion !== '' ? $verificationQuestion : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function search(array $filters = [], int $limit = 24): array
    {
        $where = ["i.status != 'resolved'"];
        $params = [];

        if (($filters['type'] ?? '') === 'lost' || ($filters['type'] ?? '') === 'found') {
            $where[] = 'i.type = :type';
            $params['type'] = $filters['type'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(i.title LIKE :query_title OR i.description LIKE :query_description OR i.location_label LIKE :query_location)';
            // % et _ sont des jokers pour LIKE : on les échappe pour qu'une
            // recherche « 100% » cherche bien le texte « 100% ».
            $query = '%' . addcslashes(mb_substr($filters['q'], 0, 100), '%_\\') . '%';
            $params['query_title'] = $query;
            $params['query_description'] = $query;
            $params['query_location'] = $query;
        }
        if (($filters['category'] ?? '') !== '') {
            $where[] = 'i.category = :category';
            $params['category'] = $filters['category'];
        }

        $limit = max(1, min($limit, 100));
        $stmt = $this->db->prepare(
            'SELECT i.id, i.user_id, i.type, i.title, i.description, i.category,
                    i.color, i.location_label, i.date_event, i.photo_path,
                    i.status, i.created_at, u.name AS user_name
             FROM items i
             INNER JOIN users u ON u.id = i.user_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY i.created_at DESC
             LIMIT ' . $limit
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** Statistiques globales, pour le tableau de bord administrateur. */
    public function stats(): array
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) AS total,
                    SUM(type = 'lost') AS lost,
                    SUM(type = 'found') AS found,
                    SUM(status = 'open') AS open,
                    SUM(status = 'matched') AS matched,
                    SUM(status = 'resolved') AS resolved
             FROM items"
        );

        return array_map('intval', $stmt->fetch());
    }

    /**
     * Toutes les annonces (y compris résolues) pour la liste d'administration,
     * avec leur auteur. $search filtre sur le titre, le lieu ou l'email de l'auteur.
     */
    public function searchAdmin(string $search, int $limit = 100): array
    {
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE i.title LIKE :search OR i.location_label LIKE :search OR u.email LIKE :search';
            $params['search'] = '%' . addcslashes(mb_substr($search, 0, 100), '%_\\') . '%';
        }

        $stmt = $this->db->prepare(
            "SELECT i.id, i.type, i.title, i.status, i.location_label, i.created_at,
                    u.id AS author_id, u.name AS author_name, u.email AS author_email
             FROM items i
             INNER JOIN users u ON u.id = i.user_id
             $where
             ORDER BY i.created_at DESC
             LIMIT " . max(1, min($limit, 200))
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT i.id, i.user_id, i.type, i.title, i.description, i.category,
                    i.color, i.location_label, i.latitude, i.longitude,
                    i.date_event, i.photo_path,
                    i.status, i.created_at, i.updated_at, u.name AS user_name
             FROM items i
             INNER JOIN users u ON u.id = i.user_id
             WHERE i.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Annonces pouvant correspondre à $item : type opposé, encore ouvertes,
     * publiées par quelqu'un d'autre, et dans une fenêtre de dates plausible
     * (le score final est calculé en PHP par MatchingService).
     *
     * Fenêtre : un objet est trouvé entre 1 jour avant (date approximative)
     * et 90 jours après sa perte.
     */
    public function findMatchCandidates(array $item, int $limit = 500): array
    {
        $date = (string) $item['date_event'];
        if ($item['type'] === 'lost') {
            $type = 'found';
            $from = date('Y-m-d', strtotime($date . ' -1 day'));
            $to   = date('Y-m-d', strtotime($date . ' +90 days'));
        } else {
            $type = 'lost';
            $from = date('Y-m-d', strtotime($date . ' -90 days'));
            $to   = date('Y-m-d', strtotime($date . ' +1 day'));
        }

        $stmt = $this->db->prepare(
            "SELECT i.id, i.user_id, i.type, i.title, i.description, i.category,
                    i.color, i.location_label, i.latitude, i.longitude,
                    i.date_event, i.photo_path, i.status, i.created_at, u.name AS user_name
             FROM items i
             INNER JOIN users u ON u.id = i.user_id
             WHERE i.type = :type
               AND i.status = 'open'
               AND i.user_id != :user_id
               AND i.date_event BETWEEN :date_from AND :date_to
             ORDER BY i.created_at DESC
             LIMIT " . max(1, min($limit, 1000))
        );
        $stmt->execute([
            'type'      => $type,
            'user_id'   => (int) $item['user_id'],
            'date_from' => $from,
            'date_to'   => $to,
        ]);

        return $stmt->fetchAll();
    }

    /** Toutes les annonces ouvertes (recalcul complet des correspondances). */
    public function findAllOpen(): array
    {
        return $this->db->query(
            "SELECT id, user_id, type, title, description, category, color, location_label,
                    latitude, longitude, date_event, photo_path, status
             FROM items
             WHERE status = 'open'
             ORDER BY id"
        )->fetchAll();
    }

    public function findByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, user_id, type, title, description, category, color,
                    location_label, date_event, photo_path, status, created_at
             FROM items
             WHERE user_id = :user_id
             ORDER BY created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function findOpenByUserAndType(int $userId, string $type): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, title, type FROM items
             WHERE user_id = :user_id AND type = :type AND status = 'open'
             ORDER BY created_at DESC"
        );
        $stmt->execute(['user_id' => $userId, 'type' => $type]);

        return $stmt->fetchAll();
    }

    public function updateStatus(int $id, int $userId, string $status): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE items SET status = :status
             WHERE id = :id AND user_id = :user_id"
        );
        $stmt->execute(['status' => $status, 'id' => $id, 'user_id' => $userId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Changement de statut décidé par l'application (et non par l'auteur) :
     * 'matched' quand une livraison est payée (plus de nouvelles demandes),
     * 'resolved' quand l'objet est livré.
     */
    public function setStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE items SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /**
     * Question de vérification d'un objet trouvé (ex. « Quel porte-clé y a-t-il
     * dessus ? »). Lue séparément : elle n'est affichée que sur le formulaire
     * de demande de restitution, jamais sur la page publique.
     */
    public function findVerificationQuestion(int $id): ?string
    {
        $stmt = $this->db->prepare('SELECT verification_question FROM items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $question = $stmt->fetchColumn();

        return is_string($question) && $question !== '' ? $question : null;
    }

    public function deleteOwned(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM items WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);

        return $stmt->rowCount() > 0;
    }

    /** Suppression par la modération (sans condition d'auteur : AdminController vérifie le rôle). */
    public function deleteById(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM items WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Photos (publiques) de toutes les annonces d'un compte, pour les effacer
     * du disque lors de la suppression du compte (AccountService).
     *
     * @return string[]
     */
    public function photoPathsByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT photo_path FROM items WHERE user_id = :user_id AND photo_path IS NOT NULL'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
