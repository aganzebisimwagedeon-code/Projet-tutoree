<?php
/**
 * Fonctions utilitaires centralisées (Authentification, Sécurité CSRF, Validation, Messages Flash, Images).
 */

/**
 * Vérifie si la requête courante utilise HTTPS.
 */
function is_https_request(): bool {
    return (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );
}

/**
 * Démarre une session sécurisée avec cookie adaptatif selon HTTPS/local (Phase 1.2 & 4.3).
 */
function start_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) {
            session_set_cookie_params([
                'lifetime' => 3600,
                'path' => '/',
                'domain' => '',
                'secure' => is_https_request(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        session_start();
        if (!isset($_SESSION['initiated'])) {
            if (!headers_sent()) {
                session_regenerate_id(true);
            }
            $_SESSION['initiated'] = true;
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
}

/**
 * Vérifie si l'utilisateur est connecté.
 */
function is_logged_in(): bool {
    return isset($_SESSION["user_id"]) && !empty($_SESSION["user_id"]);
}

/**
 * Vérifie si l'utilisateur possède un rôle spécifique.
 */
function has_role(string $role): bool {
    return is_logged_in() && isset($_SESSION["role"]) && $_SESSION["role"] === $role;
}

/**
 * Redirige l'utilisateur vers une URL.
 */
function redirect(string $location): never {
    header("Location: " . $location);
    exit;
}

/**
 * Vérifie qu'une URL de redirection est interne au site (anti Open-Redirect - Phase 4.3).
 */
function is_safe_redirect_url(string $url): bool {
    $url = trim($url);
    if ($url === '' || str_starts_with($url, '//') || str_contains($url, "\r") || str_contains($url, "\n")) {
        return false;
    }
    if (defined('BASE_URL') && str_starts_with($url, BASE_URL)) {
        return true;
    }
    $parsed = parse_url($url);
    if ($parsed === false) {
        return false;
    }
    if (isset($parsed['scheme']) || isset($parsed['host'])) {
        return false;
    }
    return str_starts_with($url, '/') || !str_contains($url, ':');
}

/**
 * Redirige de manière sécurisée vers une URL interne ou vers l'URL par défaut.
 */
function safe_redirect(?string $target, string $fallback): never {
    if ($target !== null && is_safe_redirect_url($target)) {
        redirect($target);
    }
    redirect($fallback);
}

/**
 * Exige qu'un utilisateur soit connecté, sinon redirige vers la page de connexion.
 */
function require_login(): void {
    if (!is_logged_in()) {
        $currentUri = $_SERVER["REQUEST_URI"] ?? '';
        $loginUrl = (defined('BASE_URL') ? BASE_URL : '/') . "login.php";
        if ($currentUri !== '') {
            $loginUrl .= "?redirect=" . urlencode($currentUri);
        }
        redirect($loginUrl);
    }
}

/**
 * Exige un rôle spécifique, sinon redirige.
 */
function require_role(string $role, ?string $customLoginUrl = null): void {
    if (!is_logged_in() || !has_role($role)) {
        $base = defined('BASE_URL') ? BASE_URL : '/';
        $target = $customLoginUrl ?? ($role === 'admin' ? $base . "backend/admin_login.php" : $base . "login.php");
        redirect($target);
    }
}

/**
 * Génère un hachage de mot de passe sécurisé (Phase 4.3).
 */
function hash_password(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Vérifie un mot de passe haché.
 */
function verify_password(string $password, string $hashed_password): bool {
    return password_verify($password, $hashed_password);
}

/**
 * Valide la robustesse d'un mot de passe (Phase 4.3).
 * Exige au moins 8 caractères, une lettre et un chiffre.
 * @return array{valid: bool, error: string}
 */
function validate_password_strength(string $password): array {
    if (strlen($password) < 8) {
        return ['valid' => false, 'error' => "Le mot de passe doit contenir au moins 8 caractères."];
    }
    if (strlen($password) > 128) {
        return ['valid' => false, 'error' => "Le mot de passe ne doit pas dépasser 128 caractères."];
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'error' => "Le mot de passe doit contenir au moins une lettre et un chiffre."];
    }
    return ['valid' => true, 'error' => ''];
}

/**
 * Génère ou retourne le jeton CSRF de la session courante (Phase 4.1).
 */
function generate_csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        start_secure_session();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie la validité du jeton CSRF soumis (Phase 4.1).
 */
function verify_csrf_token(?string $token = null): bool {
    if (session_status() === PHP_SESSION_NONE) {
        start_secure_session();
    }
    $submitted = $token ?? ($_POST['csrf_token'] ?? '');
    if (!is_string($submitted) || $submitted === '' || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submitted);
}

/**
 * Retourne le champ HTML caché contenant le jeton CSRF.
 */
function csrf_field(): string {
    $token = htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Nettoie une chaîne de caractères en entrée sans corrompre les apostrophes en base (Phase 4.2).
 */
function sanitize_input(mixed $data, int $maxLength = 2000): string {
    if ($data === null || !is_scalar($data)) {
        return '';
    }
    $clean = trim((string) $data);
    // Supprimer les octets nuls et balises HTML tout en préservant les apostrophes/accents
    $clean = str_replace("\0", '', $clean);
    $clean = strip_tags($clean);
    if ($maxLength > 0 && mb_strlen($clean, 'UTF-8') > $maxLength) {
        $clean = mb_substr($clean, 0, $maxLength, 'UTF-8');
    }
    return $clean;
}

/**
 * Échappe une valeur pour un affichage HTML sécurisé.
 */
function e(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Valide et convertit un identifiant ou entier (Phase 4.2).
 */
function validate_int(mixed $value, int $min = 1, ?int $max = null): ?int {
    $options = ['options' => ['min_range' => $min]];
    if ($max !== null) {
        $options['options']['max_range'] = $max;
    }
    $filtered = filter_var($value, FILTER_VALIDATE_INT, $options);
    return ($filtered === false) ? null : $filtered;
}

/**
 * Valide une adresse email (Phase 4.2).
 */
function validate_email_address(mixed $email): ?string {
    $clean = trim((string) ($email ?? ''));
    if ($clean === '' || strlen($clean) > 255) {
        return null;
    }
    $valid = filter_var($clean, FILTER_VALIDATE_EMAIL);
    return ($valid === false) ? null : $valid;
}

/**
 * Valide un numéro de téléphone (Phase 4.2).
 */
function validate_phone(mixed $phone): ?string {
    $clean = trim((string) ($phone ?? ''));
    if (!preg_match('/^\+?[0-9\s\-\.\(\)]{8,25}$/', $clean)) {
        return null;
    }
    return $clean;
}

/**
 * Valide une date selon un format strict avec DateTimeImmutable (Phase 4.2).
 */
function validate_date_format(?string $date, string $format = 'Y-m-d'): ?DateTimeImmutable {
    $date = trim((string) $date);
    if ($date === '') {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('!' . $format, $date);
    if ($dt && $dt->format($format) === $date) {
        return $dt;
    }
    return null;
}

/**
 * Valide une heure (HH:MM ou HH:MM:SS) et retourne HH:MM:SS (Phase 4.2).
 */
function validate_time_format(?string $time): ?string {
    $time = trim((string) $time);
    if (preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])(?::([0-5][0-9]))?$/', $time, $m)) {
        $sec = $m[3] ?? '00';
        return "{$m[1]}:{$m[2]}:{$sec}";
    }
    return null;
}

/**
 * Vérifie la limitation des tentatives de connexion (anti force-brute - Phase 4.3).
 * @return array{allowed: bool, wait_minutes: int, message: string}
 */
function check_login_rate_limit(mysqli $mysqli, string $email): array {
    $maxAttempts = defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5;
    $lockoutMinutes = defined('LOGIN_LOCKOUT_MINUTES') ? LOGIN_LOCKOUT_MINUTES : 15;
    $key = 'login_attempts_' . md5(strtolower(trim($email)) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'local'));

    // 1. Vérification en session
    if (isset($_SESSION[$key])) {
        $data = $_SESSION[$key];
        if (!empty($data['locked_until']) && $data['locked_until'] > time()) {
            $wait = (int) ceil(($data['locked_until'] - time()) / 60);
            return [
                'allowed' => false,
                'wait_minutes' => $wait,
                'message' => "Trop de tentatives de connexion. Veuillez réessayer dans {$wait} minute(s)."
            ];
        }
        if (!empty($data['locked_until']) && $data['locked_until'] <= time()) {
            unset($_SESSION[$key]);
        }
    }

    // 2. Vérification en base si les colonnes existent
    $sql = "SELECT failed_login_attempts, locked_until FROM users WHERE email = ? LIMIT 1";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                if (!empty($row['locked_until'])) {
                    $lockedTs = strtotime($row['locked_until']);
                    if ($lockedTs !== false && $lockedTs > time()) {
                        $wait = (int) ceil(($lockedTs - time()) / 60);
                        $stmt->close();
                        return [
                            'allowed' => false,
                            'wait_minutes' => $wait,
                            'message' => "Ce compte est temporairement verrouillé suite à plusieurs échecs. Réessayez dans {$wait} minute(s)."
                        ];
                    }
                }
            }
        }
        $stmt->close();
    }

    return ['allowed' => true, 'wait_minutes' => 0, 'message' => ''];
}

/**
 * Enregistre le résultat d'une tentative de connexion (Phase 4.3).
 */
function record_login_attempt(mysqli $mysqli, string $email, bool $success): void {
    $maxAttempts = defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5;
    $lockoutMinutes = defined('LOGIN_LOCKOUT_MINUTES') ? LOGIN_LOCKOUT_MINUTES : 15;
    $key = 'login_attempts_' . md5(strtolower(trim($email)) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'local'));

    if ($success) {
        unset($_SESSION[$key]);
        if ($stmt = $mysqli->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE email = ?")) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }

    $attempts = ($_SESSION[$key]['count'] ?? 0) + 1;
    $lockedUntil = ($attempts >= $maxAttempts) ? (time() + ($lockoutMinutes * 60)) : null;
    $_SESSION[$key] = [
        'count' => $attempts,
        'locked_until' => $lockedUntil
    ];

    if ($lockedUntil !== null) {
        $lockedUntilStr = date('Y-m-d H:i:s', $lockedUntil);
        if ($stmt = $mysqli->prepare("UPDATE users SET failed_login_attempts = failed_login_attempts + 1, locked_until = ? WHERE email = ?")) {
            $stmt->bind_param("ss", $lockedUntilStr, $email);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        if ($stmt = $mysqli->prepare("UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE email = ?")) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->close();
        }
    }
}

/**
 * Ajoute un message flash en session (Phase 5.2).
 */
function set_flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        start_secure_session();
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Récupère et vide les messages flash en session.
 */
function get_flashes(): array {
    if (empty($_SESSION['flash_messages'])) {
        return [];
    }
    $flashes = $_SESSION['flash_messages'];
    unset($_SESSION['flash_messages']);
    return $flashes;
}

/**
 * Redimensionne une image téléchargée en conservant ses proportions (Correction du bug fatal dans admin_coiffeurs.php).
 */
function resizeImage(string $filePath, int $maxWidth = 500, int $maxHeight = 500): bool {
    if (!file_exists($filePath) || !function_exists('getimagesize') || !function_exists('imagecreatetruecolor')) {
        return true;
    }
    $info = @getimagesize($filePath);
    if ($info === false) {
        return false;
    }
    [$width, $height, $type] = $info;
    if ($width <= 0 || $height <= 0 || ($width <= $maxWidth && $height <= $maxHeight)) {
        return true;
    }

    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newWidth = max(1, (int) round($width * $ratio));
    $newHeight = max(1, (int) round($height * $ratio));

    $srcImg = match ($type) {
        IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($filePath) : false,
        IMAGETYPE_PNG  => function_exists('imagecreatefrompng') ? @imagecreatefrompng($filePath) : false,
        IMAGETYPE_GIF  => function_exists('imagecreatefromgif') ? @imagecreatefromgif($filePath) : false,
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($filePath) : false,
        default        => false
    };

    if (!$srcImg) {
        return true;
    }

    $dstImg = imagecreatetruecolor($newWidth, $newHeight);
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP || $type === IMAGETYPE_GIF) {
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
    }

    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $saved = match ($type) {
        IMAGETYPE_JPEG => imagejpeg($dstImg, $filePath, 85),
        IMAGETYPE_PNG  => imagepng($dstImg, $filePath, 6),
        IMAGETYPE_GIF  => imagegif($dstImg, $filePath),
        IMAGETYPE_WEBP => imagewebp($dstImg, $filePath, 85),
        default        => true
    };

    imagedestroy($srcImg);
    imagedestroy($dstImg);
    return (bool) $saved;
}
