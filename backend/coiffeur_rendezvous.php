<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../services/NotificationService.php";

start_secure_session();
require_role('coiffeur');

$coiffeur_id = (int) $_SESSION["user_id"];
$errors = [];
$success = "";

// Récupérer l'ID du profil coiffeur
$sql_coiffeur = "SELECT id FROM coiffeurs WHERE user_id = ? LIMIT 1";
$stmt_profile = $mysqli->prepare($sql_coiffeur);
$stmt_profile->bind_param("i", $coiffeur_id);
$stmt_profile->execute();
$result_profile = $stmt_profile->get_result();
$coiffeur_profile = $result_profile->fetch_assoc();
$stmt_profile->close();

if (!$coiffeur_profile) {
    redirect(BASE_URL . "backend/coiffeur_profil.php");
}

$coiffeur_profile_id = (int) $coiffeur_profile['id'];

// Traitement des actions POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    if (!verify_csrf_token()) {
        $errors[] = "Jeton de sécurité invalide. Veuillez réessayer.";
    } else {
        $action = sanitize_input($_POST["action"], 20);
        $rdv_id = validate_int($_POST["rdv_id"] ?? null, 1);
        $motif = sanitize_input($_POST["motif_annulation"] ?? "", 500);

        if (!$rdv_id || !in_array($action, ["confirm", "cancel", "complete"], true)) {
            $errors[] = "Requête invalide.";
        } else {
            // Vérifier que le rendez-vous appartient bien à ce coiffeur
            $sqlCheck = "SELECT id, date_heure, statut FROM rendezvous WHERE id = ? AND coiffeur_id = ? LIMIT 1";
            $rdvRow = null;
            if ($stmtC = $mysqli->prepare($sqlCheck)) {
                $stmtC->bind_param("ii", $rdv_id, $coiffeur_profile_id);
                $stmtC->execute();
                $rdvRow = $stmtC->get_result()->fetch_assoc();
                $stmtC->close();
            }

            if (!$rdvRow) {
                $errors[] = "Rendez-vous introuvable.";
            } elseif ($action === "confirm") {
                $sql = "UPDATE rendezvous SET statut = 'confirmed' WHERE id = ? AND coiffeur_id = ?";
                if ($stmt = $mysqli->prepare($sql)) {
                    $stmt->bind_param("ii", $rdv_id, $coiffeur_profile_id);
                    if ($stmt->execute()) {
                        NotificationService::notifyBookingStatusChanged($mysqli, $rdv_id, 'confirmed');
                        set_flash('success', "Rendez-vous confirmé avec succès.");
                        redirect(BASE_URL . "backend/coiffeur_rendezvous.php");
                    }
                    $stmt->close();
                }
            } elseif ($action === "cancel") {
                $diff_hours = floor((strtotime($rdvRow['date_heure']) - time()) / 3600);
                $delaiMin = defined('COIFFEUR_CANCEL_POLICY_HOURS') ? COIFFEUR_CANCEL_POLICY_HOURS : 12;
                if ($resP = $mysqli->query("SELECT delai_minimum_heures FROM politiques_annulation WHERE type_acteur = 'coiffeur' LIMIT 1")) {
                    if ($pRow = $resP->fetch_assoc()) {
                        $delaiMin = (int) $pRow['delai_minimum_heures'];
                    }
                    $resP->free();
                }

                if ($diff_hours < $delaiMin && $diff_hours > -24) {
                    $errors[] = "Impossible d'annuler ce rendez-vous moins de {$delaiMin} heures avant l'horaire prévu.";
                } else {
                    $sql = "UPDATE rendezvous SET statut = 'cancelled_by_coiffeur', motif_annulation = ? WHERE id = ? AND coiffeur_id = ?";
                    if ($stmt = $mysqli->prepare($sql)) {
                        $stmt->bind_param("sii", $motif, $rdv_id, $coiffeur_profile_id);
                        if ($stmt->execute()) {
                            NotificationService::notifyBookingStatusChanged($mysqli, $rdv_id, 'cancelled_by_coiffeur', $motif);
                            set_flash('success', "Rendez-vous annulé.");
                            redirect(BASE_URL . "backend/coiffeur_rendezvous.php");
                        }
                        $stmt->close();
                    }
                }
            } elseif ($action === "complete") {
                $sql = "UPDATE rendezvous SET statut = 'completed' WHERE id = ? AND coiffeur_id = ?";
                if ($stmt = $mysqli->prepare($sql)) {
                    $stmt->bind_param("ii", $rdv_id, $coiffeur_profile_id);
                    if ($stmt->execute()) {
                        NotificationService::notifyBookingStatusChanged($mysqli, $rdv_id, 'completed');
                        set_flash('success', "Prestation marquée comme terminée.");
                        redirect(BASE_URL . "backend/coiffeur_rendezvous.php");
                    }
                    $stmt->close();
                }
            }
        }
    }
}

foreach (get_flashes() as $flash) {
    if ($flash['type'] === 'success') {
        $success = $flash['message'];
    } else {
        $errors[] = $flash['message'];
    }
}

$sql = "SELECT r.id, r.date_heure, r.type_prestation, r.statut, r.adresse_domicile, r.telephone,
               COALESCE(r.duree_minutes_snapshot, s.duree_minutes, 30) AS duree_minutes,
               COALESCE(r.prix_final, s.prix_depart) AS prix_depart,
               u.username AS client_nom, s.nom AS service_nom
        FROM rendezvous r
        JOIN users u ON r.client_id = u.id
        JOIN services s ON r.service_id = s.id
        WHERE r.coiffeur_id = ?
        ORDER BY r.date_heure DESC";

