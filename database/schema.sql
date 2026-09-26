--
-- Base de données: `kingandqween`
--
DROP DATABASE IF EXISTS `kingandqween`;
CREATE DATABASE IF NOT EXISTS `kingandqween` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `kingandqween`;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('client','coiffeur','admin') NOT NULL DEFAULT 'client',
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_reset_token` (`reset_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Structure de la table `coiffeurs`
--
CREATE TABLE `coiffeurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL UNIQUE,
  `specialite` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_coiffeurs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Structure de la table `services`
--
CREATE TABLE `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `description` text,
  `prix_depart` decimal(10,2) NOT NULL,
  `duree_minutes` int(11) NOT NULL DEFAULT 30,
  `prix_discutable` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_services_prix` CHECK (`prix_depart` >= 0),
  CONSTRAINT `chk_services_duree` CHECK (`duree_minutes` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Structure de la table `coiffeur_services` (Phase 3.1)
--
CREATE TABLE `coiffeur_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coiffeur_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `prix_personnalise` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coiffeur_service` (`coiffeur_id`, `service_id`),
  KEY `idx_cs_service` (`service_id`),
  CONSTRAINT `fk_cs_coiffeur` FOREIGN KEY (`coiffeur_id`) REFERENCES `coiffeurs`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cs_service` FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Structure de la table `rendezvous` (Phases 1.1, 3.2, 3.4)
--
CREATE TABLE `rendezvous` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `coiffeur_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `date_heure` datetime NOT NULL,
  `date_heure_fin` datetime DEFAULT NULL,
  `duree_minutes_snapshot` int(11) NOT NULL DEFAULT 30,
  `prix_final` decimal(10,2) DEFAULT NULL,
  `type_prestation` enum('domicile','salon') NOT NULL DEFAULT 'salon',
  `adresse_domicile` text DEFAULT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `statut` enum('pending','confirmed','cancelled_by_client','cancelled_by_coiffeur','completed') NOT NULL DEFAULT 'pending',
  `motif_annulation` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rdv_client_date` (`client_id`, `date_heure`),
  KEY `idx_rdv_coiffeur_date` (`coiffeur_id`, `date_heure`),
  KEY `idx_rdv_service` (`service_id`),
  KEY `idx_rdv_date_heure` (`date_heure`),
  KEY `idx_rdv_statut` (`statut`),
  CONSTRAINT `fk_rdv_client` FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rdv_coiffeur` FOREIGN KEY (`coiffeur_id`) REFERENCES `coiffeurs`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rdv_service` FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Structure de la table `plannings` (Phase 3.4)
--
CREATE TABLE `plannings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coiffeur_id` int(11) NOT NULL,
  `jour_semaine` enum('Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche') NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_planning_coiffeur_jour_debut` (`coiffeur_id`, `jour_semaine`, `heure_debut`),
  KEY `idx_plannings_coiffeur_jour` (`coiffeur_id`, `jour_semaine`),
  CONSTRAINT `fk_plannings_coiffeur` FOREIGN KEY (`coiffeur_id`) REFERENCES `coiffeurs`(`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_planning_heures` CHECK (`heure_debut` < `heure_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Structure de la table `absences_coiffeurs` (Phase 2.4)
--
CREATE TABLE `absences_coiffeurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coiffeur_id` int(11) NOT NULL,
  `date_debut` datetime NOT NULL,
  `date_fin` datetime NOT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_absences_coiffeur_dates` (`coiffeur_id`, `date_debut`, `date_fin`),
  CONSTRAINT `fk_absences_coiffeur` FOREIGN KEY (`coiffeur_id`) REFERENCES `coiffeurs`(`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_absence_dates` CHECK (`date_debut` < `date_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Structure de la table `avis` (Phase 3.3)
