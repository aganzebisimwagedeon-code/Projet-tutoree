<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../services/NotificationService.php";

start_secure_session();
require_role("admin");

$rendezvous = [];
$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    if (!verify_csrf_token()) {
        $errors[] = "Jeton de sécurité invalide. Veuillez réessayer.";
    } else {
        $appointment_id = validate_int($_POST["appointment_id"] ?? null, 1);
        $action = sanitize_input($_POST["action"], 20);
        $motif = sanitize_input($_POST["motif_annulation"] ?? "", 500);

        if (!$appointment_id || !in_array($action, ["confirm", "cancel", "complete"], true)) {
            $errors[] = "Action ou identifiant de rendez-vous invalide.";
        } else {
            $sql_get_rdv = "SELECT date_heure, client_id, coiffeur_id FROM rendezvous WHERE id = ? LIMIT 1";
            if ($stmt_get_rdv = $mysqli->prepare($sql_get_rdv)) {
                $stmt_get_rdv->bind_param("i", $appointment_id);
                $stmt_get_rdv->execute();
                $result_get_rdv = $stmt_get_rdv->get_result();

                if ($result_get_rdv->num_rows > 0) {
                    $rdv_data = $result_get_rdv->fetch_assoc();
                    $appointment_time = strtotime($rdv_data["date_heure"]);

                    if ($appointment_time === false) {
                        $errors[] = "Format de date invalide pour le rendez-vous.";
                    } elseif ($action === "cancel") {
                        $diff_hours = floor(($appointment_time - time()) / 3600);
                        $delaiMin = defined('COIFFEUR_CANCEL_POLICY_HOURS') ? COIFFEUR_CANCEL_POLICY_HOURS : 12;

                        if ($result_policy = $mysqli->query("SELECT delai_minimum_heures FROM politiques_annulation WHERE type_acteur = 'coiffeur' LIMIT 1")) {
                            if ($policy = $result_policy->fetch_assoc()) {
                                $delaiMin = (int) $policy["delai_minimum_heures"];
                            }
                            $result_policy->free();
                        }

                        if ($diff_hours < $delaiMin && $diff_hours > -24) {
                            $errors[] = "Impossible d'annuler ce rendez-vous : le délai minimum d'annulation de {$delaiMin} heures n'est pas respecté.";
                        } else {
                            $new_status = "cancelled_by_coiffeur";
                            $sql_update = "UPDATE rendezvous SET statut = ?, motif_annulation = ? WHERE id = ?";
                            if ($stmt = $mysqli->prepare($sql_update)) {
                                $stmt->bind_param("ssi", $new_status, $motif, $appointment_id);
                                if ($stmt->execute()) {
                                    $success = "Rendez-vous annulé avec succès.";
                                    NotificationService::notifyBookingStatusChanged($mysqli, $appointment_id, $new_status, $motif);
                                } else {
                                    $errors[] = "Erreur lors de l'annulation.";
                                }
                                $stmt->close();
                            }
                        }
                    } elseif ($action === "confirm") {
                        $new_status = "confirmed";
                        $sql_update = "UPDATE rendezvous SET statut = ? WHERE id = ?";
                        if ($stmt = $mysqli->prepare($sql_update)) {
                            $stmt->bind_param("si", $new_status, $appointment_id);
                            if ($stmt->execute()) {
                                $success = "Rendez-vous confirmé avec succès.";
                                NotificationService::notifyBookingStatusChanged($mysqli, $appointment_id, $new_status);
                            } else {
                                $errors[] = "Erreur lors de la confirmation.";
                            }
                            $stmt->close();
                        }
                    } elseif ($action === "complete") {
                        $new_status = "completed";
                        $sql_update = "UPDATE rendezvous SET statut = ? WHERE id = ?";
                        if ($stmt = $mysqli->prepare($sql_update)) {
                            $stmt->bind_param("si", $new_status, $appointment_id);
                            if ($stmt->execute()) {
                                $success = "Rendez-vous marqué comme terminé.";
                                NotificationService::notifyBookingStatusChanged($mysqli, $appointment_id, $new_status);
                            } else {
                                $errors[] = "Erreur lors de la mise à jour.";
                            }
                            $stmt->close();
                        }
                    }
                } else {
                    $errors[] = "Rendez-vous introuvable.";
                }
                $stmt_get_rdv->close();
            }
        }
    }
}