$rendezvous = [];
if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("i", $coiffeur_profile_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rendezvous[] = $row;
    }
    $stmt->close();
}

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Mes Rendez-vous</h1>
    </div>
</section>

<section class="coiffeur-rendezvous">
    <div class="container">
        <a href="<?php echo BASE_URL; ?>backend/coiffeur_dashboard.php" class="btn-back">← Retour au tableau de bord</a>

        <h2>Gestion de mes rendez-vous</h2>

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

        <?php if (empty($rendezvous)): ?>
            <p>Aucun rendez-vous trouvé.</p>
        <?php else: ?>
            <div class="modern-table-container">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Date &amp; Heure</th>
                            <th>Client</th>
                            <th>Service</th>
                            <th>Durée / Prix</th>
                            <th>Type</th>
                            <th>Infos domicile</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rendezvous as $rdv): ?>
                            <tr>
                                <td data-label="Date & Heure"><?php echo date("d/m/Y H:i", strtotime($rdv["date_heure"])); ?></td>
                                <td data-label="Client"><?php echo htmlspecialchars($rdv["client_nom"]); ?></td>
                                <td data-label="Service"><?php echo htmlspecialchars($rdv["service_nom"]); ?></td>
                                <td data-label="Durée / Prix"><?php echo (int) $rdv["duree_minutes"]; ?> min / <?php echo number_format((float) $rdv["prix_depart"], 2, ',', ' '); ?> $</td>
                                <td data-label="Type"><?php echo htmlspecialchars(ucfirst($rdv["type_prestation"])); ?></td>
                                <td data-label="Infos domicile">
                                    <?php if ($rdv["type_prestation"] === 'domicile'): ?>
                                        <button type="button" class="btn-details"
                                                data-adresse="<?php echo htmlspecialchars($rdv['adresse_domicile'] ?? ''); ?>"
                                                data-telephone="<?php echo htmlspecialchars($rdv['telephone'] ?? ''); ?>">
                                            Voir détails
                                        </button>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td data-label="Statut">
                                    <span class="status-badge status-<?php echo htmlspecialchars($rdv["statut"]); ?>">
                                        <?php
                                        switch ($rdv["statut"]) {
                                            case 'pending': echo 'En attente'; break;
                                            case 'confirmed': echo 'Confirmé'; break;
                                            case 'cancelled_by_client': echo 'Annulé par client'; break;
                                            case 'cancelled_by_coiffeur': echo 'Annulé par moi'; break;
                                            case 'completed': echo 'Terminé'; break;
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td data-label="Actions" class="actions-cell">
                                    <?php if ($rdv["statut"] === "pending"): ?>
                                        <div class="action-buttons">
                                            <form method="post">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="rdv_id" value="<?php echo (int) $rdv["id"]; ?>">
                                                <input type="hidden" name="action" value="confirm">
                                                <button type="submit" class="btn-action btn-confirm" title="Confirmer">✓</button>
                                            </form>
                                            <form method="post" onsubmit="return confirm('Confirmer l\'annulation de ce rendez-vous ?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="rdv_id" value="<?php echo (int) $rdv["id"]; ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <button type="submit" class="btn-action btn-cancel" title="Annuler">✕</button>
                                            </form>
                                        </div>
                                    <?php elseif ($rdv["statut"] === "confirmed"): ?>
                                        <div class="action-buttons">
                                            <form method="post">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="rdv_id" value="<?php echo (int) $rdv["id"]; ?>">
                                                <input type="hidden" name="action" value="complete">
                                                <button type="submit" class="btn-action btn-complete">Terminer</button>
                                            </form>
                                            <form method="post" onsubmit="return confirm('Confirmer l\'annulation de ce rendez-vous ?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="rdv_id" value="<?php echo (int) $rdv["id"]; ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <button type="submit" class="btn-action btn-cancel" title="Annuler">✕</button>
                                            </form>
                                        </div>
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

<style>
.modern-table-container {
    overflow-x: auto;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    margin: 20px 0;
}
.modern-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}
.modern-table th {
    background-color: #f8f9fa;
    padding: 16px 15px;
    text-align: left;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #e9ecef;
}
.modern-table td {
    padding: 14px 15px;
    border-bottom: 1px solid #e9ecef;
    color: #495057;
}
.modern-table tr:hover {
    background-color: #f8f9fa;
}
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.85em;
    font-weight: 500;
}
.status-pending { background: #fff3cd; color: #856404; }
.status-confirmed { background: #d4edda; color: #155724; }
.status-cancelled_by_client,
.status-cancelled_by_coiffeur { background: #f8d7da; color: #721c24; }
.status-completed { background: #cce5ff; color: #004085; }
.actions-cell { white-space: nowrap; }
.action-buttons { display: flex; gap: 8px; }
.btn-action {
    border: none;
    border-radius: 4px;
    padding: 8px 12px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
}
.btn-confirm { background: #28a745; color: white; }
.btn-cancel { background: #dc3545; color: white; }
.btn-complete { background: #17a2b8; color: white; }
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}
.modal-content {
    background-color: #fff;
    margin: 15% auto;
    padding: 20px;
    border-radius: 8px;
    max-width: 500px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}
.close-modal {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}
.close-modal:hover { color: #000; }
</style>

<div id="detailsModal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h3>Détails du domicile</h3>
        <p><strong>Adresse :</strong> <span id="modal-adresse"></span></p>
        <p><strong>Téléphone :</strong> <span id="modal-telephone"></span></p>
    </div>
</div>

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
