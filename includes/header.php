<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>King and Qween - Salon de Coiffure</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/responsive.css">
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <a href="<?php echo BASE_URL; ?>index.php">King and Qween</a>
            </div>
            <nav>
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>index.php">Accueil</a></li>
                    <li><a href="<?php echo BASE_URL; ?>frontend/prestations.php">Prestations</a></li>
                    <li><a href="<?php echo BASE_URL; ?>frontend/galerie.php">Galerie</a></li>
                    <li><a href="<?php echo BASE_URL; ?>frontend/rendezvous.php">Rendez-vous</a></li>
                    <li><a href="<?php echo BASE_URL; ?>frontend/avis.php">Avis Clients</a></li>
                    <li><a href="<?php echo BASE_URL; ?>frontend/contact.php">Contact</a></li>
                    <li><a href="<?php echo BASE_URL; ?>politique.php">Politique</a></li>
                    <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                        <li><a href="<?php echo BASE_URL; ?>frontend/client_dashboard.php">Mon Espace</a></li>
                        <li><a href="<?php echo BASE_URL; ?>logout.php">Déconnexion</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo BASE_URL; ?>login.php">Connexion</a></li>
                        <li><a href="<?php echo BASE_URL; ?>register.php">Inscription</a></li>
                    <?php endif; ?>
                    <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === "admin"): ?>
                        <li><a href="<?php echo BASE_URL; ?>backend/admin_dashboard.php">Admin</a></li>
                    <?php endif; ?>
                     <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === "coiffeur"): ?>
                        <li><a href="<?php echo BASE_URL; ?>backend/coiffeur_dashboard.php">Dashboard</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <main>


