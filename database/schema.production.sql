-- =============================================================
-- Lost&Found AI - Schéma de la base de données (import phpMyAdmin)
-- Version SANS « CREATE DATABASE » / « USE » : sur un hébergement
-- mutualisé (Hostinger...), la base existe déjà et porte un nom
-- imposé (ex. u123456789_lostfound). Importer ce fichier dans la
-- base déjà créée via hPanel > Bases de données.
-- Compatible MySQL 8+ / MariaDB 10.5+
-- =============================================================

-- Suppression dans l'ordre inverse des dépendances (réinstallation propre)
-- ATTENTION : ces lignes effacent toutes les données existantes.
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS claims;
DROP TABLE IF EXISTS email_verifications;
DROP TABLE IF EXISTS rate_limits;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS conversations;
DROP TABLE IF EXISTS matches;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS users;

-- -------------------------------------------------------------
-- Utilisateurs
-- -------------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name          VARCHAR(100)  NOT NULL,
  email         VARCHAR(190)  NOT NULL,
  password_hash VARCHAR(255)  NOT NULL,
  role          ENUM('user','admin') NOT NULL DEFAULT 'user',
  email_verified_at DATETIME    NULL,                -- NULL = email pas encore vérifié par code
  notify_matches TINYINT(1)     NOT NULL DEFAULT 1,  -- 0 = ne plus recevoir les emails de correspondance
  session_version INT UNSIGNED  NOT NULL DEFAULT 0,  -- +1 à chaque changement de mot de passe : déconnecte les autres sessions
  terms_accepted_at DATETIME    NULL,                -- date d'acceptation des CGU (preuve en cas de litige)
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tentatives de connexion échouées (protection contre le brute force)
-- Une ligne = un échec. Les lignes sont supprimées après une
-- connexion réussie ou lorsqu'elles ont plus de 24 h.
-- Pas de clé étrangère vers users : on enregistre aussi les échecs
-- sur des emails qui n'existent pas.
-- -------------------------------------------------------------
CREATE TABLE login_attempts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email        VARCHAR(190) NOT NULL,
  ip_address   VARCHAR(45)  NOT NULL,             -- 45 caractères : assez pour IPv6
  attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempts_email (email, attempted_at),
  KEY idx_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Limites de débit (anti-spam) : une ligne = une action sensible
-- (inscription, message, prise de contact, création d'annonce).
-- rate_key = 'user:<id>' ou 'ip:<adresse>'. Purgée après 24 h.
-- -------------------------------------------------------------
CREATE TABLE rate_limits (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  action     VARCHAR(30)  NOT NULL,
  rate_key   VARCHAR(60)  NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rate_limits_lookup (action, rate_key, created_at),
  KEY idx_rate_limits_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Annonces (objets perdus ou trouvés)
-- -------------------------------------------------------------
CREATE TABLE items (
  id                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id               INT UNSIGNED  NOT NULL,
  type                  ENUM('lost','found') NOT NULL,
  title                 VARCHAR(150)  NOT NULL,
  description           TEXT          NULL,
  category              VARCHAR(50)   NOT NULL,
  color                 VARCHAR(30)   NULL,
  location_label        VARCHAR(150)  NOT NULL,
  latitude              DECIMAL(9,6)  NULL,
  longitude             DECIMAL(9,6)  NULL,
  date_event            DATE          NOT NULL,
  photo_path            VARCHAR(255)  NULL,
  -- Détail non public : connu seulement du vrai propriétaire (phase 2)
  verification_question VARCHAR(255)  NULL,
  status                ENUM('open','matched','resolved') NOT NULL DEFAULT 'open',
  created_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_items_user (user_id),
  -- Index utilisé par la recherche et par le calcul des correspondances
  KEY idx_items_search (type, status, category, date_event),
  CONSTRAINT fk_items_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Correspondances entre une annonce perdue et une annonce trouvée
-- -------------------------------------------------------------
CREATE TABLE matches (
  id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  lost_item_id  INT UNSIGNED     NOT NULL,
  found_item_id INT UNSIGNED     NOT NULL,
  score         TINYINT UNSIGNED NOT NULL,           -- 0 à 100
  status        ENUM('suggested','accepted','rejected') NOT NULL DEFAULT 'suggested',
  notified_at   DATETIME         NULL,               -- email envoyé (évite les doublons)
  created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  -- Une seule correspondance par couple d'annonces (permet un upsert)
  UNIQUE KEY uq_matches_pair (lost_item_id, found_item_id),
  KEY idx_matches_found (found_item_id),
  CONSTRAINT fk_matches_lost FOREIGN KEY (lost_item_id)
    REFERENCES items (id) ON DELETE CASCADE,
  CONSTRAINT fk_matches_found FOREIGN KEY (found_item_id)
    REFERENCES items (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Conversations (une par correspondance acceptée)
-- -------------------------------------------------------------
CREATE TABLE conversations (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  match_id      INT UNSIGNED NULL,
  item_id       INT UNSIGNED NULL,
  initiator_id  INT UNSIGNED NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_conversations_match (match_id),
  UNIQUE KEY uq_conversations_direct (item_id, initiator_id),
  KEY idx_conversations_item (item_id),
  CONSTRAINT fk_conversations_match FOREIGN KEY (match_id)
    REFERENCES matches (id) ON DELETE CASCADE
  ,
  CONSTRAINT fk_conversations_item FOREIGN KEY (item_id)
    REFERENCES items (id) ON DELETE CASCADE,
  CONSTRAINT fk_conversations_initiator FOREIGN KEY (initiator_id)
    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Messages de la messagerie interne
-- -------------------------------------------------------------
CREATE TABLE messages (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id INT UNSIGNED NOT NULL,
  sender_id       INT UNSIGNED NOT NULL,
  content         TEXT         NOT NULL,
  read_at         DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_messages_conversation (conversation_id, created_at),
  CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id)
    REFERENCES conversations (id) ON DELETE CASCADE,
  CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id)
    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Code de vérification d'email : un seul code actif par compte.
CREATE TABLE email_verifications (
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
CREATE TABLE claims (
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
CREATE TABLE orders (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  claim_id          INT UNSIGNED NOT NULL,
  buyer_id          INT UNSIGNED NOT NULL,          -- propriétaire, qui paie
  finder_id         INT UNSIGNED NOT NULL,          -- personne qui a trouvé, qui expédie
  carrier           VARCHAR(40)  NOT NULL,          -- code du transporteur (voir ShippingService)
  delivery_mode     ENUM('relay','locker','home') NOT NULL,
  relay_point_id    VARCHAR(60)  NULL,
  relay_point_label VARCHAR(255) NULL,
  recipient_name    VARCHAR(100) NOT NULL,          -- prénom seulement en point relais
  address_encrypted TEXT         NULL,              -- domicile uniquement, CHIFFRÉ (AES-256-GCM)
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

-- Lien de réinitialisation du mot de passe (« mot de passe oublié ») :
-- un seul lien actif par compte, valable 1 heure, utilisable une fois.
CREATE TABLE password_resets (
  user_id    INT UNSIGNED NOT NULL,
  token_hash CHAR(64)     NOT NULL,                 -- HMAC du jeton, jamais le jeton en clair
  expires_at DATETIME     NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_password_resets_token (token_hash),
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Signalements d'annonces (spam, arnaque, contenu choquant...), traités
-- par un administrateur sur /admin/reports. Un signalement par personne et par annonce.
CREATE TABLE reports (
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
