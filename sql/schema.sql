-- ============================================================================
-- PROJET 51 — Plateforme de crowdfunding pour projets entrepreneuriaux
-- Paiement mobile money (Wave, Orange Money, Free Money)
-- Master CCA — ESP Dakar — M. Ousmane LY — Année 2025-2026
-- ============================================================================
-- IMPORTANT : les comptes de test (utilisateurs) ne sont PAS créés ici avec
-- un mot de passe en clair. Ils sont créés par le script PHP
-- sql/seed_demo.php qui utilise password_hash() pour garantir un hash bcrypt
-- valide avec VOTRE version de PHP (voir README.md, section "Installation").
-- ============================================================================

DROP DATABASE IF EXISTS crowdfunding_esp;
CREATE DATABASE crowdfunding_esp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crowdfunding_esp;

-- ----------------------------------------------------------------------------
-- Table : utilisateurs
-- 3 rôles : administrateur, porteur (porteur de projet), contributeur
-- ----------------------------------------------------------------------------
CREATE TABLE utilisateurs (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom                 VARCHAR(100) NOT NULL,
    prenom              VARCHAR(100) NOT NULL,
    email               VARCHAR(150) NOT NULL UNIQUE,
    telephone           VARCHAR(20)  NOT NULL,
    mot_de_passe        VARCHAR(255) NOT NULL,           -- password_hash() bcrypt
    role                ENUM('admin','porteur','contributeur') NOT NULL DEFAULT 'contributeur',
    kyc_valide          TINYINT(1) NOT NULL DEFAULT 0,
    statut              ENUM('actif','suspendu') NOT NULL DEFAULT 'actif',
    token_reset         VARCHAR(255) NULL,
    derniere_connexion  DATETIME NULL,
    date_creation       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_utilisateurs_role (role),
    INDEX idx_utilisateurs_email (email)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Table : categories
-- ----------------------------------------------------------------------------
CREATE TABLE categories (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom                 VARCHAR(100) NOT NULL UNIQUE,
    description         VARCHAR(255) NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Table : campagnes  (ENTITÉ PRINCIPALE)
-- ----------------------------------------------------------------------------
CREATE TABLE campagnes (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    porteur_id          INT UNSIGNED NOT NULL,
    categorie_id        INT UNSIGNED NOT NULL,
    titre               VARCHAR(150) NOT NULL,
    slug                VARCHAR(170) NOT NULL UNIQUE,
    description         TEXT NOT NULL,
    objectif_montant     DECIMAL(14,2) NOT NULL,
    montant_collecte     DECIMAL(14,2) NOT NULL DEFAULT 0,
    date_debut          DATE NOT NULL,
    date_fin            DATE NOT NULL,
    statut              ENUM('brouillon','en_attente','active','reussie','echouee','cloturee') NOT NULL DEFAULT 'brouillon',
    video_url           VARCHAR(255) NULL,
    image_url           VARCHAR(255) NULL,
    date_creation        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_maj             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_campagnes_porteur FOREIGN KEY (porteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    CONSTRAINT fk_campagnes_categorie FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE RESTRICT,
    INDEX idx_campagnes_statut (statut),
    INDEX idx_campagnes_titre (titre),
    FULLTEXT INDEX ft_campagnes_recherche (titre, description)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Table : paliers (contreparties par montant)
-- ----------------------------------------------------------------------------
CREATE TABLE paliers (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campagne_id         INT UNSIGNED NOT NULL,
    titre               VARCHAR(100) NOT NULL,
    description         VARCHAR(255) NULL,
    montant_min          DECIMAL(12,2) NOT NULL,
    contrepartie        VARCHAR(255) NULL,
    quantite_disponible   INT UNSIGNED NULL,               -- NULL = illimité
    quantite_reservee     INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_paliers_campagne FOREIGN KEY (campagne_id) REFERENCES campagnes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Table : contributions
-- ----------------------------------------------------------------------------
CREATE TABLE contributions (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campagne_id         INT UNSIGNED NOT NULL,
    contributeur_id      INT UNSIGNED NOT NULL,
    palier_id           INT UNSIGNED NULL,
    montant             DECIMAL(12,2) NOT NULL,
    anonyme             TINYINT(1) NOT NULL DEFAULT 0,
    message              VARCHAR(255) NULL,
    statut              ENUM('en_attente','validee','remboursee','echouee') NOT NULL DEFAULT 'en_attente',
    date_contribution     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_contrib_campagne FOREIGN KEY (campagne_id) REFERENCES campagnes(id) ON DELETE CASCADE,
    CONSTRAINT fk_contrib_contributeur FOREIGN KEY (contributeur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    CONSTRAINT fk_contrib_palier FOREIGN KEY (palier_id) REFERENCES paliers(id) ON DELETE SET NULL,
    INDEX idx_contrib_statut (statut)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Table : paiements (transactions mobile money)
-- ----------------------------------------------------------------------------
CREATE TABLE paiements (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contribution_id      INT UNSIGNED NOT NULL,
    operateur            ENUM('wave','orange_money','free_money','carte') NOT NULL,
    reference_transaction VARCHAR(100) NOT NULL UNIQUE,
    montant              DECIMAL(12,2) NOT NULL,
    statut               ENUM('en_attente','reussi','echoue','rembourse') NOT NULL DEFAULT 'en_attente',
    date_paiement         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_paiements_contribution FOREIGN KEY (contribution_id) REFERENCES contributions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Table : actualites (mises à jour publiées par le porteur sur sa campagne)
-- ----------------------------------------------------------------------------
CREATE TABLE actualites (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campagne_id         INT UNSIGNED NOT NULL,
    titre               VARCHAR(150) NOT NULL,
    contenu              TEXT NOT NULL,
    date_publication      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_actualites_campagne FOREIGN KEY (campagne_id) REFERENCES campagnes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Table : notifications
-- ----------------------------------------------------------------------------
CREATE TABLE notifications (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id       INT UNSIGNED NOT NULL,
    message              VARCHAR(255) NOT NULL,
    lu                  TINYINT(1) NOT NULL DEFAULT 0,
    date_creation         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Table : journal_audit (piste d'audit horodatée des actions sensibles)
-- ----------------------------------------------------------------------------
CREATE TABLE journal_audit (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id       INT UNSIGNED NULL,
    action                VARCHAR(100) NOT NULL,
    table_concernee        VARCHAR(50) NOT NULL,
    enregistrement_id     INT UNSIGNED NULL,
    details               VARCHAR(255) NULL,
    adresse_ip            VARCHAR(45) NULL,
    date_action           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================================
-- DONNÉES DE DÉMONSTRATION (hors comptes utilisateurs — voir seed_demo.php)
-- ============================================================================

INSERT INTO categories (nom, description) VALUES
('Agro-industrie', 'Transformation agricole, agro-alimentaire'),
('Technologie', 'Applications, plateformes, IoT'),
('Artisanat & Mode', 'Textile, couture, artisanat local'),
('Santé', 'Solutions de santé et bien-être'),
('Éducation', 'Projets à vocation éducative'),
('Environnement', 'Projets verts et développement durable');

-- Les INSERT dans campagnes, paliers, contributions et paiements sont
-- exécutés par sql/seed_demo.php car ils référencent les id des
-- utilisateurs créés dynamiquement (avec password_hash()).
