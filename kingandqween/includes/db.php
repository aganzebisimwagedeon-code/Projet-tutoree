<?php
require_once __DIR__ . "/config.php";

$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Vérifier la connexion
if ($mysqli->connect_error) {
    die("Erreur de connexion à la base de données: " . $mysqli->connect_error);
}

// Définir le jeu de caractères à utf8mb4
$mysqli->set_charset("utf8mb4");
?>

