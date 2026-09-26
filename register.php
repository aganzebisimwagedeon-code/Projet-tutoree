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
$username_err = $email_err = $password_err = $confirm_password_err = $general_err = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        $general_err = "Jeton de sécurité invalide. Veuillez soumettre à nouveau le formulaire.";
    } else {
        // Valider le nom d'utilisateur
        $username = sanitize_input($_POST["username"] ?? "", 100);
        if ($username === "") {
            $username_err = "Veuillez entrer un nom d'utilisateur.";
        } elseif (mb_strlen($username) < 2) {
            $username_err = "Le nom d'utilisateur doit contenir au moins 2 caractères.";
        }

        // Valider l'email
        $rawEmail = trim($_POST["email"] ?? "");
        if ($rawEmail === "") {
            $email_err = "Veuillez entrer une adresse email.";
        } else {
            $validEmail = validate_email_address($rawEmail);
            if ($validEmail === null) {
                $email_err = "Veuillez entrer une adresse email valide.";
                $email = sanitize_input($rawEmail, 255);
            } else {
                $email = $validEmail;
                $sql = "SELECT id FROM users WHERE email = ? LIMIT 1";
                if ($stmt = $mysqli->prepare($sql)) {
                    $stmt->bind_param("s", $email);
                    if ($stmt->execute()) {
                        $stmt->store_result();
                        if ($stmt->num_rows === 1) {
                            $email_err = "Cet email est déjà utilisé.";
                        }
                    } else {
                        $general_err = "Une erreur est survenue lors de la vérification de l'email.";
                    }
                    $stmt->close();
                }
            }
        }

        // Valider la robustesse du mot de passe (Phase 4.3)
        $password = (string) ($_POST["password"] ?? "");
        $confirm_password = (string) ($_POST["confirm_password"] ?? "");

        if ($password === "") {
            $password_err = "Veuillez entrer un mot de passe.";
        } else {
            $strength = validate_password_strength($password);
            if (!$strength['valid']) {
                $password_err = $strength['error'];
            }
        }

        // Valider la confirmation du mot de passe
        if ($confirm_password === "") {
            $confirm_password_err = "Veuillez confirmer le mot de passe.";
        } elseif (empty($password_err) && ($password !== $confirm_password)) {
            $confirm_password_err = "Les mots de passe ne correspondent pas.";
        }

        // Insérer dans la base de données
        if (empty($general_err) && empty($username_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)) {
            $sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'client')";
            if ($stmt = $mysqli->prepare($sql)) {
                $param_password = hash_password($password);
                $stmt->bind_param("sss", $username, $email, $param_password);
                if ($stmt->execute()) {
                    $stmt->close();
                    redirect(BASE_URL . "login.php");
                } else {
                    $general_err = "Une erreur est survenue lors de la création du compte.";
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
        <h1>Inscription</h1>
    </div>
</section>

<section class="auth-form">
    <div class="container">
        <?php if (!empty($general_err)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($general_err); ?></div>
        <?php endif; ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="username">Nom d'utilisateur :</label>
                <input type="text" name="username" id="username" maxlength="100" class="<?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>" required>
                <span class="invalid-feedback"><?php echo htmlspecialchars($username_err); ?></span>
            </div>
            <div class="form-group">
                <label for="email">Email :</label>
                <input type="email" name="email" id="email" maxlength="255" class="<?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>" required>
                <span class="invalid-feedback"><?php echo htmlspecialchars($email_err); ?></span>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe (min. 8 caractères, lettres et chiffres) :</label>
                <input type="password" name="password" id="password" minlength="8" class="<?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="" required>
                <span class="invalid-feedback"><?php echo htmlspecialchars($password_err); ?></span>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe :</label>
                <input type="password" name="confirm_password" id="confirm_password" minlength="8" class="<?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" value="" required>
                <span class="invalid-feedback"><?php echo htmlspecialchars($confirm_password_err); ?></span>
            </div>
            <div class="form-group">
                <button type="submit" class="btn">S'inscrire</button>
            </div>
            <p>Vous avez déjà un compte ? <a href="<?php echo BASE_URL; ?>login.php">Connectez-vous ici</a>.</p>
        </form>
    </div>
</section>

<?php include __DIR__ . "/includes/footer.php"; ?>
