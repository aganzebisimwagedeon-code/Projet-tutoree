<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/functions.php";

start_secure_session();

// Rediriger si déjà connecté
if (is_logged_in()) {
    redirect(BASE_URL . "index.php");
}

$username = $email = $password = $confirm_password = "";
$username_err = $email_err = $password_err = $confirm_password_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Valider le nom d'utilisateur
    if (empty(trim($_POST["username"]))) {
        $username_err = "Veuillez entrer un nom d'utilisateur.";
    } else {
        $username = sanitize_input($_POST["username"]);
    }

    // Valider l'email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Veuillez entrer une adresse email.";
    } else {
        // Vérifier si l'email existe déjà
        $sql = "SELECT id FROM users WHERE email = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = trim($_POST["email"]);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $email_err = "Cet email est déjà utilisé.";
                } else {
                    $email = sanitize_input($_POST["email"]);
                }
            } else {
                echo "Oops! Une erreur est survenue. Veuillez réessayer plus tard.";
            }
            $stmt->close();
        }
    }

    // Valider le mot de passe
    if (empty(trim($_POST["password"]))) {
        $password_err = "Veuillez entrer un mot de passe.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Le mot de passe doit contenir au moins 6 caractères.";
    } else {
        $password = sanitize_input($_POST["password"]);
    }

    // Valider la confirmation du mot de passe
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Veuillez confirmer le mot de passe.";
    } else {
        $confirm_password = sanitize_input($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Les mots de passe ne correspondent pas.";
        }
    }

    // Vérifier les erreurs avant d'insérer dans la base de données
    if (empty($username_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)) {

        $sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'client')";

        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("sss", $param_username, $param_email, $param_password);

            $param_username = $username;
            $param_email = $email;
            $param_password = hash_password($password); // Hacher le mot de passe

            if ($stmt->execute()) {
                // Rediriger vers la page de connexion
                redirect(BASE_URL . "login.php");
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
        <h1>Inscription</h1>
    </div>
</section>

<section class="auth-form">
    <div class="container">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="username">Nom d'utilisateur :</label>
                <input type="text" name="username" id="username" class="<?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>" required>
                <span class="invalid-feedback"><?php echo $username_err; ?></span>
            </div>
            <div class="form-group">
                <label for="email">Email :</label>
                <input type="email" name="email" id="email" class="<?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>" required>
                <span class="invalid-feedback"><?php echo $email_err; ?></span>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe :</label>
                <input type="password" name="password" id="password" class="<?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="" required>
                <span class="invalid-feedback"><?php echo $password_err; ?></span>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe :</label>
                <input type="password" name="confirm_password" id="confirm_password" class="<?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" value="" required>
                <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
            </div>
            <div class="form-group">
                <button type="submit" class="btn">S'inscrire</button>
            </div>
            <p>Vous avez déjà un compte ? <a href="<?php echo BASE_URL; ?>login.php">Connectez-vous ici</a>.</p>
        </form>
    </div>
</section>

<?php include __DIR__ . "/includes/footer.php"; ?>

