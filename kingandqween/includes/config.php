<?php

define("DB_SERVER", "localhost");
define("DB_USERNAME", "root");
define("DB_PASSWORD", "");
define("DB_NAME", "kingandqween");

// URL de base du site (à adapter si le projet n'est pas à la racine du serveur web)
define("BASE_URL", "http://localhost/kingandqween/");

// Politique d'annulation par défaut (en heures)
define("CLIENT_CANCEL_POLICY_HOURS", 24);
define("COIFFEUR_CANCEL_POLICY_HOURS", 12);

// Clé secrète pour les sessions (à changer pour une valeur aléatoire et complexe en production)
define("SESSION_SECRET", "votre_cle_secrete_tres_longue_et_complexe_ici");

// Paramètres d'envoi d'emails (à configurer pour un vrai serveur SMTP)
define("MAIL_HOST", "smtp.example.com");
define("MAIL_USERNAME", "your_email@example.com");
define("MAIL_PASSWORD", "your_email_password");
define("MAIL_PORT", 587);
define("MAIL_FROM_EMAIL", "no-reply@kingandqween.com");
define("MAIL_FROM_NAME", "King and Qween Salon");

?>

