<?php
/**
 * Modèle User : toutes les requêtes SQL sur la table `users`.
 *
 * Règle du projet : les contrôleurs n'écrivent jamais de SQL, ils passent
 * par un modèle. Toutes les requêtes sont préparées (paramètres :nom).
 *
 * Les emails sont toujours enregistrés en minuscules (normalisation faite par
 * le contrôleur) : « Alaa@Mail.com » et « alaa@mail.com » sont le même compte.
 */

declare(strict_types=1);

namespace App\Models;

use PDO;

final class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    /**
     * Recherche par email, AVEC le hash du mot de passe.
     * Réservé à la connexion : ne jamais transmettre ce résultat à une vue.
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, password_hash, role, created_at
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Recherche par id, SANS le hash du mot de passe.
     * C'est ce résultat que Auth::user() met à disposition de l'application.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, role, email_verified_at, notify_matches, session_version, created_at
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Hash du mot de passe d'un compte, pour vérifier une confirmation
     * (changement de mot de passe, suppression du compte). Jamais transmis à une vue.
     */
    public function passwordHash(int $id): ?string
    {
        $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $hash = $stmt->fetchColumn();

        return is_string($hash) ? $hash : null;
    }

    /**
     * Crée un compte et retourne son id. L'utilisateur a coché « J'accepte les
     * CGU » (contrôlé par AuthController) : la date est enregistrée.
     *
     * @param string $passwordHash Résultat de password_hash(), jamais le mot de passe en clair.
     */
    public function create(string $name, string $email, string $passwordHash): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password_hash, terms_accepted_at)
             VALUES (:name, :email, :password_hash, NOW())'
        );
        $stmt->execute([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => $passwordHash,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** Marque l'email comme vérifié (après saisie du bon code). */
    public function markEmailVerified(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** Active (true) ou coupe (false) les emails de nouvelles correspondances. */
    public function setNotifyMatches(int $id, bool $enabled): void
    {
        $stmt = $this->db->prepare('UPDATE users SET notify_matches = :enabled WHERE id = :id');
        $stmt->execute(['enabled' => $enabled ? 1 : 0, 'id' => $id]);
    }

    /**
     * Met à jour le hash d'un mot de passe. Utilisé quand PHP recommande un
     * algorithme plus récent (voir password_needs_rehash dans AuthController).
     */
    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['hash' => $passwordHash, 'id' => $id]);
    }

    /**
     * Nouveau mot de passe choisi par l'utilisateur (oubli ou changement).
     *
     * session_version + 1 : toutes les sessions ouvertes avec l'ancien mot de
     * passe deviennent invalides (voir Auth::user). Si quelqu'un avait volé le
     * compte, il est déconnecté dès que le vrai propriétaire change le mot de passe.
     */
    public function changePassword(int $id, string $passwordHash): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET password_hash = :hash, session_version = session_version + 1 WHERE id = :id'
        );
        $stmt->execute(['hash' => $passwordHash, 'id' => $id]);
    }

    /** Donne (true) ou retire (false) le rôle d'administrateur (bin/make-admin.php). */
    public function setAdmin(int $id, bool $admin): void
    {
        $stmt = $this->db->prepare('UPDATE users SET role = :role WHERE id = :id');
        $stmt->execute(['role' => $admin ? 'admin' : 'user', 'id' => $id]);
    }

    /**
     * Supprime définitivement un compte. Les clés étrangères ON DELETE CASCADE
     * effacent avec lui ses annonces, correspondances, conversations, messages,
     * demandes, commandes, codes et signalements (voir database/schema.sql).
     * Les FICHIERS (photos) ne sont pas en base : AccountService les supprime.
     */
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** Statistiques globales, pour le tableau de bord administrateur. */
    public function stats(): array
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) AS total,
                    SUM(email_verified_at IS NOT NULL) AS verified,
                    SUM(role = 'admin') AS admins,
                    SUM(created_at >= NOW() - INTERVAL 7 DAY) AS new_7d
             FROM users"
        );

        return array_map('intval', $stmt->fetch());
    }

    /**
     * Comptes pour la liste d'administration (avec leur nombre d'annonces),
     * du plus récent au plus ancien. $search filtre sur le nom ou l'email.
     */
    public function searchAdmin(string $search, int $limit = 100): array
    {
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE u.name LIKE :search OR u.email LIKE :search';
            $params['search'] = '%' . addcslashes(mb_substr($search, 0, 100), '%_\\') . '%';
        }

        $stmt = $this->db->prepare(
            "SELECT u.id, u.name, u.email, u.role, u.email_verified_at, u.created_at,
                    COUNT(i.id) AS item_count
             FROM users u
             LEFT JOIN items i ON i.user_id = u.id
             $where
             GROUP BY u.id
             ORDER BY u.created_at DESC
             LIMIT " . max(1, min($limit, 200))
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Comptes jamais vérifiés créés il y a plus de $days jours (nettoyage
     * automatique, bin/cleanup.php) : sans vérification, ils n'ont pu ni publier
     * ni contacter personne, ce sont presque toujours des robots ou des fautes de frappe.
     *
     * @return int[]
     */
    public function unverifiedOlderThan(int $days): array
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM users
             WHERE email_verified_at IS NULL AND created_at < NOW() - INTERVAL :days DAY'
        );
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Toutes les données d'un compte, pour le droit d'accès et de portabilité
     * du RGPD (articles 15 et 20) : téléchargées en JSON depuis « Mon compte ».
     * Jamais le hash du mot de passe ; l'adresse de livraison chiffrée n'est
     * pas incluse (elle est effacée dès la livraison).
     */
    public function exportData(int $id): array
    {
        $queries = [
            'compte' => 'SELECT name, email, role, email_verified_at, notify_matches, terms_accepted_at, created_at
                         FROM users WHERE id = :id',
            'annonces' => 'SELECT id, type, title, description, category, color, location_label, date_event,
                                  photo_path, status, created_at
                           FROM items WHERE user_id = :id ORDER BY created_at',
            'demandes_de_restitution' => 'SELECT c.id, i.title AS objet, c.answer, c.proof, c.status, c.created_at
                                          FROM claims c INNER JOIN items i ON i.id = c.item_id
                                          WHERE c.claimant_id = :id ORDER BY c.created_at',
            'messages_envoyes' => 'SELECT conversation_id, content, created_at
                                   FROM messages WHERE sender_id = :id ORDER BY created_at',
            'livraisons' => 'SELECT id, IF(buyer_id = :id2, \'destinataire\', \'expediteur\') AS role, carrier,
                                    delivery_mode, relay_point_label, total_cents, status, created_at
                             FROM orders WHERE buyer_id = :id OR finder_id = :id3 ORDER BY created_at',
        ];

        $export = [];
        foreach ($queries as $section => $sql) {
            $stmt = $this->db->prepare($sql);
            // Un même paramètre nommé ne peut pas apparaître deux fois avec
            // PDO (émulation désactivée) : d'où :id2 et :id3.
            $params = ['id' => $id];
            if (str_contains($sql, ':id2')) {
                $params += ['id2' => $id, 'id3' => $id];
            }
            $stmt->execute($params);
            $export[$section] = $section === 'compte' ? ($stmt->fetch() ?: []) : $stmt->fetchAll();
        }

        return $export;
    }
}
