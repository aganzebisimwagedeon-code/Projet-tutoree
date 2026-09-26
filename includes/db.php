<?php
require_once __DIR__ . "/config.php";

// Désactiver les exceptions automatiques mysqli pour gérer proprement les erreurs via les valeurs de retour
mysqli_report(MYSQLI_REPORT_OFF);

$mysqli = @new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);

// Vérifier la connexion sans exposer d'informations sensibles en production
if ($mysqli->connect_errno) {
    error_log("Erreur critique de connexion MySQL (" . $mysqli->connect_errno . "): " . $mysqli->connect_error);
    if (defined('APP_DEBUG') && APP_DEBUG && APP_ENV !== 'production') {
        die("Erreur de connexion à la base de données : " . htmlspecialchars($mysqli->connect_error));
    }
    http_response_code(500);
    die("Le service est temporairement indisponible. Veuillez réessayer plus tard.");
}

// Définir le jeu de caractères à utf8mb4
$mysqli->set_charset("utf8mb4");
