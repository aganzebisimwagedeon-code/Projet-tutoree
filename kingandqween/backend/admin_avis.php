<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();

if (!is_logged_in() || !has_role("admin")) {
    redirect(BASE_URL . "backend/admin_login.php");
}

$avis = [];
$errors = [];
$success = "";

// Gérer la modération des avis
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["review_id"]) && isset($_POST["action"])) {
    $review_id = sanitize_input($_POST["review_id"]);
    $action = sanitize_input($_POST["action"]); // 'approve' ou 'reject'

    $new_status = ($action == "approve") ? "approved" : "rejected";

    $sql_update = "UPDATE avis SET statut = ? WHERE id = ?";
    if ($stmt = $mysqli->prepare($sql_update)) {
        $stmt->bind_param("si", $new_status, $review_id);
        if ($stmt->execute()) {
            $success = "Avis " . $new_status . " avec succès.";
        } else {
            $errors[] = "Erreur lors de la mise à jour de l\avis: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Récupérer tous les avis avec les noms des clients et coiffeurs
$sql_avis = "SELECT a.id, a.note, a.commentaire, a.statut, a.created_at, 
             u.username AS client_nom, 
             uc.username AS coiffeur_nom 
             FROM avis a
             JOIN users u ON a.client_id = u.id
             LEFT JOIN coiffeurs c ON a.coiffeur_id = c.id
             LEFT JOIN users uc ON c.user_id = uc.id
             ORDER BY a.created_at DESC";

if ($result = $mysqli->query($sql_avis)) {
    while ($row = $result->fetch_assoc()) {
        $avis[] = $row;
    }
    $result->free();
}

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Modération des Avis Clients</h1>
    </div>
</section>

<section class="admin-content">
    <div class="container">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
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

        <h2>Liste des Avis</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Coiffeur</th>
                        <th>Note</th>
                        <th>Commentaire</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($avis)): ?>
                        <tr>
                            <td colspan="7">Aucun avis trouvé.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($avis as $review): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($review["client_nom"]); ?></td>
                                <td><?php echo htmlspecialchars($review["coiffeur_nom"] ?: "N/A"); ?></td>
                                <td><?php echo htmlspecialchars($review["note"]); ?>/5</td>
                                <td><?php echo nl2br(htmlspecialchars($review["commentaire"])); ?></td>
                                <td><?php echo date("d/m/Y H:i", strtotime($review["created_at"])); ?></td>
                                <td><span class="status-<?php echo strtolower($review["statut"]); ?>"><?php echo htmlspecialchars(ucfirst($review["statut"])); ?></span></td>
                                <td>
                                    <?php if ($review["statut"] == "pending"): ?>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="display:inline-block;">
                                            <input type="hidden" name="review_id" value="<?php echo $review["id"]; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success btn-sm">Approuver</button>
                                        </form>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="display:inline-block;" onsubmit="return confirm(\'Êtes-vous sûr de vouloir rejeter cet avis ?\');">
                                            <input type="hidden" name="review_id" value="<?php echo $review["id"]; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-danger btn-sm">Rejeter</button>
                                        </form>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>

