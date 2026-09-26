<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/functions.php";

start_secure_session();

// Rediriger si déjà connecté
if (is_logged_in()) {
    redirect(BASE_URL . "index.php");
}

$email = $password = "";
$email_err = $password_err = $login_err = $reset_success = "";

// Vérifier si un message de succès de réinitialisation est présent
if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $reset_success = "Votre mot de passe a été réinitialisé avec succès !";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        $login_err = "Session expirée ou jeton de sécurité invalide. Veuillez réessayer.";
    } else {
        // Valider l'email
        $rawEmail = trim($_POST["email"] ?? "");
        if ($rawEmail === "") {
            $email_err = "Veuillez entrer votre email.";
        } else {
            $validEmail = validate_email_address($rawEmail);
            if ($validEmail === null) {
                $email_err = "Format d'adresse email invalide.";
                $email = sanitize_input($rawEmail, 255);
            } else {
                $email = $validEmail;
            }
        }

        // Valider le mot de passe (sans altérer les caractères spéciaux)
        $password = (string) ($_POST["password"] ?? "");
        if (trim($password) === "") {
            $password_err = "Veuillez entrer votre mot de passe.";
        }

        // Vérifier la limitation des tentatives (anti-force brute - Phase 4.3)
        if (empty($email_err) && empty($password_err)) {
            $rateCheck = check_login_rate_limit($mysqli, $email);
            if (!$rateCheck['allowed']) {
                $login_err = $rateCheck['message'];
            }
        }

        // Valider les identifiants
        if (empty($email_err) && empty($password_err) && empty($login_err)) {
            $sql = "SELECT id, username, email, password, role FROM users WHERE email = ? LIMIT 1";

            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("s", $email);

                if ($stmt->execute()) {
                    $stmt->store_result();

                    if ($stmt->num_rows === 1) {
                        $stmt->bind_result($id, $username, $db_email, $hashed_password, $role);
                        if ($stmt->fetch()) {
                            if (verify_password($password, $hashed_password)) {
                                record_login_attempt($mysqli, $email, true);
                                session_regenerate_id(true);

                                $_SESSION["loggedin"] = true;
                                $_SESSION["user_id"] = $id;
                                $_SESSION["username"] = $username;
                                $_SESSION["email"] = $db_email;
                                $_SESSION["role"] = $role;

                                $defaultTarget = match ($role) {
                                    'admin' => BASE_URL . "backend/admin_dashboard.php",
                                    'coiffeur' => BASE_URL . "backend/coiffeur_dashboard.php",
                                    default => BASE_URL . "frontend/client_dashboard.php",
                                };

                                if (!empty($_GET["redirect"])) {
                                    safe_redirect(urldecode((string) $_GET["redirect"]), $defaultTarget);
                                }
                                redirect($defaultTarget);
                            } else {
                                record_login_attempt($mysqli, $email, false);
                                $login_err = "Email ou mot de passe invalide.";
                            }
                        }
                    } else {
                        record_login_attempt($mysqli, $email, false);
                        $login_err = "Email ou mot de passe invalide.";
                    }
                } else {
                    $login_err = "Une erreur est survenue. Veuillez réessayer plus tard.";
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
        <h1>Connexion</h1>
    </div>
</section>

<section class="auth-form">
    <div class="container">
        <?php 
        if (!empty($login_err)) {
            echo '<div class="alert alert-danger">' . htmlspecialchars($login_err) . '</div>';
        }
        if (!empty($reset_success)) {
            echo '<div class="alert alert-success">' . htmlspecialchars($reset_success) . '</div>';
        }
        ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); 
        if (isset($_GET["redirect"])) { echo "?redirect=" . urlencode((string) $_GET["redirect"]); } ?>" method="post">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="email">Email :</label>
                <input type="email" name="email" id="email" class="<?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>" required>
                <span class="invalid-feedback"><?php echo htmlspecialchars($email_err); ?></span>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe :</label>
                <input type="password" name="password" id="password" class="<?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" required>
                <span class="invalid-feedback"><?php echo htmlspecialchars($password_err); ?></span>
            </div>
            <div class="form-group">
                <button type="submit" class="btn">Se connecter</button>
            </div>
            <p>Vous n'avez pas de compte ? <a href="<?php echo BASE_URL; ?>register.php">Inscrivez-vous ici</a>.</p>
            <p><a href="<?php echo BASE_URL; ?>forgot_password.php">Mot de passe oublié ?</a></p>
        </form>
    </div>
</section>

<?php include __DIR__ . "/includes/footer.php"; ?>