$sql_rendezvous = "SELECT r.id, r.date_heure,
                          COALESCE(r.duree_minutes_snapshot, s.duree_minutes, 30) AS duree_minutes,
                          COALESCE(r.prix_final, s.prix_depart) AS prix_final,
                          r.type_prestation, r.statut, r.adresse_domicile, r.telephone, r.motif_annulation,
                          uc.username AS client_nom,
                          u.username AS coiffeur_nom,
                          s.nom AS service_nom
                   FROM rendezvous r
                   JOIN users uc ON r.client_id = uc.id
                   JOIN coiffeurs co ON r.coiffeur_id = co.id
                   JOIN users u ON co.user_id = u.id
                   JOIN services s ON r.service_id = s.id
                   ORDER BY r.date_heure DESC";

if ($result = $mysqli->query($sql_rendezvous)) {
    while ($row = $result->fetch_assoc()) {
        $rendezvous[] = $row;
    }
    $result->free();
} else {
    $errors[] = "Erreur lors de la récupération des rendez-vous.";
}

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Gérer les Rendez-vous</h1>
    </div>
</section>

<section class="admin-content">
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

        <h2>Liste des Rendez-vous</h2>
        <?php if (empty($rendezvous)): ?>
            <div class="alert alert-info">
                <p>Aucun rendez-vous trouvé.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date &amp; Heure</th>
                            <th>Client</th>
                            <th>Coiffeur</th>
                            <th>Service</th>
                            <th>Durée / Prix</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th>Infos domicile</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rendezvous as $rdv): ?>
                            <tr>
                                <td><?php echo date("d/m/Y H:i", strtotime($rdv["date_heure"])); ?></td>
                                <td><?php echo htmlspecialchars($rdv["client_nom"]); ?></td>
                                <td><?php echo htmlspecialchars($rdv["coiffeur_nom"]); ?></td>
                                <td><?php echo htmlspecialchars($rdv["service_nom"]); ?></td>
                                <td><?php echo (int) $rdv["duree_minutes"]; ?> min / <?php echo number_format((float) $rdv["prix_final"], 2, ',', ' '); ?> $</td>
                                <td><?php echo htmlspecialchars(ucfirst($rdv["type_prestation"])); ?></td>
                                <td>
                                    <span class="status-<?php echo strtolower($rdv["statut"]); ?>">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $rdv["statut"]))); ?>
                                    </span>
                                    <?php if (!empty($rdv["motif_annulation"])): ?>
                                        <br><small>Motif : <?php echo htmlspecialchars($rdv["motif_annulation"]); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($rdv["type_prestation"] === 'domicile'): ?>
                                        <button type="button" class="btn-details btn btn-sm btn-info"
                                                data-adresse="<?php echo htmlspecialchars($rdv['adresse_domicile'] ?? ''); ?>"
                                                data-telephone="<?php echo htmlspecialchars($rdv['telephone'] ?? ''); ?>">
                                            Voir détails
                                        </button>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($rdv["statut"] === "pending"): ?>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline" style="display:inline-block;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="appointment_id" value="<?php echo (int) $rdv["id"]; ?>">
                                            <input type="hidden" name="action" value="confirm">
                                            <button type="submit" class="btn btn-success btn-sm">Confirmer</button>
                                        </form>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline" style="display:inline-block;" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="appointment_id" value="<?php echo (int) $rdv["id"]; ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <button type="submit" class="btn btn-danger btn-sm">Annuler</button>
                                        </form>
                                    <?php elseif ($rdv["statut"] === "confirmed"): ?>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline" style="display:inline-block;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="appointment_id" value="<?php echo (int) $rdv["id"]; ?>">
                                            <input type="hidden" name="action" value="complete">
                                            <button type="submit" class="btn btn-sm">Terminer</button>
                                        </form>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline" style="display:inline-block;" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="appointment_id" value="<?php echo (int) $rdv["id"]; ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <button type="submit" class="btn btn-danger btn-sm">Annuler</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">Aucune action</span>
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

<div id="detailsModal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h3>Détails du domicile</h3>
        <p><strong>Adresse :</strong> <span id="modal-adresse"></span></p>
        <p><strong>Téléphone :</strong> <span id="modal-telephone"></span></p>
    </div>
</div>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1050;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}
.modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 25px;
    border-radius: 8px;
    max-width: 600px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    position: relative;
}
.close-modal {
    color: #aaa;
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}
.close-modal:hover {
    color: #000;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('detailsModal');
    const closeBtn = document.querySelector('.close-modal');
    document.querySelectorAll('.btn-details').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById('modal-adresse').textContent = button.dataset.adresse;
            document.getElementById('modal-telephone').textContent = button.dataset.telephone;
            modal.style.display = 'block';
        });
    });
    if (closeBtn) {
        closeBtn.addEventListener('click', () => { modal.style.display = 'none'; });
    }
    window.addEventListener('click', (event) => {
        if (event.target === modal) { modal.style.display = 'none'; }
    });
});
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>
