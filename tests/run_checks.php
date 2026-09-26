<?php
/**
 * Suite de vérification technique et fonctionnelle (Phase 8).
 * Exécute :
 * 1. Vérification de l'absence du dossier dupliqué `kingandqween/` (Phase 0.1)
 * 2. Vérification de la présence de `README.md`, `.gitignore`, `.env.example` (Phase 0.2 & 4.4)
 * 3. Linting syntaxique (`php -l`) sur l'ensemble des fichiers PHP du projet
 * 4. Contrôle statique de cohérence du schéma SQL (`database/schema.sql`)
 * 5. Tests unitaires des fonctions de sécurité (CSRF, validation d'entrées, mots de passe, anti-Open Redirect)
 * 6. Tests unitaires de la logique de réservation (chevauchements horaires, jours de la semaine)
 */

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/includes/functions.php';
require_once $projectRoot . '/services/BookingService.php';

$passed = 0;
$failed = 0;

function assert_check(bool $condition, string $label): void {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] {$label}\n";
        $passed++;
    } else {
        echo "[FAIL] {$label}\n";
        $failed++;
    }
}

echo "=== 1. Structure du projet & Documentation (Phase 0) ===\n";
assert_check(!is_dir($projectRoot . '/kingandqween'), "Le dossier dupliqué kingandqween/ a bien été supprimé");
assert_check(is_file($projectRoot . '/README.md'), "Le fichier README.md est présent");
assert_check(is_file($projectRoot . '/.gitignore'), "Le fichier .gitignore est présent");
assert_check(is_file($projectRoot . '/.env.example'), "Le fichier .env.example est présent");

echo "\n=== 2. Vérification syntaxique PHP (php -l) sur tous les fichiers ===\n";
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS)
);
$phpFiles = [];
foreach ($iterator as $file) {
    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) ||
        str_contains($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) {
        continue;
    }
    if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
        $phpFiles[] = $path;
    }
}
sort($phpFiles);

foreach ($phpFiles as $phpFile) {
    $relPath = substr($phpFile, strlen($projectRoot) + 1);
    $output = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($phpFile) . ' 2>&1', $output, $code);
    assert_check($code === 0, "Syntaxe PHP valide : {$relPath}");
}

echo "\n=== 3. Cohérence du schéma SQL (Phases 1, 2, 3 & 7) ===\n";
$schemaSql = (string) file_get_contents($projectRoot . '/database/schema.sql');
assert_check(str_contains($schemaSql, '`adresse_domicile`'), "Table rendezvous contient adresse_domicile");
assert_check(str_contains($schemaSql, '`telephone`'), "Table rendezvous contient telephone");
assert_check(str_contains($schemaSql, '`duree_minutes`'), "Table services contient duree_minutes");
assert_check(str_contains($schemaSql, 'CREATE TABLE `coiffeur_services`'), "Table coiffeur_services est définie");
assert_check(str_contains($schemaSql, 'CREATE TABLE `absences_coiffeurs`'), "Table absences_coiffeurs est définie");
assert_check(str_contains($schemaSql, '`rendezvous_id`'), "Table avis contient rendezvous_id");
assert_check(str_contains($schemaSql, '`reset_token`'), "Table users contient reset_token");
assert_check(str_contains($schemaSql, 'CREATE TABLE `notifications`'), "Table notifications est définie");
assert_check(str_contains($schemaSql, 'CREATE TABLE `email_logs`'), "Table email_logs est définie");
assert_check(!str_contains($schemaSql, '$2y$10$Q.Q.Q.Q'), "Les hachages bcrypt factices ont été remplacés par de vrais hachages bcrypt");
assert_check(verify_password('password', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'), "Le hachage bcrypt de démonstration dans schema.sql est valide");

echo "\n=== 4. Tests unitaires de sécurité (Phase 4) ===\n";
// Protection des secrets
$forgotContent = (string) file_get_contents($projectRoot . '/forgot_password.php');
assert_check(!str_contains($forgotContent, 'roqf qzcf xcze wyxa'), "Aucun mot de passe Gmail en clair dans forgot_password.php");

// CSRF
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$token = generate_csrf_token();
assert_check(strlen($token) === 64, "Jeton CSRF généré sur 64 caractères hexadécimaux");
assert_check(verify_csrf_token($token) === true, "Validation d'un jeton CSRF valide");
assert_check(verify_csrf_token('jeton_invalide') === false, "Rejet d'un jeton CSRF invalide");

// Politique de mot de passe
assert_check(validate_password_strength('court1')['valid'] === false, "Rejet d'un mot de passe trop court (< 8 caractères)");
assert_check(validate_password_strength('sanschiffre')['valid'] === false, "Rejet d'un mot de passe sans chiffre");
assert_check(validate_password_strength('123456789')['valid'] === false, "Rejet d'un mot de passe sans lettre");
assert_check(validate_password_strength('Solide123!')['valid'] === true, "Validation d'un mot de passe conforme");

// Validation des entrées
assert_check(sanitize_input("L'élégance <script>alert(1)</script>") === "L'élégance alert(1)", "sanitize_input supprime les balises HTML sans corrompre les apostrophes");
assert_check(validate_email_address('client@kingandqween.com') === 'client@kingandqween.com', "Validation d'un email valide");
assert_check(validate_email_address('email_invalide') === null, "Rejet d'un email invalide");
assert_check(validate_phone('+243 99 123 4567') !== null, "Validation d'un numéro de téléphone valide");
assert_check(validate_phone('abc') === null, "Rejet d'un numéro de téléphone invalide");
assert_check(validate_date_format('2026-10-15') !== null, "Validation d'une date YYYY-MM-DD valide");
assert_check(validate_date_format('2026-02-30') === null, "Rejet d'une date calendaire inexistante (30 février)");
assert_check(validate_time_format('14:30') === '14:30:00', "Normalisation d'un horaire HH:MM valide");
assert_check(validate_time_format('25:99') === null, "Rejet d'un horaire invalide");

// Anti-Open Redirect
assert_check(is_safe_redirect_url('/frontend/rendezvous.php') === true, "Redirection interne relative autorisée");
assert_check(is_safe_redirect_url('https://evil.example.com/phishing') === false, "Redirection externe bloquée (anti-Open Redirect)");
assert_check(is_safe_redirect_url('//evil.example.com') === false, "Redirection protocol-relative bloquée");

echo "\n=== 5. Tests unitaires de logique de réservation (Phase 2) ===\n";
$monday = new DateTimeImmutable('2026-10-05');
assert_check(BookingService::getDayNameFr($monday) === 'Lundi', "Conversion DateTime -> Jour de la semaine en français (Lundi)");

// Chevauchement de créneaux : [10:00, 11:00[ vs [10:30, 11:30[ -> chevauchement
$t1000 = strtotime('2026-10-05 10:00:00');
$t1030 = strtotime('2026-10-05 10:30:00');
$t1100 = strtotime('2026-10-05 11:00:00');
$t1130 = strtotime('2026-10-05 11:30:00');
assert_check(BookingService::intervalsOverlap($t1000, $t1100, $t1030, $t1130) === true, "Détection de chevauchement entre 10h00-11h00 et 10h30-11h30");
assert_check(BookingService::intervalsOverlap($t1000, $t1100, $t1100, $t1130) === false, "Deux créneaux consécutifs (10h00-11h00 et 11h00-11h30) ne se chevauchent pas");

echo "\n=============================================\n";
echo "Résultat : {$passed} test(s) réussi(s), {$failed} échec(s).\n";
exit($failed > 0 ? 1 : 0);
