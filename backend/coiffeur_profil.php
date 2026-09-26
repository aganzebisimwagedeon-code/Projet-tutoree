<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();
require_role('coiffeur');

$coiffeur_id = (int) $_SESSION["user_id"];
$message = "";
$errors = [];

$sql = "SELECT u.username, u.email, c.id AS coiffeur_table_id, c.specialite, c.photo
        FROM users u
        LEFT JOIN coiffeurs c ON u.id = c.user_id
        WHERE u.id = ? LIMIT 1";
$coiffeur_info = null;
if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("i", $coiffeur_id);
    $stmt->execute();
    $coiffeur_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        $errors[] = "Jeton de sécurité invalide. Veuillez réessayer.";
    } else {
        $username = sanitize_input($_POST["username"] ?? "", 100);
        $email = validate_email_address($_POST["email"] ?? "");
        $specialite = sanitize_input($_POST["specialite"] ?? "", 255);
        $new_password = (string) ($_POST["new_password"] ?? "");

        if ($username === "" || $email === null) {
            $errors[] = "Un nom d'utilisateur et une adresse email valide sont obligatoires.";
        }

        if ($new_password !== "") {
            $strength = validate_password_strength($new_password);
            if (!$strength['valid']) {
                $errors[] = $strength['error'];
            }
        }

        if (empty($errors)) {
            $sql_user = "UPDATE users SET username = ?, email = ? WHERE id = ?";
            if ($stmt = $mysqli->prepare($sql_user)) {
                $stmt->bind_param("ssi", $username, $email, $coiffeur_id);
                $stmt->execute();
                $stmt->close();
            }

            if (!empty($coiffeur_info["coiffeur_table_id"])) {
                $sql_coiffeur = "UPDATE coiffeurs SET specialite = ? WHERE user_id = ?";
                if ($stmt = $mysqli->prepare($sql_coiffeur)) {
                    $stmt->bind_param("si", $specialite, $coiffeur_id);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                $sql_coiffeur = "INSERT INTO coiffeurs (user_id, specialite) VALUES (?, ?)";
                if ($stmt = $mysqli->prepare($sql_coiffeur)) {
                    $stmt->bind_param("is", $coiffeur_id, $specialite);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            if ($new_password !== "") {
                $hashed_password = hash_password($new_password);
                $sql_password = "UPDATE users SET password = ? WHERE id = ?";
                if ($stmt = $mysqli->prepare($sql_password)) {
                    $stmt->bind_param("si", $hashed_password, $coiffeur_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $_SESSION["username"] = $username;
            $_SESSION["email"] = $email;
            $message = "Profil mis à jour avec succès !";

            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("i", $coiffeur_id);
                $stmt->execute();
                $coiffeur_info = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
        }
    }
}

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Mon Profil</h1>
    </div>
</section>

<section class="coiffeur-profil">
    <div class="container">
        <a href="<?php echo BASE_URL; ?>backend/coiffeur_dashboard.php" class="btn-back">← Retour au tableau de bord</a>

        <h2>Gestion de mon profil</h2>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="post" class="profile-form">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="username">Nom d'utilisateur :</label>
                <input type="text" id="username" name="username" maxlength="100" value="<?php echo htmlspecialchars($coiffeur_info["username"] ?? ""); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email :</label>
                <input type="email" id="email" name="email" maxlength="255" value="<?php echo htmlspecialchars($coiffeur_info["email"] ?? ""); ?>" required>
            </div>

            <div class="form-group">
                <label for="specialite">Spécialité :</label>
                <textarea id="specialite" name="specialite" rows="3" maxlength="255" placeholder="Décrivez vos spécialités (ex: Coiffure Dame, Tresses, Maquillage)"><?php echo htmlspecialchars($coiffeur_info["specialite"] ?? ""); ?></textarea>
            </div>

            <div class="form-group">
                <label for="new_password">Nouveau mot de passe (min. 8 caractères, lettres et chiffres ; laisser vide pour ne pas changer) :</label>
                <input type="password" id="new_password" name="new_password" minlength="8" placeholder="Nouveau mot de passe">
            </div>

            <button type="submit" class="btn">Mettre à jour le profil</button>
        </form>
    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>
