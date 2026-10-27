-- =============================================================
-- Migration du 16/10/2026 : préparation de la mise en ligne
--   - mot de passe oublié (table password_resets)
--   - déconnexion de toutes les sessions après un changement de mot de passe
--     (colonne users.session_version)
--   - acceptation des CGU tracée (colonne users.terms_accepted_at)
--   - signalement d'annonces et modération (table reports)
--
-- À exécuter UNE fois sur une base existante (installation déjà en place) :
--     docker compose exec -T db mariadb -u lost_found -plost_found_password lost_found_ai < database/migrations/2026_10_16_accounts_legal_reports.sql
-- Une nouvelle installation n'en a pas besoin : database/schema.sql contient déjà tout.
-- =============================================================

-- IF NOT EXISTS : la migration peut être relancée sans erreur (MariaDB 10.0.2+)
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS session_version   INT UNSIGNED NOT NULL DEFAULT 0 AFTER notify_matches,
  ADD COLUMN IF NOT EXISTS terms_accepted_at DATETIME     NULL AFTER session_version;

-- Lien de réinitialisation du mot de passe : un seul lien actif par compte.
CREATE TABLE IF NOT EXISTS password_resets (
  user_id    INT UNSIGNED NOT NULL,
  token_hash CHAR(64)     NOT NULL,                 -- HMAC du jeton, jamais le jeton en clair
  expires_at DATETIME     NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_password_resets_token (token_hash),
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Signalements d'annonces (spam, arnaque, contenu choquant...).
-- Un utilisateur ne peut signaler une même annonce qu'une fois.
CREATE TABLE IF NOT EXISTS reports (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id     INT UNSIGNED NOT NULL,
  reporter_id INT UNSIGNED NOT NULL,
  reason      ENUM('spam','fraud','inappropriate','personal_data','other') NOT NULL,
  details     VARCHAR(500) NULL,
  status      ENUM('open','closed') NOT NULL DEFAULT 'open',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reports_item_reporter (item_id, reporter_id),
  KEY idx_reports_status (status, created_at),
  CONSTRAINT fk_reports_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE,
  CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
