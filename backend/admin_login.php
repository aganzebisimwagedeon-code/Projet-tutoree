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

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Valider l'email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Veuillez entrer votre email.";
    } else {
        $email = sanitize_input($_POST["email"]);
    }

    // Valider le mot de passe
    if (empty(trim($_POST["password"]))) {
        $password_err = "Veuillez entrer votre mot de passe.";
    } else {
        $password = sanitize_input($_POST["password"]);
    }

    // Valider les identifiants
    if (empty($email_err) && empty($password_err)) {
        $sql = "SELECT id, username, email, password, role FROM users WHERE email = ? AND role = 'admin'";

        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = $email;

            if ($stmt->execute()) {
                $stmt->store_result();

                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($id, $username, $email, $hashed_password, $role);
                    if ($stmt->fetch()) {
                        if (verify_password($password, $hashed_password)) {
                            // Mot de passe correct, démarrer une nouvelle session
                            session_regenerate_id(true); // Régénérer l'ID de session

                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["email"] = $email;
                            $_SESSION["role"] = $role;

                            // Rediriger vers le tableau de bord admin
                            redirect(BASE_URL . "backend/admin_dashboard.php");
                        } else {
                            $login_err = "Email ou mot de passe invalide.";
                        }
                    }
                } else {
                    $login_err = "Email ou mot de passe invalide.";
                }
            } else {
                echo "Oops! Une erreur est survenue. Veuillez réessayer plus tard.";
            }

            $stmt->close();
        }
    }

    $mysqli->close();
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

            <?php 
            if (!empty($login_err)) {
                echo '<div class="alert alert-danger">' . $login_err . '</div>';
            }
            ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label for="email">Email :</label>
                    <input type="email" name="email" id="email" class="<?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>" required>
                    <span class="invalid-feedback"><?php echo $email_err; ?></span>
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe :</label>
                    <input type="password" name="password" id="password" class="<?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" required>
                    <span class="invalid-feedback"><?php echo $password_err; ?></span>
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

