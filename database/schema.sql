--
-- Base de données: `kingandqween`
--
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
  `role` enum('client','coiffeur','admin') NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Structure de la table `coiffeurs`
--

CREATE TABLE `coiffeurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `specialite` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Structure de la table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `description` text,
  `prix_depart` decimal(10,2) NOT NULL,
  `prix_discutable` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Structure de la table `rendezvous`
--

CREATE TABLE `rendezvous` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `coiffeur_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `date_heure` datetime NOT NULL,
  `type_prestation` enum('domicile','salon') NOT NULL,
  `statut` enum('pending','confirmed','cancelled_by_client','cancelled_by_coiffeur','completed') NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`coiffeur_id`) REFERENCES `coiffeurs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Structure de la table `plannings`
--

CREATE TABLE `plannings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coiffeur_id` int(11) NOT NULL,
  `jour_semaine` enum('Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche') NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`coiffeur_id`) REFERENCES `coiffeurs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Structure de la table `avis`
--

CREATE TABLE `avis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `coiffeur_id` int(11) DEFAULT NULL,
  `note` int(11) CHECK (note >= 1 AND note <= 5),
  `commentaire` text,
  `statut` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`coiffeur_id`) REFERENCES `coiffeurs`(`id`) ON DELETE SET NULL
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Structure de la table `politiques_annulation`
--

CREATE TABLE `politiques_annulation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_acteur` enum('client','coiffeur') NOT NULL,
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
-- Insertion de données initiales pour les rôles d'utilisateur
--
INSERT INTO `users` (`username`, `email`, `password`, `role`) VALUES
('admin', 'admin@kingandqween.com', '$2y$10$Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q', 'admin'); -- Mot de passe: password

--
-- Insertion de données initiales pour les politiques d'annulation (exemple)
--
INSERT INTO `politiques_annulation` (`type_acteur`, `delai_minimum_heures`, `penalite_active`, `description_penalite`) VALUES
('client', 24, 1, '50% du prix du service sera facturé si annulation moins de 24h avant.'),
('coiffeur', 12, 0, 'Notification au client et proposition de reprogrammation.');

--
-- Insertion de données initiales pour les horaires d'ouverture (exemple)
--
INSERT INTO `horaires_ouverture` (`jour_semaine`, `heure_ouverture`, `heure_fermeture`, `ferme`) VALUES
('Lundi', '09:00:00', '18:00:00', 0),
('Mardi', '09:00:00', '18:00:00', 0),
('Mercredi', '09:00:00', '18:00:00', 0),
('Jeudi', '09:00:00', '18:00:00', 0),
('Vendredi', '09:00:00', '19:00:00', 0),
('Samedi', '10:00:00', '17:00:00', 0),
('Dimanche', '00:00:00', '00:00:00', 1);

--
-- Insertion de données initiales pour les services (exemple)
--
INSERT INTO `services` (`nom`, `description`, `prix_depart`, `prix_discutable`) VALUES
('Coiffure Homme', 'Coupe, shampoing, coiffage', 25.00, 0),
('Coiffure Dame', 'Coupe, shampoing, brushing', 45.00, 0),
('Manucure', 'Soin des mains et pose de vernis', 20.00, 0),
('Pédicure', 'Soin des pieds et pose de vernis', 30.00, 0),
('Dreadlocks', 'Création ou entretien de dreadlocks', 80.00, 1),
('Tresses', 'Tresses africaines, nattes collées', 60.00, 1),
('Soin Visage', 'Nettoyage, masque, hydratation', 50.00, 0),
('Taille Barbe', 'Taille et entretien de la barbe', 15.00, 0),
('Maquillage', 'Maquillage de jour ou de soirée', 35.00, 0);

--
-- Insertion de données initiales pour les coiffeurs (exemple)
--
INSERT INTO `users` (`username`, `email`, `password`, `role`) VALUES
('Jean Dupont', 'jean.dupont@kingandqween.com', '$2y$10$Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q', 'coiffeur'),
('Marie Curie', 'marie.curie@kingandqween.com', '$2y$10$Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q.Q', 'coiffeur');

INSERT INTO `coiffeurs` (`user_id`, `specialite`, `photo`) VALUES
((SELECT id FROM users WHERE email = 'jean.dupont@kingandqween.com'), 'Coiffure Homme, Taille Barbe', 'jean_dupont.jpg'),
((SELECT id FROM users WHERE email = 'marie.curie@kingandqween.com'), 'Coiffure Dame, Tresses, Maquillage', 'marie_curie.jpg');

--
-- Insertion de données initiales pour les plannings (exemple)
--
INSERT INTO `plannings` (`coiffeur_id`, `jour_semaine`, `heure_debut`, `heure_fin`) VALUES
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'jean.dupont@kingandqween.com')), 'Lundi', '09:00:00', '17:00:00'),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'jean.dupont@kingandqween.com')), 'Mardi', '09:00:00', '17:00:00'),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'marie.curie@kingandqween.com')), 'Mercredi', '10:00:00', '18:00:00'),
((SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'marie.curie@kingandqween.com')), 'Jeudi', '10:00:00', '18:00:00');

--
-- Insertion de données initiales pour les promotions (exemple)
--
INSERT INTO `promotions` (`titre`, `description`, `date_debut`, `date_fin`) VALUES
('Promotion Été', '20% de réduction sur toutes les prestations de coiffure femme.', '2025-07-01', '2025-08-31'),
('Offre Barbe', 'Taille de barbe offerte pour toute coupe homme.', '2025-08-01', '2025-08-15');

--
-- Insertion de données initiales pour les rendez-vous (exemple)
--
INSERT INTO `rendezvous` (`client_id`, `coiffeur_id`, `service_id`, `date_heure`, `type_prestation`, `statut`) VALUES
((SELECT id FROM users WHERE email = 'admin@kingandqween.com'), (SELECT id FROM coiffeurs WHERE user_id = (SELECT id FROM users WHERE email = 'jean.dupont@kingandqween.com')), (SELECT id FROM services WHERE nom = 'Coiffure Homme'), '2025-08-10 10:00:00', 'salon', 'pending');

-- Note: Le mot de passe 'password' est haché avec bcrypt. Utilisez password_hash() en PHP pour générer de vrais hachages.

