<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();

// Rediriger si l'utilisateur n'est pas connecté
if (!is_logged_in()) {
    redirect(BASE_URL . "login.php?redirect=" . urlencode($_SERVER["REQUEST_URI"]));
}

$user_id = $_SESSION["user_id"];
$username = $_SESSION["username"];
$appointments = [];
$errors = [];
$success = "";

// Récupérer l'historique des rendez-vous du client
$sql_appointments = "SELECT r.id, r.date_heure, r.type_prestation, r.statut, s.nom AS service_nom, u.username AS coiffeur_nom 
                     FROM rendezvous r
                     JOIN services s ON r.service_id = s.id
                     JOIN coiffeurs c ON r.coiffeur_id = c.id
                     JOIN users u ON c.user_id = u.id
                     WHERE r.client_id = ?
                     ORDER BY r.date_heure DESC";

if ($stmt = $mysqli->prepare($sql_appointments)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    $stmt->close();
}

// Gérer l'annulation de rendez-vous
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["cancel_appointment_id"])) {
    $appointment_id = sanitize_input($_POST["cancel_appointment_id"]);

    // Vérifier si le rendez-vous appartient bien à l'utilisateur connecté
    $sql_check_owner = "SELECT client_id, date_heure FROM rendezvous WHERE id = ?";
    if ($stmt_check = $mysqli->prepare($sql_check_owner)) {
        $stmt_check->bind_param("i", $appointment_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows == 1) {
            $appointment_data = $result_check->fetch_assoc();
            if ($appointment_data["client_id"] == $user_id) {
                // Vérifier la politique d'annulation
                $appointment_time = strtotime($appointment_data["date_heure"]);
                $current_time = time();
                $diff_hours = floor(($appointment_time - $current_time) / 3600);

                $sql_policy = "SELECT delai_minimum_heures, penalite_active, description_penalite FROM politiques_annulation WHERE type_acteur = 'client'";
                $result_policy = $mysqli->query($sql_policy);
                $policy = $result_policy->fetch_assoc();

                if ($diff_hours < $policy["delai_minimum_heures"]) {
                    $errors[] = "Impossible d'annuler ce rendez-vous. Le délai minimum d'annulation de " . $policy["delai_minimum_heures"] . " heures n'est pas respecté. " . ($policy["penalite_active"] ? $policy["description_penalite"] : "");
                } else {
                    $sql_cancel = "UPDATE rendezvous SET statut = 'cancelled_by_client' WHERE id = ?";
                    if ($stmt_cancel = $mysqli->prepare($sql_cancel)) {
                        $stmt_cancel->bind_param("i", $appointment_id);
                        if ($stmt_cancel->execute()) {
                            $success = "Rendez-vous annulé avec succès.";
                            // Rafraîchir la liste des rendez-vous
                            redirect(BASE_URL . "frontend/client_dashboard.php");
                        } else {
                            $errors[] = "Erreur lors de l'annulation du rendez-vous: " . $stmt_cancel->error;
                        }
                        $stmt_cancel->close();
                    }
                }
            } else {
                $errors[] = "Vous n'êtes pas autorisé à annuler ce rendez-vous.";
            }
        } else {
            $errors[] = "Rendez-vous introuvable.";
        }
        $stmt_check->close();
    }
}

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Bienvenue, <?php echo htmlspecialchars($username); ?> !</h1>
    </div>
</section>

<section class="client-dashboard">
    <div class="container">
        <h2>Mes Rendez-vous</h2>

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

        <?php if (empty($appointments)): ?>
            <p>Vous n'avez pas encore de rendez-vous.</p>
            <p><a href="<?php echo BASE_URL; ?>frontend/rendezvous.php" class="btn">Prendre un rendez-vous</a></p>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Heure</th>
                            <th>Coiffeur</th>
                            <th>Prestation</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td><?php echo date("d/m/Y H:i", strtotime($appointment["date_heure"])); ?></td>
                                <td><?php echo htmlspecialchars($appointment["coiffeur_nom"]); ?></td>
                                <td><?php echo htmlspecialchars($appointment["service_nom"]); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($appointment["type_prestation"])); ?></td>
                                <td><span class="status-<?php echo strtolower($appointment["statut"]); ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $appointment["statut"]))); ?></span></td>
                                <td>
                                    <?php if ($appointment["statut"] == "pending" || $appointment["statut"] == "confirmed"): ?>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?');">
                                            <input type="hidden" name="cancel_appointment_id" value="<?php echo $appointment["id"]; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Annuler</button>
                                        </form>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>