--
CREATE TABLE `avis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `coiffeur_id` int(11) DEFAULT NULL,
  `rendezvous_id` int(11) DEFAULT NULL,
  `note` int(11) NOT NULL,
  `commentaire` text NOT NULL,
  `statut` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_avis_rendezvous` (`rendezvous_id`),
  KEY `idx_avis_client` (`client_id`),
  KEY `idx_avis_coiffeur` (`coiffeur_id`),
  KEY `idx_avis_statut` (`statut`),
  CONSTRAINT `fk_avis_client` FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_avis_coiffeur` FOREIGN KEY (`coiffeur_id`) REFERENCES `coiffeurs`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_avis_rendezvous` FOREIGN KEY (`rendezvous_id`) REFERENCES `rendezvous`(`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_avis_note` CHECK (`note` >= 1 AND `note` <= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Structure de la table `promotions`
--
CREATE TABLE `promotions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `description` text,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_promotions_dates` (`date_debut`, `date_fin`),
  CONSTRAINT `chk_promotions_dates` CHECK (`date_debut` <= `date_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Structure de la table `politiques_annulation`
--
CREATE TABLE `politiques_annulation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_acteur` enum('client','coiffeur') NOT NULL UNIQUE,
  `delai_minimum_heures` int(11) NOT NULL,
  `penalite_active` tinyint(1) DEFAULT 0,
  `description_penalite` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Structure de la table `horaires_ouverture`
--
CREATE TABLE `horaires_ouverture` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jour_semaine` enum('Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche') NOT NULL UNIQUE,
  `heure_ouverture` time NOT NULL,
  `heure_fermeture` time NOT NULL,
  `ferme` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Structure de la table `notifications` (Phase 7)
--
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `lu` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_lu` (`user_id`, `lu`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Structure de la table `email_logs` (Phase 7)
--
CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `destinataire_email` varchar(255) NOT NULL,
  `sujet` varchar(255) NOT NULL,
  `type_notification` varchar(50) NOT NULL,
  `statut` enum('sent','failed','skipped') NOT NULL DEFAULT 'skipped',
  `erreur_message` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_logs_statut` (`statut`),
  KEY `idx_email_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- DONNÉES INITIALES (Mot de passe de démonstration : "password")
-- Hachage bcrypt valide généré par password_hash('password', PASSWORD_BCRYPT)
-- --------------------------------------------------------

INSERT INTO `users` (`username`, `email`, `password`, `role`) VALUES
('admin', 'admin@kingandqween.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Jean Dupont', 'jean.dupont@kingandqween.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'coiffeur'),
('Marie Curie', 'marie.curie@kingandqween.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'coiffeur'),
('Client Test', 'client@kingandqween.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client');

INSERT INTO `politiques_annulation` (`type_acteur`, `delai_minimum_heures`, `penalite_active`, `description_penalite`) VALUES
('client', 24, 1, '50% du prix du service sera facturé si annulation moins de 24h avant.'),
('coiffeur', 12, 0, 'Notification au client et proposition de reprogrammation.');

INSERT INTO `horaires_ouverture` (`jour_semaine`, `heure_ouverture`, `heure_fermeture`, `ferme`) VALUES
('Lundi', '09:00:00', '18:00:00', 0),
('Mardi', '09:00:00', '18:00:00', 0),
('Mercredi', '09:00:00', '18:00:00', 0),
('Jeudi', '09:00:00', '18:00:00', 0),
('Vendredi', '09:00:00', '19:00:00', 0),
('Samedi', '10:00:00', '17:00:00', 0),
('Dimanche', '00:00:00', '00:00:00', 1);

INSERT INTO `services` (`nom`, `description`, `prix_depart`, `duree_minutes`, `prix_discutable`) VALUES
('Coiffure Homme', 'Coupe, shampoing, coiffage', 25.00, 30, 0),
('Coiffure Dame', 'Coupe, shampoing, brushing', 45.00, 60, 0),
('Manucure', 'Soin des mains et pose de vernis', 20.00, 45, 0),
('Pédicure', 'Soin des pieds et pose de vernis', 30.00, 45, 0),
('Dreadlocks', 'Création ou entretien de dreadlocks', 80.00, 120, 1),
('Tresses', 'Tresses africaines, nattes collées', 60.00, 90, 1),
('Soin Visage', 'Nettoyage, masque, hydratation', 50.00, 45, 0),
('Taille Barbe', 'Taille et entretien de la barbe', 15.00, 20, 0),
('Maquillage', 'Maquillage de jour ou de soirée', 35.00, 45, 0);

INSERT INTO `coiffeurs` (`user_id`, `specialite`, `photo`) VALUES
((SELECT id FROM users WHERE email = 'jean.dupont@kingandqween.com'), 'Coiffure Homme, Taille Barbe, Dreadlocks', 'jean_dupont.jpg'),
((SELECT id FROM users WHERE email = 'marie.curie@kingandqween.com'), 'Coiffure Dame, Tresses, Maquillage, Manucure, Pédicure, Soin Visage', 'marie_curie.jpg');

-- Liaison coiffeurs <-> services (Phase 3.1)
INSERT INTO `coiffeur_services` (`coiffeur_id`, `service_id`, `prix_personnalise`) VALUES
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'jean.dupont@kingandqween.com')), (SELECT id FROM services WHERE nom = 'Coiffure Homme'), NULL),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'jean.dupont@kingandqween.com')), (SELECT id FROM services WHERE nom = 'Taille Barbe'), NULL),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'jean.dupont@kingandqween.com')), (SELECT id FROM services WHERE nom = 'Dreadlocks'), NULL),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'marie.curie@kingandqween.com')), (SELECT id FROM services WHERE nom = 'Coiffure Dame'), NULL),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'marie.curie@kingandqween.com')), (SELECT id FROM services WHERE nom = 'Tresses'), NULL),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'marie.curie@kingandqween.com')), (SELECT id FROM services WHERE nom = 'Maquillage'), NULL),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'marie.curie@kingandqween.com')), (SELECT id FROM services WHERE nom = 'Manucure'), NULL),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'marie.curie@kingandqween.com')), (SELECT id FROM services WHERE nom = 'Pédicure'), NULL),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'marie.curie@kingandqween.com')), (SELECT id FROM services WHERE nom = 'Soin Visage'), NULL);

-- Plannings hebdomadaires complets
INSERT INTO `plannings` (`coiffeur_id`, `jour_semaine`, `heure_debut`, `heure_fin`) VALUES
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'jean.dupont@kingandqween.com')), 'Lundi', '09:00:00', '17:00:00'),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'jean.dupont@kingandqween.com')), 'Mardi', '09:00:00', '17:00:00'),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'jean.dupont@kingandqween.com')), 'Jeudi', '09:00:00', '17:00:00'),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'jean.dupont@kingandqween.com')), 'Vendredi', '09:00:00', '18:00:00'),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'jean.dupont@kingandqween.com')), 'Samedi', '10:00:00', '17:00:00'),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'marie.curie@kingandqween.com')), 'Mardi', '10:00:00', '18:00:00'),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'marie.curie@kingandqween.com')), 'Mercredi', '10:00:00', '18:00:00'),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'marie.curie@kingandqween.com')), 'Jeudi', '10:00:00', '18:00:00'),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'marie.curie@kingandqween.com')), 'Vendredi', '10:00:00', '19:00:00'),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'marie.curie@kingandqween.com')), 'Samedi', '10:00:00', '17:00:00');

INSERT INTO `promotions` (`titre`, `description`, `date_debut`, `date_fin`) VALUES
('Promotion Été', '20% de réduction sur toutes les prestations de coiffure femme.', '2025-07-01', '2026-12-31'),
('Offre Barbe', 'Taille de barbe offerte pour toute coupe homme.', '2025-08-01', '2026-12-31');

INSERT INTO `rendezvous` (`client_id`, `coiffeur_id`, `service_id`, `date_heure`, `date_heure_fin`, `duree_minutes_snapshot`, `prix_final`, `type_prestation`, `statut`) VALUES
((SELECT id FROM users WHERE email = 'client@kingandqween.com'), (SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'jean.dupont@kingandqween.com')), (SELECT id FROM services WHERE nom = 'Coiffure Homme'), '2026-10-05 10:00:00', '2026-10-05 10:30:00', 30, 25.00, 'salon', 'pending');
