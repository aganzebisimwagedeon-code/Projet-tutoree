<?php
// Activation complète du rapport d'erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

// Début du buffer de sortie pour capturer les erreurs
ob_start();

start_secure_session();

$errors = [];
$success = "";
$avis = [];

// Vérification de la connexion à la base de données
if ($mysqli->connect_error) {
    die("Erreur de connexion à la base de données: " . $mysqli->connect_error);
}

// Récupérer les avis approuvés
$sql_avis = "SELECT a.note, a.commentaire, a.created_at, 
                    u.username AS client_nom, 
                    co.specialite AS coiffeur_specialite
             FROM avis a
             JOIN users u ON a.client_id = u.id
             LEFT JOIN coiffeurs co ON a.coiffeur_id = co.id
             WHERE a.statut = 'approved'
             ORDER BY a.created_at DESC";

if ($result_avis = $mysqli->query($sql_avis)) {
    if ($result_avis->num_rows > 0) {
        while ($row = $result_avis->fetch_assoc()) {
            $avis[] = $row;
        }
    }
    $result_avis->free();
} else {
    $errors[] = "Erreur SQL: " . $mysqli->error;
    error_log("Erreur SQL dans avis.php: " . $mysqli->error);
}

// Gérer la soumission d'un avis
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit_avis"])) {
    if (!is_logged_in()) {
        $errors[] = "Vous devez être connecté pour laisser un avis.";
    } else {
        $client_id = $_SESSION["user_id"];
        $note = sanitize_input($_POST["note"]);
        $commentaire = sanitize_input($_POST["commentaire"]);
        $coiffeur_id = isset($_POST["coiffeur_id"]) && !empty($_POST["coiffeur_id"]) ? (int)$_POST["coiffeur_id"] : NULL;

        if (empty($note) || $note < 1 || $note > 5) {
            $errors[] = "La note doit être comprise entre 1 et 5.";
        }
        if (empty($commentaire)) {
            $errors[] = "Le commentaire est obligatoire.";
        }

        if (empty($errors)) {
            // CORRECTION ICI : Gestion du NULL pour coiffeur_id
            $sql_insert_avis = "INSERT INTO avis (client_id, coiffeur_id, note, commentaire, statut) VALUES (?, ?, ?, ?, 'pending')";
            
            if ($stmt = $mysqli->prepare($sql_insert_avis)) {
                // Types conditionnels
                if ($coiffeur_id === NULL) {
                    $stmt->bind_param("iiss", $client_id, $note, $commentaire);
                } else {
                    $stmt->bind_param("iiis", $client_id, $coiffeur_id, $note, $commentaire);
                }
                
                if ($stmt->execute()) {
                    $success = "Votre avis a été soumis avec succès et est en attente de modération.";
                    $_POST = array(); // Réinitialisation
                } else {
                    $errors[] = "Erreur lors de la soumission de l'avis: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = "Erreur de préparation de la requête: " . $mysqli->error;
            }
        }
    }
}

// Récupérer la liste des coiffeurs pour le formulaire d'avis
$coiffeurs_for_avis = [];
$sql_coiffeurs_avis = "SELECT c.id, u.username 
                       FROM coiffeurs c
                       JOIN users u ON c.user_id = u.id";
                       
if ($result_coiffeurs_avis = $mysqli->query($sql_coiffeurs_avis)) {
    while ($row = $result_coiffeurs_avis->fetch_assoc()) {
        $coiffeurs_for_avis[] = $row;
    }
    $result_coiffeurs_avis->free();
} else {
    $errors[] = "Erreur lors de la récupération des coiffeurs: " . $mysqli->error;
}

// Inclusion du header
include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Avis Clients</h1>
    </div>
</section>

<section class="reviews">
    <div class="container">
        <p class="intro-text">Découvrez ce que nos clients pensent de nos services et laissez votre propre avis pour nous aider à nous améliorer !</p>

        <!-- Affichage des messages d'erreur/succès -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h3>Erreurs :</h3>
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <p><?php echo $success; ?></p>
            </div>
        <?php endif; ?>

        <!-- Formulaire d'avis -->
        <h2>Laisser un avis</h2>
        <?php if (is_logged_in()): ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="review-form">
                <div class="form-group">
                    <label for="note">Votre note (1-5) :</label>
                    <input type="number" id="note" name="note" min="1" max="5" value="<?php echo isset($_POST["note"]) ? htmlspecialchars($_POST["note"]) : ""; ?>" required>
                </div>
                <div class="form-group">
                    <label for="coiffeur_id">Coiffeur (optionnel) :</label>
                    <select name="coiffeur_id" id="coiffeur_id">
                        <option value="">-- Aucun coiffeur spécifique --</option>
                        <?php foreach ($coiffeurs_for_avis as $coiffeur): ?>
                            <option value="<?php echo $coiffeur["id"]; ?>" <?php echo (isset($_POST["coiffeur_id"]) && $_POST["coiffeur_id"] == $coiffeur["id"]) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($coiffeur["username"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="commentaire">Votre commentaire :</label>
                    <textarea id="commentaire" name="commentaire" rows="5" required><?php echo isset($_POST["commentaire"]) ? htmlspecialchars($_POST["commentaire"]) : ""; ?></textarea>
                </div>
                <button type="submit" name="submit_avis" class="btn">Soumettre l'avis</button>
            </form>
        <?php else: ?>
            <p>Veuillez vous <a href="<?php echo BASE_URL; ?>login.php">connecter</a> pour laisser un avis.</p>
        <?php endif; ?>

        <!-- Liste des avis -->
        <h2>Avis des clients</h2>
        <?php if (empty($avis)): ?>
            <div class="alert alert-info">
                <p>Aucun avis n'a encore été publié ou approuvé.</p>
            </div>
        <?php else: ?>
            <div class="reviews-list">
                <?php foreach ($avis as $review): ?>
                    <div class="review-item">
                        <p class="review-meta">
                            <strong><?php echo htmlspecialchars($review["client_nom"]); ?></strong>
                            <?php if (!empty($review["coiffeur_specialite"])): ?>
                                - Spécialité: <?php echo htmlspecialchars($review["coiffeur_specialite"]); ?>
                            <?php endif; ?>
                            <br>Posté le <?php echo date("d/m/Y à H:i", strtotime($review["created_at"])); ?>
                        </p>
                        <div class="stars">
                            <?php for ($i = 0; $i < $review["note"]; $i++): ?>
                                <i class="fas fa-star"></i>
                            <?php endfor; ?>
                            <?php for ($i = $review["note"]; $i < 5; $i++): ?>
                                <i class="far fa-star"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="review-comment"><?php echo nl2br(htmlspecialchars($review["commentaire"])); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php 
include __DIR__ . "/../includes/footer.php";

// Vider le buffer et afficher
ob_end_flush();