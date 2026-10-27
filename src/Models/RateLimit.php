<?php
/**
 * Modèle RateLimit : limite le nombre d'actions sensibles dans le temps
 * (anti-spam et anti-abus).
 *
 * Chaque action réussie ajoute une ligne dans `rate_limits`. Avant d'agir, on
 * compte les lignes récentes pour le couple (action, clé) :
 *
 *     $limit = new RateLimit();
 *     if ($limit->tooMany('message', 'user:12', 20, 300)) { ... refus 429 ... }
 *     $limit->hit('message', 'user:12');
 *
 * La clé identifie l'auteur : 'user:<id>' pour un membre connecté,
 * 'ip:<adresse>' pour un visiteur (inscription).
 *
 * Même principe que LoginAttempt : stockage en base et non en session, sinon
 * il suffirait de supprimer son cookie pour remettre le compteur à zéro.
 */

declare(strict_types=1);

namespace App\Models;

use PDO;

final class RateLimit
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    /**
     * Vrai si $key a déjà effectué $max fois l'action $action pendant les
     * $windowSeconds dernières secondes.
     */
    public function tooMany(string $action, string $key, int $max, int $windowSeconds): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM rate_limits
             WHERE action = :action
               AND rate_key = :rate_key
               AND created_at > NOW() - INTERVAL :seconds SECOND'
        );
        $stmt->bindValue('action', $action);
        $stmt->bindValue('rate_key', $key);
        $stmt->bindValue('seconds', $windowSeconds, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn() >= $max;
    }

    /**
     * Enregistre une action. Profite de l'appel pour purger, de temps en
     * temps, les lignes de plus de 24 h (la table reste petite).
     */
    public function hit(string $action, string $key): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO rate_limits (action, rate_key) VALUES (:action, :rate_key)'
        );
        $stmt->execute(['action' => $action, 'rate_key' => $key]);

        if (random_int(1, 50) === 1) {
            $this->purgeOld();
        }
    }

    /** Supprime les lignes de plus de 24 h. Retourne le nombre de lignes effacées. */
    public function purgeOld(): int
    {
        return (int) $this->db->exec('DELETE FROM rate_limits WHERE created_at < NOW() - INTERVAL 1 DAY');
    }
}
