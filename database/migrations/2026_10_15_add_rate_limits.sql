-- =============================================================
-- Migration : ajoute la table rate_limits (anti-spam) à une base
-- existante, SANS effacer les données.
-- Utilisation : mysql -u root lost_found_ai < database/migrations/2026_10_15_add_rate_limits.sql
-- (Inutile si la base a été créée avec la version actuelle de schema.sql.)
-- =============================================================

CREATE TABLE IF NOT EXISTS rate_limits (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  action     VARCHAR(30)  NOT NULL,
  rate_key   VARCHAR(60)  NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rate_limits_lookup (action, rate_key, created_at),
  KEY idx_rate_limits_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
