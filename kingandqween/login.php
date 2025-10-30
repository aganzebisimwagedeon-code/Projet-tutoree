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
        $sql = "SELECT id, username, email, password, role FROM users WHERE email = ?";

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

                            // Rediriger vers la page d'origine ou le tableau de bord
                            if (isset($_GET["redirect"]) && !empty($_GET["redirect"])) {
                                redirect(urldecode($_GET["redirect"]));
                            } else if ($role === 'admin') {
                                redirect(BASE_URL . "backend/admin_dashboard.php");
                            } else if ($role === 'coiffeur') {
                                redirect(BASE_URL . "backend/coiffeur_dashboard.php");
                            } else {
                                redirect(BASE_URL . "frontend/client_dashboard.php");
                            }
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
            echo '<div class="alert alert-danger">' . $login_err . '</div>';
        }
        if (!empty($reset_success)) {
            echo '<div class="alert alert-success">' . $reset_success . '</div>';
        }
        ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); 
        if (isset($_GET["redirect"])) { echo "?redirect=" . urlencode($_GET["redirect"]); } ?>" method="post">
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
            <p>Vous n'avez pas de compte ? <a href="<?php echo BASE_URL; ?>register.php">Inscrivez-vous ici</a>.</p>
            <p><a href="<?php echo BASE_URL; ?>forgot_password.php">Mot de passe oublié ?</a></p>
        </form>
    </div>
</section>

<?php include __DIR__ . "/includes/footer.php"; ?>
