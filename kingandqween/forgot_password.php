<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/functions.php";

// Charger PHPMailer
require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Africa/Lubumbashi');
start_secure_session();

$email = "";
$email_err = $success_msg = $error_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["email"]))) {
        $email_err = "Veuillez entrer votre email.";
    } else {
        $email = sanitize_input($_POST["email"]);
    }

    if (empty($email_err)) {
        // Vérifier si l'email existe
        $sql = "SELECT id, email FROM users WHERE email = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("s", $email);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    // Générer un token sécurisé
                    $token = bin2hex(random_bytes(50));
                    $expiry = date("Y-m-d H:i:s", time() + 3600); // 1h expiration
                    
                    // Stocker le token dans la base
                    $update_sql = "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?";
                    if ($update_stmt = $mysqli->prepare($update_sql)) {
                        $update_stmt->bind_param("sss", $token, $expiry, $email);
                        if ($update_stmt->execute()) {
                            // Envoyer l'email avec PHPMailer
                            $reset_link = BASE_URL . "reset_password.php?token=$token";
                            
                            $mail = new PHPMailer(true);
                            
                            try {
                                // Configuration SMTP Mailtrap
                                $mail->isSMTP();
                                $mail->Host = 'smtp.gmail.com';
                                $mail->SMTPAuth = true;
                                $mail->Username = 'henryfayole417@gmail.com'; // Remplacer par vos credentials
                                $mail->Password = 'roqf qzcf xcze wyxa'; // Remplacer par vos credentials
                                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                $mail->Port = 587;
                                
                                // Expéditeur et destinataire
                                $mail->setFrom('henryfayole417@gmail.com', 'Kingandqueen');
                                $mail->addAddress($email);
                                
                                // Contenu de l'email
                                $mail->isHTML(true);
                                $mail->Subject = 'Réinitialisation de votre mot de passe';
                                
                                // Corps HTML du message
                                $mail->Body = '
                                <html>
                                <head>
                                    <style>
                                        body { font-family: Arial, sans-serif; }
                                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                        .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                                        .content { padding: 30px; background-color: #ffffff; }
                                        .button { display: inline-block; padding: 10px 20px; background-color: #6c757d; 
                                                 color: white !important; text-decoration: none; border-radius: 4px; }
                                        .footer { padding: 20px; text-align: center; color: #6c757d; font-size: 12px; }
                                    </style>
                                </head>
                                <body>
                                    <div class="container">
                                        <div class="header">
                                            <h2>Réinitialisation de votre mot de passe</h2>
                                        </div>
                                        <div class="content">
                                            <p>Bonjour,</p>
                                            <p>Vous avez demandé la réinitialisation de votre mot de passe pour votre compte sur notre salon de coiffure KING AND QUEEN.</p>
                                            <p>Cliquez sur le bouton ci-dessous pour réinitialiser votre mot de passe :</p>
                                            <p style="text-align: center; margin: 30px 0;">
                                                <a href="' . $reset_link . '" class="button">Réinitialiser mon mot de passe</a>
                                            </p>
                                            <p>Si le bouton ne fonctionne pas, vous pouvez copier-coller ce lien dans votre navigateur :</p>
                                            <p><small>' . $reset_link . '</small></p>
                                            <p>Ce lien est valable pendant 1 heure.</p>
                                            <p>Si vous n êtes pas à l origine de cette demande, veuillez ignorer cet email.</p>
                                        </div>
                                        <div class="footer">
                                            <p>© ' . date('Y') . ' Votre Salon de Coiffure. Tous droits réservés.</p>
                                        </div>
                                    </div>
                                </body>
                                </html>';
                                
                                // Version texte pour les clients email qui ne supportent pas HTML
                                $mail->AltBody = "Bonjour,\n\n"
                                    . "Vous avez demandé la réinitialisation de votre mot de passe.\n"
                                    . "Cliquez sur le lien suivant pour réinitialiser votre mot de passe :\n"
                                    . $reset_link . "\n\n"
                                    . "Ce lien est valable pendant 1 heure.\n\n"
                                    . "Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.\n";
                                
                                $mail->send();
                                $success_msg = "Un lien de réinitialisation a été envoyé à votre email.";
                            } catch (Exception $e) {
                                $error_msg = "Erreur lors de l'envoi de l'email. Veuillez réessayer plus tard.";
                                // Pour le débogage, vous pouvez décommenter la ligne suivante:
                                $error_msg .= " Erreur: " . $mail->ErrorInfo;
                            }
                        } else {
                            $error_msg = "Erreur lors de la mise à jour. Veuillez réessayer.";
                        }
                        $update_stmt->close();
                    }
                } else {
                    // Ne pas révéler si l'email existe ou pas
                    $success_msg = "Si l'email existe dans notre système, un lien de réinitialisation sera envoyé.";
                }
            } else {
                $error_msg = "Erreur lors de la vérification. Veuillez réessayer.";
            }
            $stmt->close();
        } else {
            $error_msg = "Erreur de base de données. Veuillez réessayer plus tard.";
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
    <title>Mot de passe oublié</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        body {
            background-color: #f8f9fa;
            background-image: url('<?php echo BASE_URL; ?>assets/images/background.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border: none;
        }
        .card-header {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 20px;
            text-align: center;
        }
        .card-body {
            padding: 30px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            border: none;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .form-control:focus {
            border-color: #6c757d;
            box-shadow: 0 0 0 0.25rem rgba(108, 117, 125, 0.25);
        }
        .invalid-feedback {
            display: block;
        }
        .logo {
            max-width: 150px;
            margin: 0 auto 20px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="KING AND QUEEN SALON DE COIFFURE" class="logo">
                        <h3>Mot de passe oublié</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($success_msg)): ?>
                            <div class="alert alert-success text-center"><?php echo $success_msg; ?></div>
                        <?php endif; ?>
                        <?php if (!empty($error_msg)): ?>
                            <div class="alert alert-danger text-center"><?php echo $error_msg; ?></div>
                        <?php endif; ?>
                        
                        <p class="text-center mb-4">Entrez votre adresse email pour recevoir un lien de réinitialisation de mot de passe.</p>
                        
                        <form method="post">
                            <div class="mb-4">
                                <label for="email" class="form-label">Adresse email</label>
                                <input type="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" 
                                       id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" 
                                       placeholder="votre@email.com" required>
                                <div class="invalid-feedback"><?php echo $email_err; ?></div>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-lg">Envoyer le lien de réinitialisation</button>
                            </div>
                            
                            <div class="text-center mt-4">
                                <p class="mb-0">
                                    <a href="<?php echo BASE_URL; ?>login.php" class="text-decoration-none">
                                        <i class="bi bi-arrow-left"></i> Retour à la connexion
                                    </a>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="text-center mt-4 text-white">
                    <p>© <?php echo date('Y'); ?> Votre Salon de Coiffure. Tous droits réservés.</p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
