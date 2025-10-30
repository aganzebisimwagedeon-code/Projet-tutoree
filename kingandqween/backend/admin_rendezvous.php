<?php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Définir la timezone
date_default_timezone_set('Europe/Paris'); // Adaptez à votre fuseau horaire

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

// Démarrer le buffer pour capturer les erreurs
ob_start();

start_secure_session();

// Redirection si non admin
if (!is_logged_in() || !has_role("admin")) {
    redirect(BASE_URL . "backend/admin_login.php");
    exit;
}

$rendezvous = [];
$errors = [];
$success = "";

// Gérer la modification du statut d'un rendez-vous
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"])) {
    $appointment_id = sanitize_input($_POST["appointment_id"]);
    $action = sanitize_input($_POST["action"]); // 'confirm' ou 'cancel'

    // Récupérer les détails du rendez-vous
    $sql_get_rdv = "SELECT date_heure, client_id, coiffeur_id FROM rendezvous WHERE id = ?";
    if ($stmt_get_rdv = $mysqli->prepare($sql_get_rdv)) {
        $stmt_get_rdv->bind_param("i", $appointment_id);
        $stmt_get_rdv->execute();
        $result_get_rdv = $stmt_get_rdv->get_result();
        
        if ($result_get_rdv->num_rows > 0) {
            $rdv_data = $result_get_rdv->fetch_assoc();
            $current_time = time();
            $appointment_time = strtotime($rdv_data["date_heure"]);
            
            // Vérifier si la date est valide
            if ($appointment_time === false) {
                $errors[] = "Format de date invalide pour le rendez-vous.";
            } else {
                $diff_hours = floor(($appointment_time - $current_time) / 3600);

                if ($action == "cancel") {
                    // Appliquer la politique d'annulation côté coiffeur
                    $sql_policy = "SELECT delai_minimum_heures, penalite_active, description_penalite 
                                   FROM politiques_annulation 
                                   WHERE type_acteur = 'coiffeur'";
                    
                    if ($result_policy = $mysqli->query($sql_policy)) {
                        if ($result_policy->num_rows > 0) {
                            $policy = $result_policy->fetch_assoc();

                            if ($diff_hours < $policy["delai_minimum_heures"]) {
                                $errors[] = "Impossible d'annuler ce rendez-vous. Le délai minimum d'annulation de " . 
                                            $policy["delai_minimum_heures"] . " heures n'est pas respecté.";
                            } else {
                                $new_status = "cancelled_by_coiffeur";
                                $sql_update = "UPDATE rendezvous SET statut = ? WHERE id = ?";
                                if ($stmt = $mysqli->prepare($sql_update)) {
                                    $stmt->bind_param("si", $new_status, $appointment_id);
                                    if ($stmt->execute()) {
                                        $success = "Rendez-vous annulé avec succès.";
                                    } else {
                                        $errors[] = "Erreur lors de l'annulation: " . $stmt->error;
                                    }
                                    $stmt->close();
                                } else {
                                    $errors[] = "Erreur de préparation de la requête: " . $mysqli->error;
                                }
                            }
                        } else {
                            $errors[] = "Politique d'annulation non trouvée.";
                        }
                        $result_policy->free();
                    } else {
                        $errors[] = "Erreur SQL (politique): " . $mysqli->error;
                    }
                } elseif ($action == "confirm") {
                    $new_status = "confirmed";
                    $sql_update = "UPDATE rendezvous SET statut = ? WHERE id = ?";
                    if ($stmt = $mysqli->prepare($sql_update)) {
                        $stmt->bind_param("si", $new_status, $appointment_id);
                        if ($stmt->execute()) {
                            $success = "Rendez-vous confirmé avec succès.";
                        } else {
                            $errors[] = "Erreur lors de la confirmation: " . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $errors[] = "Erreur de préparation de la requête: " . $mysqli->error;
                    }
                }
            }
        } else {
            $errors[] = "Rendez-vous introuvable.";
        }
        $stmt_get_rdv->close();
    } else {
        $errors[] = "Erreur de préparation de la requête: " . $mysqli->error;
    }
}

// Récupérer tous les rendez-vous
$sql_rendezvous = "SELECT r.id, r.date_heure, r.type_prestation, r.statut,r.adresse_domicile, r.telephone, 
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
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $rendezvous[] = $row;
        }
    }
    $result->free();
} else {
    $errors[] = "Erreur lors de la récupération des rendez-vous: " . $mysqli->error;
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
                            <th>Date & Heure</th>
                            <th>Client</th>
                            <th>Coiffeur</th>
                            <th>Service</th>
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
                                <td><?php echo htmlspecialchars(ucfirst($rdv["type_prestation"])); ?></td>
                                <td>
                                    <span class="status-<?php echo strtolower($rdv["statut"]); ?>">
                                        <?php 
                                        $statut_affichage = str_replace('_', ' ', $rdv["statut"]);
                                        echo htmlspecialchars(ucfirst($statut_affichage)); 
                                        ?>
                                    </span>
                                </td>
                                <td><?php if ($rdv["type_prestation"] == 'domicile'): ?><button type="button" class="btn-details btn btn-sm btn-info"
                            data-adresse="<?php echo htmlspecialchars($rdv['adresse_domicile']); ?>" 
                            data-telephone="<?php echo htmlspecialchars($rdv['telephone']); ?>">
                        Voir détails
                    </button>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
                                <td>
                                    <?php if ($rdv["statut"] == "pending"): ?>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                            <input type="hidden" name="appointment_id" value="<?php echo $rdv["id"]; ?>">
                                            <input type="hidden" name="action" value="confirm">
                                            <button type="submit" class="btn btn-success btn-sm">Confirmer</button>
                                        </form>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?');">
                                            <input type="hidden" name="appointment_id" value="<?php echo $rdv["id"]; ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <button type="submit" class="btn btn-danger btn-sm">Annuler</button>
                                        </form>
                                    <?php elseif ($rdv["statut"] == "confirmed"): ?>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?');">
                                            <input type="hidden" name="appointment_id" value="<?php echo $rdv["id"]; ?>">
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
/* Style pour la modale */
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
// Gestion de la modale
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

    closeBtn.addEventListener('click', () => {
        modal.style.display = 'none';
    });

    window.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});
</script>

<?php 
include __DIR__ . "/../includes/footer.php";

// Vider le buffer et afficher
ob_end_flush();