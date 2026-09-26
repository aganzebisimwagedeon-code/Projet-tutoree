<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/services/NotificationService.php";

start_secure_session();

$email = "";
$email_err = $success_msg = $error_msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        $error_msg = "Jeton de sécurité invalide. Veuillez réessayer.";
    } else {
        $rawEmail = trim($_POST["email"] ?? "");
        if ($rawEmail === "") {
            $email_err = "Veuillez entrer votre email.";
        } else {
            $validEmail = validate_email_address($rawEmail);
            if ($validEmail === null) {
                $email_err = "Veuillez fournir une adresse email valide.";
                $email = sanitize_input($rawEmail, 255);
            } else {
                $email = $validEmail;
            }
        }

        if (empty($email_err)) {
            $sql = "SELECT id, email FROM users WHERE email = ? LIMIT 1";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("s", $email);
                if ($stmt->execute()) {
                    $stmt->store_result();
                    if ($stmt->num_rows === 1) {
                        $token = bin2hex(random_bytes(32));
                        $expiry = date("Y-m-d H:i:s", time() + 3600); // 1 heure

                        $update_sql = "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?";
                        if ($update_stmt = $mysqli->prepare($update_sql)) {
                            $update_stmt->bind_param("sss", $token, $expiry, $email);
                            if ($update_stmt->execute()) {
                                $reset_link = BASE_URL . "reset_password.php?token=" . urlencode($token);
                                $bodyHtml = "<p>Bonjour,</p>"
                                    . "<p>Vous avez demandé la réinitialisation de votre mot de passe sur <strong>King and Qween</strong>.</p>"
                                    . "<p style='margin: 25px 0; text-align: center;'>"
                                    . "<a href='" . htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8') . "' style='background:#6c757d;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;'>Réinitialiser mon mot de passe</a>"
                                    . "</p>"
                                    . "<p>Ce lien expirera dans 1 heure. Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.</p>";

                                NotificationService::sendEmail(
                                    $mysqli,
                                    $email,
                                    'Réinitialisation de votre mot de passe - King and Qween',
                                    $bodyHtml,
                                    'password_reset'
                                );
                            }
                            $update_stmt->close();
                        }
                    }
                    // Message uniforme pour ne pas divulguer l'existence d'un compte
                    $success_msg = "Si un compte est associé à cette adresse email, un lien de réinitialisation vous a été envoyé.";
                } else {
                    $error_msg = "Une erreur est survenue. Veuillez réessayer plus tard.";
                }
                $stmt->close();
            }
        }
    }
}

include __DIR__ . "/includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Mot de passe oublié</h1>
    </div>
</section>

<section class="auth-form">
    <div class="container">
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php else: ?>
            <p>Entrez votre adresse email pour recevoir un lien de réinitialisation de votre mot de passe.</p>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label for="email">Votre adresse email :</label>
                    <input type="email" class="<?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>"
                           id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    <span class="invalid-feedback"><?php echo htmlspecialchars($email_err); ?></span>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn">Envoyer le lien de réinitialisation</button>
                </div>
                <p><a href="<?php echo BASE_URL; ?>login.php">← Retour à la connexion</a></p>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . "/includes/footer.php"; ?>
