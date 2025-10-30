<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/functions.php";

start_secure_session();

$token = $_GET['token'] ?? '';
$new_password = $confirm_password = "";
$password_err = $token_err = $success_msg = "";

// Vérifier le token
if (empty($token)) {
    $token_err = "Token invalide.";
} else {
    $sql = "SELECT id, reset_token_expiry FROM users WHERE reset_token = ?";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows == 0) {
            $token_err = "Token invalide ou expiré.";
        } else {
            $stmt->bind_result($user_id, $expiry);
            $stmt->fetch();
            
            // Vérifier l'expiration
            if (strtotime($expiry) < time()) {
                $token_err = "Le lien de réinitialisation a expiré.";
            }
        }
        $stmt->close();
    }
}

// Traiter le nouveau mot de passe
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($token_err)) {
    // Validation du nouveau mot de passe
    if (empty(trim($_POST["new_password"]))) {
        $password_err = "Veuillez entrer un nouveau mot de passe.";
    } elseif (strlen(trim($_POST["new_password"])) < 8) {
        $password_err = "Le mot de passe doit contenir au moins 8 caractères.";
    } else {
        $new_password = trim($_POST["new_password"]);
    }
    
    // Validation de la confirmation
    if (empty(trim($_POST["confirm_password"]))) {
        $password_err = "Veuillez confirmer votre mot de passe.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($new_password != $confirm_password)) {
            $password_err = "Les mots de passe ne correspondent pas.";
        }
    }
    
    // Mettre à jour le mot de passe si aucune erreur
    if (empty($password_err) && empty($token_err)) {
        $hashed_password = hash_password($new_password);
        $update_sql = "UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ?";
        if ($update_stmt = $mysqli->prepare($update_sql)) {
            $update_stmt->bind_param("ss", $hashed_password, $token);
            if ($update_stmt->execute()) {
                $success_msg = "Votre mot de passe a été réinitialisé avec succès !";
                // Redirection après 3 secondes
                header("refresh:3;url=" . BASE_URL . "login.php?reset=success");
            } else {
                $token_err = "Erreur lors de la mise à jour. Veuillez réessayer.";
            }
            $update_stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialiser le mot de passe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            margin-top: 50px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center">
                        <h3>Réinitialiser votre mot de passe</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($token_err)): ?>
                            <div class="alert alert-danger"><?php echo $token_err; ?></div>
                        <?php endif; ?>
                        <?php if (!empty($success_msg)): ?>
                            <div class="alert alert-success"><?php echo $success_msg; ?></div>
                            <p>Vous serez redirigé vers la page de connexion dans quelques instants...</p>
                        <?php else: ?>
                            <form method="post">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                    <input type="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" 
                                           id="new_password" name="new_password" required>
                                    <div class="invalid-feedback"><?php echo $password_err; ?></div>
                                    <div class="form-text">Minimum 8 caractères.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                                    <input type="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" 
                                           id="confirm_password" name="confirm_password" required>
                                    <div class="invalid-feedback"><?php echo $password_err; ?></div>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Réinitialiser le mot de passe</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
