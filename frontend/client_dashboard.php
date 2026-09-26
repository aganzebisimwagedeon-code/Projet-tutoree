<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../services/BookingService.php";
require_once __DIR__ . "/../services/NotificationService.php";

start_secure_session();
require_login();

$user_id = (int) $_SESSION["user_id"];
$username = $_SESSION["username"] ?? "Client";
$appointments = [];
$errors = [];
$success = "";

// Gérer les actions POST (annulation, reprogrammation, marquage des notifications)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        $errors[] = "Jeton de sécurité invalide. Veuillez réessayer.";
    } elseif (isset($_POST["mark_notifications_read"])) {
        NotificationService::markAllRead($mysqli, $user_id);
        $success = "Notifications marquées comme lues.";
    } elseif (isset($_POST["cancel_appointment_id"])) {
        $appointment_id = validate_int($_POST["cancel_appointment_id"], 1);
        $motif = sanitize_input($_POST["motif_annulation"] ?? "", 500);

        if (!$appointment_id) {
            $errors[] = "Identifiant de rendez-vous invalide.";
        } else {
            $sql_check_owner = "SELECT client_id, date_heure, statut FROM rendezvous WHERE id = ? LIMIT 1";
            if ($stmt_check = $mysqli->prepare($sql_check_owner)) {
                $stmt_check->bind_param("i", $appointment_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                if ($result_check->num_rows === 1) {
                    $appointment_data = $result_check->fetch_assoc();
                    if ((int) $appointment_data["client_id"] === $user_id) {
                        if (!in_array($appointment_data["statut"], ['pending', 'confirmed'], true)) {
                            $errors[] = "Ce rendez-vous ne peut plus être annulé.";
                        } else {
                            $appointment_time = strtotime($appointment_data["date_heure"]);
                            $diff_hours = floor(($appointment_time - time()) / 3600);

                            $delaiMin = defined('CLIENT_CANCEL_POLICY_HOURS') ? CLIENT_CANCEL_POLICY_HOURS : 24;
                            $descPenalite = "";
                            if ($result_policy = $mysqli->query("SELECT delai_minimum_heures, penalite_active, description_penalite FROM politiques_annulation WHERE type_acteur = 'client' LIMIT 1")) {
                                if ($policy = $result_policy->fetch_assoc()) {
                                    $delaiMin = (int) $policy["delai_minimum_heures"];
                                    if (!empty($policy["penalite_active"])) {
                                        $descPenalite = (string) $policy["description_penalite"];
                                    }
                                }
                                $result_policy->free();
                            }

                            if ($diff_hours < $delaiMin) {
                                $errors[] = "Impossible d'annuler ce rendez-vous : le délai minimum de {$delaiMin} heures n'est pas respecté. " . $descPenalite;
                            } else {
                                $sql_cancel = "UPDATE rendezvous SET statut = 'cancelled_by_client', motif_annulation = ? WHERE id = ?";
                                if ($stmt_cancel = $mysqli->prepare($sql_cancel)) {
                                    $stmt_cancel->bind_param("si", $motif, $appointment_id);
                                    if ($stmt_cancel->execute()) {
                                        $stmt_cancel->close();
                                        NotificationService::notifyBookingStatusChanged($mysqli, $appointment_id, 'cancelled_by_client', $motif);
                                        set_flash('success', "Rendez-vous annulé avec succès.");
                                        redirect(BASE_URL . "frontend/client_dashboard.php");
                                    } else {
                                        $errors[] = "Erreur lors de l'annulation du rendez-vous.";
                                    }
                                    $stmt_cancel->close();
                                }
                            }
                        }
                    } else {
                        $errors[] = "Vous n'êtes pas autorisé à modifier ce rendez-vous.";
                    }
                } else {
                    $errors[] = "Rendez-vous introuvable.";
                }
                $stmt_check->close();
            }
        }
    } elseif (isset($_POST["reschedule_appointment_id"])) {
        $rdvId = validate_int($_POST["reschedule_appointment_id"], 1);
        $newDate = sanitize_input($_POST["new_date"] ?? "", 20);
        $newTime = sanitize_input($_POST["new_time"] ?? "", 10);

        if (!$rdvId || $newDate === "" || $newTime === "") {
            $errors[] = "Veuillez choisir une nouvelle date et une nouvelle heure pour reprogrammer.";
        } else {
            $res = BookingService::rescheduleBooking($mysqli, $rdvId, $user_id, $newDate, $newTime);
            if ($res['success']) {
                set_flash('success', "Votre rendez-vous a été reprogrammé avec succès et est en attente de confirmation.");
                redirect(BASE_URL . "frontend/client_dashboard.php");
            } else {
                $errors = array_merge($errors, $res['errors']);
            }
        }
    }
}

