<?php

/**
 * Vérifie si l'utilisateur est connecté.
 * @return bool True si connecté, false sinon.
 */
function is_logged_in() {
    return isset($_SESSION["user_id"]);
}

/**
 * Redirige l'utilisateur vers une autre page.
 * @param string $location L'URL de destination.
 */
function redirect($location) {
    header("Location: " . $location);
    exit;
}

/**
 * Vérifie si l'utilisateur a un rôle spécifique.
 * @param string $role Le rôle à vérifier (e.g., 'admin', 'coiffeur', 'client').
 * @return bool True si l'utilisateur a le rôle, false sinon.
 */
function has_role($role) {
    return is_logged_in() && isset($_SESSION["role"]) && $_SESSION["role"] === $role;
}

/**
 * Génère un hachage de mot de passe sécurisé.
 * @param string $password Le mot de passe en clair.
 * @return string Le mot de passe haché.
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Vérifie un mot de passe haché.
 * @param string $password Le mot de passe en clair.
 * @param string $hashed_password Le mot de passe haché.
 * @return bool True si le mot de passe correspond, false sinon.
 */
function verify_password($password, $hashed_password) {
    return password_verify($password, $hashed_password);
}

/**
 * Nettoie une chaîne de caractères pour éviter les injections SQL et XSS.
 * @param string $data La chaîne à nettoyer.
 * @return string La chaîne nettoyée.
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Démarre une session sécurisée.
 */
function start_secure_session() {
    if (session_status() == PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 3600, // 1 heure
            'path' => '/',
            'domain' => '', // Laissez vide pour le domaine actuel
            'secure' => true, // N'envoyer le cookie qu'en HTTPS
            'httponly' => true, // Empêche l'accès via JavaScript
            'samesite' => 'Lax' // Protège contre les attaques CSRF
        ]);
        session_start();
        // Régénérer l'ID de session pour prévenir la fixation de session
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
    }
}

?>