<?php
/**
 * Configuration principale de l'application King and Qween.
 * Charge les variables depuis le fichier .env s'il existe, ou utilise les variables d'environnement système.
 */

if (!function_exists('kq_load_env')) {
    function kq_load_env(string $envFile): void {
        if (!is_readable($envFile)) {
            return;
        }
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            if (
                (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))
            ) {
                $val = substr($val, 1, -1);
            }
            if ($key !== '' && getenv($key) === false && !isset($_ENV[$key])) {
                putenv("$key=$val");
                $_ENV[$key] = $val;
            }
        }
    }
}

if (!function_exists('kq_env')) {
    function kq_env(string $key, mixed $default = null): mixed {
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        $val = getenv($key);
        if ($val !== false) {
            return $val;
        }
        return $default;
    }
}

kq_load_env(dirname(__DIR__) . '/.env');

// Environnement et débogage (Phase 1.2)
define('APP_ENV', (string) kq_env('APP_ENV', 'production'));
$debugRaw = kq_env('APP_DEBUG', APP_ENV === 'development' ? 'true' : 'false');
define('APP_DEBUG', filter_var($debugRaw, FILTER_VALIDATE_BOOLEAN));

// Fuseau horaire uniforme
define('APP_TIMEZONE', (string) kq_env('APP_TIMEZONE', 'Africa/Lubumbashi'));
date_default_timezone_set(APP_TIMEZONE);

// Gestion sécurisée de l'affichage des erreurs (désactivé en production)
error_reporting(E_ALL);
ini_set('log_errors', '1');
if (APP_DEBUG && APP_ENV !== 'production') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

// Paramètres Base de Données
define('DB_SERVER', (string) kq_env('DB_SERVER', '127.0.0.1'));
define('DB_PORT', (int) kq_env('DB_PORT', 3306));
define('DB_USERNAME', (string) kq_env('DB_USERNAME', 'root'));
define('DB_PASSWORD', (string) kq_env('DB_PASSWORD', ''));
define('DB_NAME', (string) kq_env('DB_NAME', 'kingandqween'));

// Détection ou configuration de BASE_URL (Phase 0.1 & Phase 1.2)
$configuredBaseUrl = (string) kq_env('BASE_URL', '');
if ($configuredBaseUrl !== '') {
    define('BASE_URL', rtrim($configuredBaseUrl, '/') . '/');
} else {
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $projRoot = realpath(dirname(__DIR__));
    $subPath = '';
    if ($docRoot && $projRoot && str_starts_with($projRoot, $docRoot)) {
        $subPath = trim(str_replace('\\', '/', substr($projRoot, strlen($docRoot))), '/');
    }
    define('BASE_URL', $scheme . '://' . $host . ($subPath !== '' ? '/' . $subPath : '') . '/');
}

// Politiques d'annulation par défaut (en heures)
define('CLIENT_CANCEL_POLICY_HOURS', (int) kq_env('CLIENT_CANCEL_POLICY_HOURS', 24));
define('COIFFEUR_CANCEL_POLICY_HOURS', (int) kq_env('COIFFEUR_CANCEL_POLICY_HOURS', 12));

// Sécurité & Sessions
define('SESSION_SECRET', (string) kq_env('SESSION_SECRET', '9f4b2a7d1e8c6b3a5f0e2d4c8b7a9e1f3d5c7b9a0e2f4d6c8b1a3e5f7d9c0b2a'));
define('MAX_LOGIN_ATTEMPTS', (int) kq_env('MAX_LOGIN_ATTEMPTS', 5));
define('LOGIN_LOCKOUT_MINUTES', (int) kq_env('LOGIN_LOCKOUT_MINUTES', 15));

// Paramètres d'envoi d'emails (SMTP)
define('MAIL_ENABLED', filter_var(kq_env('MAIL_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN));
define('MAIL_HOST', (string) kq_env('MAIL_HOST', 'smtp.example.com'));
define('MAIL_PORT', (int) kq_env('MAIL_PORT', 587));
define('MAIL_ENCRYPTION', (string) kq_env('MAIL_ENCRYPTION', 'tls'));
define('MAIL_USERNAME', (string) kq_env('MAIL_USERNAME', ''));
define('MAIL_PASSWORD', (string) kq_env('MAIL_PASSWORD', ''));
define('MAIL_FROM_EMAIL', (string) kq_env('MAIL_FROM_EMAIL', 'no-reply@kingandqween.com'));
define('MAIL_FROM_NAME', (string) kq_env('MAIL_FROM_NAME', 'King and Qween Salon'));