// Messages flash
foreach (get_flashes() as $flash) {
    if ($flash['type'] === 'success') {
        $success = $flash['message'];
    } else {
        $errors[] = $flash['message'];
    }
}

// Récupérer l'historique des rendez-vous du client
$sql_appointments = "SELECT r.id, r.date_heure, r.date_heure_fin,
                            COALESCE(r.duree_minutes_snapshot, s.duree_minutes, 30) AS duree_minutes,
                            COALESCE(r.prix_final, s.prix_depart) AS prix_final,
                            r.type_prestation, r.statut, r.motif_annulation,
                            s.nom AS service_nom, u.username AS coiffeur_nom,
                            a.id AS avis_id
                     FROM rendezvous r
                     JOIN services s ON r.service_id = s.id
                     JOIN coiffeurs c ON r.coiffeur_id = c.id
                     JOIN users u ON c.user_id = u.id
                     LEFT JOIN avis a ON a.rendezvous_id = r.id
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

// Récupérer les notifications du client (Phase 7)
$notifications = NotificationService::getUserNotifications($mysqli, $user_id, 8);

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Bienvenue, <?php echo htmlspecialchars($username); ?> !</h1>
    </div>
</section>

<section class="client-dashboard">
    <div class="container">
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

        <?php if (!empty($notifications)): ?>
            <div class="notifications-panel" style="background:#f8f9fa; border-left:4px solid #d4af37; padding:15px; margin-bottom:25px; border-radius:4px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0;">Mes Notifications</h3>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" style="margin:0;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="mark_notifications_read" value="1">
                        <button type="submit" class="btn btn-sm">Tout marquer comme lu</button>
                    </form>
                </div>
                <ul style="margin:10px 0 0; padding-left:20px;">
                    <?php foreach ($notifications as $notif): ?>
                        <li style="margin-bottom:6px; <?php echo empty($notif['lu']) ? 'font-weight:bold;' : 'color:#666;'; ?>">
                            <?php echo htmlspecialchars($notif['titre']); ?> — <?php echo htmlspecialchars($notif['message']); ?>
                            <small style="color:#888;">(<?php echo date('d/m/Y H:i', strtotime($notif['created_at'])); ?>)</small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h2>Mes Rendez-vous</h2>
            <a href="<?php echo BASE_URL; ?>frontend/rendezvous.php" class="btn">Nouveau rendez-vous</a>
        </div>

        <?php if (empty($appointments)): ?>
            <p>Vous n'avez pas encore de rendez-vous.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date &amp; Heure</th>
                            <th>Coiffeur</th>
                            <th>Prestation</th>
                            <th>Durée / Prix</th>
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
                                <td>
                                    <?php echo (int) $appointment["duree_minutes"]; ?> min /
                                    <?php echo number_format((float) $appointment["prix_final"], 2, ',', ' '); ?> $
                                </td>
                                <td><?php echo htmlspecialchars(ucfirst($appointment["type_prestation"])); ?></td>
                                <td>
                                    <span class="status-<?php echo strtolower($appointment["statut"]); ?>">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $appointment["statut"]))); ?>
                                    </span>
                                    <?php if (!empty($appointment["motif_annulation"])): ?>
                                        <br><small>Motif : <?php echo htmlspecialchars($appointment["motif_annulation"]); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($appointment["statut"] === "pending" || $appointment["statut"] === "confirmed"): ?>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?');" style="margin-bottom:6px;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="cancel_appointment_id" value="<?php echo (int) $appointment["id"]; ?>">
                                            <input type="text" name="motif_annulation" placeholder="Motif (optionnel)" maxlength="200" style="padding:4px; font-size:0.85rem; width:140px;">
                                            <button type="submit" class="btn btn-danger btn-sm">Annuler</button>
                                        </form>
                                        <details>
                                            <summary style="cursor:pointer; font-size:0.85rem; color:#0056b3;">Reprogrammer</summary>
                                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="margin-top:6px;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="reschedule_appointment_id" value="<?php echo (int) $appointment["id"]; ?>">
                                                <input type="date" name="new_date" min="<?php echo date('Y-m-d'); ?>" required style="padding:3px; font-size:0.8rem;">
                                                <input type="time" name="new_time" required style="padding:3px; font-size:0.8rem;">
                                                <button type="submit" class="btn btn-sm">Valider</button>
                                            </form>
                                        </details>
                                    <?php elseif ($appointment["statut"] === "completed" && empty($appointment["avis_id"])): ?>
                                        <a href="<?php echo BASE_URL; ?>frontend/avis.php?rendezvous_id=<?php echo (int) $appointment["id"]; ?>" class="btn btn-sm">Laisser un avis</a>
                                    <?php else: ?>
                                        -
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
