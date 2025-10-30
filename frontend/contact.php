<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Contactez-nous</h1>
    </div>
</section>

<section class="contact-info">
    <div class="container">
        <p class="intro-text">N'hésitez pas à nous contacter pour toute question, prise de rendez-vous ou information complémentaire. Nous sommes là pour vous !</p>

        <div class="contact-details">
            <p><i class="fas fa-phone-alt"></i> Téléphone: <strong>+33 1 23 45 67 89</strong></p>
            <p><i class="fas fa-envelope"></i> Email: <strong>contact@kingandqween.com</strong></p>
            <p><i class="fas fa-map-marker-alt"></i> Adresse: <strong>123 Rue de la Coiffure, 75001 Paris, France</strong></p>
            <p><i class="fas fa-clock"></i> Horaires: <strong>Du Lundi au Samedi, 9h00 - 19h00</strong></p>
        </div>

        <div class="alert alert-info">
            <p>Le formulaire de contact n'est actuellement pas disponible. Veuillez nous contacter directement par téléphone ou email.</p>
        </div>
    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>