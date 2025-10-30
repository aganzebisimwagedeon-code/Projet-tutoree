<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/functions.php";

start_secure_session();

// Détruire toutes les variables de session
$_SESSION = array();

// Détruire la session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Rediriger vers la page d'accueil
redirect(BASE_URL . "index.php");
?>

