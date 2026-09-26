<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/functions.php";

start_secure_session();

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$new_password = $confirm_password = "";
$password_err = $token_err = $success_msg = "";

// Vérifier le token
if ($token === '' || !preg_match('/^[a-f0-9]{32,128}$/i', $token)) {
    $token_err = "Lien de réinitialisation invalide.";
} else {
    $sql = "SELECT id, reset_token_expiry FROM users WHERE reset_token = ? LIMIT 1";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $token_err = "Token invalide ou expiré.";
        } else {
            $stmt->bind_result($user_id, $expiry);
            $stmt->fetch();
            if (empty($expiry) || strtotime($expiry) < time()) {
                $token_err = "Le lien de réinitialisation a expiré.";
            }
        }
        $stmt->close();
    }
}

// Traiter le nouveau mot de passe
if ($_SERVER["REQUEST_METHOD"] === "POST" && empty($token_err)) {
    if (!verify_csrf_token()) {
        $password_err = "Jeton de sécurité invalide. Veuillez réessayer.";
    } else {
        $new_password = (string) ($_POST["new_password"] ?? "");
        $confirm_password = (string) ($_POST["confirm_password"] ?? "");

        $strength = validate_password_strength($new_password);
        if (!$strength['valid']) {
            $password_err = $strength['error'];
        } elseif ($new_password !== $confirm_password) {
            $password_err = "Les mots de passe ne correspondent pas.";
        }

        if (empty($password_err)) {
            $hashed_password = hash_password($new_password);
            $update_sql = "UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL, failed_login_attempts = 0, locked_until = NULL WHERE reset_token = ?";
            if ($update_stmt = $mysqli->prepare($update_sql)) {
                $update_stmt->bind_param("ss", $hashed_password, $token);
                if ($update_stmt->execute()) {
                    $success_msg = "Votre mot de passe a été réinitialisé avec succès !";
                    header("refresh:3;url=" . BASE_URL . "login.php?reset=success");
                } else {
                    $token_err = "Erreur lors de la mise à jour. Veuillez réessayer.";
                }
                $update_stmt->close();
            }
        }
    }
}

include __DIR__ . "/includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Réinitialiser votre mot de passe</h1>
    </div>
</section>

<section class="auth-form">
    <div class="container">
        <?php if (!empty($token_err)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($token_err); ?></div>
            <p><a href="<?php echo BASE_URL; ?>forgot_password.php" class="btn">Demander un nouveau lien</a></p>
        <?php elseif (!empty($success_msg)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
            <p>Vous serez redirigé vers la page de connexion dans quelques instants...</p>
        <?php else: ?>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?token=' . urlencode($token); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe (min. 8 caractères, lettres et chiffres) :</label>
                    <input type="password" class="<?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>"
                           id="new_password" name="new_password" minlength="8" required>
                    <span class="invalid-feedback"><?php echo htmlspecialchars($password_err); ?></span>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmer le nouveau mot de passe :</label>
                    <input type="password" class="<?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>"
                           id="confirm_password" name="confirm_password" minlength="8" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn">Réinitialiser le mot de passe</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . "/includes/footer.php"; ?>
