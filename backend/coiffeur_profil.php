<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();

// Vérifier si l'utilisateur est connecté et a le rôle coiffeur
if (!is_logged_in() || !has_role('coiffeur')) {
    redirect(BASE_URL . "login.php");
}

$coiffeur_id = $_SESSION["user_id"];
$message = "";

// Récupérer les informations du coiffeur
$sql = "SELECT u.username, u.email, c.specialite, c.photo 
        FROM users u 
        LEFT JOIN coiffeurs c ON u.id = c.user_id 
        WHERE u.id = ?";
$coiffeur_info = null;
if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("i", $coiffeur_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $coiffeur_info = $result->fetch_assoc();
    $stmt->close();
}

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitize_input($_POST["username"]);
    $email = sanitize_input($_POST["email"]);
    $specialite = sanitize_input($_POST["specialite"]);
    $new_password = $_POST["new_password"];
    
    // Mise à jour des informations utilisateur
    $sql_user = "UPDATE users SET username = ?, email = ? WHERE id = ?";
    if ($stmt = $mysqli->prepare($sql_user)) {
        $stmt->bind_param("ssi", $username, $email, $coiffeur_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Mise à jour ou insertion des informations coiffeur
    $sql_check_coiffeur = "SELECT id FROM coiffeurs WHERE user_id = ?";
    $coiffeur_exists = false;
    if ($stmt = $mysqli->prepare($sql_check_coiffeur)) {
        $stmt->bind_param("i", $coiffeur_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->fetch_assoc()) {
            $coiffeur_exists = true;
        }
        $stmt->close();
    }
    
    if ($coiffeur_exists) {
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
    
    // Mise à jour du mot de passe si fourni
    if (!empty($new_password)) {
        $hashed_password = hash_password($new_password);
        $sql_password = "UPDATE users SET password = ? WHERE id = ?";
        if ($stmt = $mysqli->prepare($sql_password)) {
            $stmt->bind_param("si", $hashed_password, $coiffeur_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    $message = "Profil mis à jour avec succès !";
    
    // Recharger les informations
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("i", $coiffeur_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $coiffeur_info = $result->fetch_assoc();
        $stmt->close();
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
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <form method="post" class="profile-form">
            <div class="form-group">
                <label for="username">Nom d'utilisateur:</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($coiffeur_info["username"] ?? ""); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($coiffeur_info["email"] ?? ""); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="specialite">Spécialité:</label>
                <textarea name="specialite" rows="3" placeholder="Décrivez vos spécialités (ex: Coiffure Dame, Tresses, Maquillage)"><?php echo htmlspecialchars($coiffeur_info["specialite"] ?? ""); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="new_password">Nouveau mot de passe (laisser vide pour ne pas changer):</label>
                <input type="password" name="new_password" placeholder="Nouveau mot de passe">
            </div>
            
            <button type="submit" class="btn-primary">Mettre à jour le profil</button>
        </form>
    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>

