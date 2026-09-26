<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();

$errors = [];
$success = "";
$avis = [];
$eligible_rdvs = [];

// Si l'utilisateur est connecté, récupérer ses rendez-vous terminés n'ayant pas encore d'avis (Phase 3.3)
if (is_logged_in()) {
    $client_id = (int) $_SESSION["user_id"];
    $sql_eligible = "SELECT r.id, r.date_heure, r.coiffeur_id, s.nom AS service_nom, u.username AS coiffeur_nom
                     FROM rendezvous r
                     JOIN services s ON r.service_id = s.id
                     JOIN coiffeurs c ON r.coiffeur_id = c.id
                     JOIN users u ON c.user_id = u.id
                     LEFT JOIN avis a ON a.rendezvous_id = r.id
                     WHERE r.client_id = ?
                       AND r.statut = 'completed'
                       AND a.id IS NULL
                     ORDER BY r.date_heure DESC";
    if ($stmtE = $mysqli->prepare($sql_eligible)) {
        $stmtE->bind_param("i", $client_id);
        if ($stmtE->execute()) {
            $resE = $stmtE->get_result();
            while ($rowE = $resE->fetch_assoc()) {
                $eligible_rdvs[$rowE['id']] = $rowE;
            }
        }
        $stmtE->close();
    }
}

// Gérer la soumission d'un avis (Phase 3.3 & Phase 4.1)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_avis"])) {
    if (!is_logged_in()) {
        $errors[] = "Vous devez être connecté pour laisser un avis.";
    } elseif (!verify_csrf_token()) {
        $errors[] = "Jeton de sécurité invalide. Veuillez réessayer.";
    } else {
        $client_id = (int) $_SESSION["user_id"];
        $rdv_id = validate_int($_POST["rendezvous_id"] ?? null, 1);
        $note = validate_int($_POST["note"] ?? null, 1, 5);
        $commentaire = sanitize_input($_POST["commentaire"] ?? "", 1500);

        if (!$rdv_id || !isset($eligible_rdvs[$rdv_id])) {
            $errors[] = "Veuillez sélectionner un rendez-vous terminé valide n'ayant pas encore fait l'objet d'un avis.";
        }
        if ($note === null) {
            $errors[] = "La note doit être comprise entre 1 et 5.";
        }
        if ($commentaire === "" || mb_strlen($commentaire) < 3) {
            $errors[] = "Veuillez saisir un commentaire d'au moins 3 caractères.";
        }

        if (empty($errors)) {
            $coiffeur_id = (int) $eligible_rdvs[$rdv_id]['coiffeur_id'];
            $sql_insert_avis = "INSERT INTO avis (client_id, coiffeur_id, rendezvous_id, note, commentaire, statut) VALUES (?, ?, ?, ?, ?, 'pending')";
            if ($stmt = $mysqli->prepare($sql_insert_avis)) {
                $stmt->bind_param("iiiis", $client_id, $coiffeur_id, $rdv_id, $note, $commentaire);
                if ($stmt->execute()) {
                    $success = "Votre avis a été soumis avec succès et sera publié après modération.";
                    unset($eligible_rdvs[$rdv_id]);
                    $_POST = [];
                } else {
                    $errors[] = "Erreur lors de l'enregistrement de votre avis.";
                }
                $stmt->close();
            } else {
                $errors[] = "Erreur interne lors de la préparation de l'avis.";
            }
        }
    }
}

// Récupérer les avis approuvés
$sql_avis = "SELECT a.note, a.commentaire, a.created_at,
                    u.username AS client_nom,
                    uco.username AS coiffeur_nom,
                    co.specialite AS coiffeur_specialite
             FROM avis a
             JOIN users u ON a.client_id = u.id
             LEFT JOIN coiffeurs co ON a.coiffeur_id = co.id
             LEFT JOIN users uco ON co.user_id = uco.id
             WHERE a.statut = 'approved'
             ORDER BY a.created_at DESC";

if ($result_avis = $mysqli->query($sql_avis)) {
    while ($row = $result_avis->fetch_assoc()) {
        $avis[] = $row;
    }
    $result_avis->free();
}

$preselected_rdv_id = validate_int($_GET['rendezvous_id'] ?? ($_POST['rendezvous_id'] ?? null), 1);

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Avis Clients</h1>
    </div>
</section>

<section class="reviews">
    <div class="container">
        <p class="intro-text">Découvrez ce que nos clients pensent de nos prestations et partagez votre expérience après votre rendez-vous !</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <p><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>

        <h2>Laisser un avis</h2>
        <?php if (!is_logged_in()): ?>
            <p>Veuillez vous <a href="<?php echo BASE_URL; ?>login.php">connecter</a> pour laisser un avis sur une prestation terminée.</p>
        <?php elseif (empty($eligible_rdvs)): ?>
            <div class="alert alert-info">
                <p>Seuls les clients ayant effectué un rendez-vous terminé peuvent publier un avis (un avis par rendez-vous).</p>
            </div>
        <?php else: ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="review-form">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label for="rendezvous_id">Rendez-vous concerné :</label>
                    <select name="rendezvous_id" id="rendezvous_id" required>
                        <option value="">-- Sélectionnez votre prestation terminée --</option>
                        <?php foreach ($eligible_rdvs as $rdv): ?>
                            <option value="<?php echo (int) $rdv['id']; ?>" <?php echo ($preselected_rdv_id === (int) $rdv['id']) ? 'selected' : ''; ?>>
                                <?php echo date('d/m/Y', strtotime($rdv['date_heure'])); ?> –
                                <?php echo htmlspecialchars($rdv['service_nom']); ?> (avec <?php echo htmlspecialchars($rdv['coiffeur_nom']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="note">Votre note (1 à 5) :</label>
                    <input type="number" id="note" name="note" min="1" max="5" value="<?php echo isset($_POST["note"]) ? htmlspecialchars((string) $_POST["note"]) : "5"; ?>" required>
                </div>
                <div class="form-group">
                    <label for="commentaire">Votre commentaire :</label>
                    <textarea id="commentaire" name="commentaire" rows="4" maxlength="1500" required><?php echo isset($_POST["commentaire"]) ? htmlspecialchars((string) $_POST["commentaire"]) : ""; ?></textarea>
                </div>
                <button type="submit" name="submit_avis" class="btn">Soumettre l'avis</button>
            </form>
        <?php endif; ?>

        <h2>Avis des clients</h2>
        <?php if (empty($avis)): ?>
            <div class="alert alert-info">
                <p>Aucun avis n'a encore été publié.</p>
            </div>
        <?php else: ?>
            <div class="reviews-list">
                <?php foreach ($avis as $review): ?>
                    <div class="review-item">
                        <p class="review-meta">
                            <strong><?php echo htmlspecialchars($review["client_nom"]); ?></strong>
                            <?php if (!empty($review["coiffeur_nom"])): ?>
                                — Coiffeur : <?php echo htmlspecialchars($review["coiffeur_nom"]); ?>
                            <?php endif; ?>
                            <br>Posté le <?php echo date("d/m/Y à H:i", strtotime($review["created_at"])); ?>
                        </p>
                        <div class="stars">
                            <?php for ($i = 0; $i < (int) $review["note"]; $i++): ?>
                                <i class="fas fa-star"></i>
                            <?php endfor; ?>
                            <?php for ($i = (int) $review["note"]; $i < 5; $i++): ?>
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

<?php include __DIR__ . "/../includes/footer.php"; ?>
