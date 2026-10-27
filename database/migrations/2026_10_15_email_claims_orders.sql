-- =============================================================
-- Migration : vérification des emails, notifications, demandes de
-- restitution (preuve de propriété) et commandes de livraison.
-- Ajoute des colonnes et des tables SANS effacer les données.
--
-- Utilisation :
--   mysql -u root lost_found_ai < database/migrations/2026_10_15_email_claims_orders.sql
--   (Docker) Get-Content database\migrations\2026_10_15_email_claims_orders.sql |
--            docker exec -i lost-found-ai-db-1 mariadb -ulost_found -plost_found_password lost_found_ai
--
-- Inutile si la base a été créée avec la version actuelle de schema.sql.
-- =============================================================

-- Comptes : date de vérification de l'email (NULL = pas encore vérifié)
-- et choix de recevoir ou non les emails de correspondance.
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL AFTER role,
  ADD COLUMN IF NOT EXISTS notify_matches TINYINT(1) NOT NULL DEFAULT 1 AFTER email_verified_at;

-- Correspondances : date d'envoi de l'email (évite d'envoyer deux fois le même)
ALTER TABLE matches
  ADD COLUMN IF NOT EXISTS notified_at DATETIME NULL AFTER status;

-- Code de vérification d'email : un seul code actif par compte.
CREATE TABLE IF NOT EXISTS email_verifications (
  user_id    INT UNSIGNED     NOT NULL,
  code_hash  CHAR(64)         NOT NULL,             -- HMAC du code, jamais le code en clair
  expires_at DATETIME         NOT NULL,
  attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- essais ratés (bloqué à 5)
  sent_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Demandes de restitution : le propriétaire prouve que l'objet trouvé est
-- à lui (réponse à la question de vérification, détail non public, photo).
CREATE TABLE IF NOT EXISTS claims (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id     INT UNSIGNED NOT NULL,                -- annonce « trouvé »
  claimant_id INT UNSIGNED NOT NULL,                -- personne qui se dit propriétaire
  answer      VARCHAR(255) NULL,                    -- réponse à la question de vérification
  proof       TEXT         NOT NULL,                -- détails prouvant la propriété
  proof_photo VARCHAR(255) NULL,                    -- fichier PRIVÉ dans storage/proofs (hors public/)
  status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  decided_at  DATETIME     NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_claims_item_claimant (item_id, claimant_id),
  KEY idx_claims_claimant (claimant_id),
  CONSTRAINT fk_claims_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE,
  CONSTRAINT fk_claims_claimant FOREIGN KEY (claimant_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Commandes de livraison : frais de port + pourboire pour la personne qui a
-- trouvé l'objet. Les montants sont en centimes (entiers : pas d'arrondi).
CREATE TABLE IF NOT EXISTS orders (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  claim_id          INT UNSIGNED NOT NULL,
  buyer_id          INT UNSIGNED NOT NULL,          -- propriétaire, qui paie
  finder_id         INT UNSIGNED NOT NULL,          -- personne qui a trouvé, qui expédie
  carrier           VARCHAR(40)  NOT NULL,          -- code du transporteur (voir ShippingService)
  delivery_mode     ENUM('relay','locker','home') NOT NULL,
  relay_point_id    VARCHAR(60)  NULL,
  relay_point_label VARCHAR(255) NULL,
  recipient_name    VARCHAR(100) NOT NULL,          -- prénom seulement en point relais
  address_encrypted TEXT         NULL,              -- domicile uniquement, CHIFFRÉ (sodium)
  shipping_cents    INT UNSIGNED NOT NULL,
  tip_cents         INT UNSIGNED NOT NULL,
  total_cents       INT UNSIGNED NOT NULL,
  currency          CHAR(3)      NOT NULL DEFAULT 'EUR',
  status            ENUM('pending_payment','paid','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending_payment',
  payment_provider  VARCHAR(20)  NOT NULL,          -- 'simulation' ou 'stripe'
  payment_ref       VARCHAR(255) NULL,              -- identifiant de la session de paiement
  tracking_number   VARCHAR(64)  NULL,
  tip_status        ENUM('pending','available','paid') NOT NULL DEFAULT 'pending',
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at           DATETIME     NULL,
  shipped_at        DATETIME     NULL,
  delivered_at      DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_claim (claim_id),
  KEY idx_orders_buyer (buyer_id),
  KEY idx_orders_finder (finder_id),
  KEY idx_orders_payment_ref (payment_ref),
  CONSTRAINT fk_orders_claim FOREIGN KEY (claim_id) REFERENCES claims (id) ON DELETE CASCADE,
  CONSTRAINT fk_orders_buyer FOREIGN KEY (buyer_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_orders_finder FOREIGN KEY (finder_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
