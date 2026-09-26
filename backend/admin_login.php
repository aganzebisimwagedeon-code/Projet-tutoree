<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();

// Rediriger si déjà connecté en tant qu'admin
if (has_role('admin')) {
    redirect(BASE_URL . "backend/admin_dashboard.php");
}

$email = $password = "";
$email_err = $password_err = $login_err = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        $login_err = "Session expirée ou jeton CSRF invalide. Veuillez réessayer.";
    } else {
        $rawEmail = trim($_POST["email"] ?? "");
        if ($rawEmail === "") {
            $email_err = "Veuillez entrer votre email.";
        } else {
            $validEmail = validate_email_address($rawEmail);
            if ($validEmail === null) {
                $email_err = "Format d'email invalide.";
                $email = sanitize_input($rawEmail, 255);
            } else {
                $email = $validEmail;
            }
        }

        $password = (string) ($_POST["password"] ?? "");
        if (trim($password) === "") {
            $password_err = "Veuillez entrer votre mot de passe.";
        }

        if (empty($email_err) && empty($password_err)) {
            $rateCheck = check_login_rate_limit($mysqli, $email);
            if (!$rateCheck['allowed']) {
                $login_err = $rateCheck['message'];
            }
        }

        if (empty($email_err) && empty($password_err) && empty($login_err)) {
            $sql = "SELECT id, username, email, password, role FROM users WHERE email = ? AND role = 'admin' LIMIT 1";

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

                                redirect(BASE_URL . "backend/admin_dashboard.php");
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin - King and Qween</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/responsive.css">
</head>
<body>
    <div class="admin-login-container">
        <div class="admin-login-form">
            <h1>Connexion Administrateur</h1>
            <p>King and Qween - Espace Admin</p>

            <?php if (!empty($login_err)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($login_err); ?></div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
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
            </form>

            <p><a href="<?php echo BASE_URL; ?>index.php">Retour au site</a></p>
        </div>
    </div>
</body>
</html>
