<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();

// Vérifier si l'utilisateur est connecté et a le rôle coiffeur
if (!is_logged_in() || !has_role('coiffeur')) {
    redirect(BASE_URL . "login.php");
}

$coiffeur_id = $_SESSION["user_id"];

// Récupérer l'ID du profil coiffeur
$sql_coiffeur = "SELECT id FROM coiffeurs WHERE user_id = ?";
$stmt_profile = $mysqli->prepare($sql_coiffeur);
$stmt_profile->bind_param("i", $coiffeur_id);
$stmt_profile->execute();
$result_profile = $stmt_profile->get_result();
$coiffeur_profile = $result_profile->fetch_assoc();
$stmt_profile->close();

if (!$coiffeur_profile) {
    die("Profil coiffeur non trouvé.");
}

$coiffeur_profile_id = $coiffeur_profile['id'];

// Traitement des actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"])) {
    $action = $_POST["action"];
    $rdv_id = intval($_POST["rdv_id"]);
    
    if ($action == "confirm") {
        $sql = "UPDATE rendezvous SET statut = 'confirmed' WHERE id = ? AND coiffeur_id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("ii", $rdv_id, $coiffeur_profile_id);
            if (!$stmt->execute()) {
                error_log("Erreur SQL: " . $stmt->error);
            }
            $stmt->close();
        }
    } elseif ($action == "cancel") {
        $sql = "UPDATE rendezvous SET statut = 'cancelled_by_coiffeur' WHERE id = ? AND coiffeur_id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("ii", $rdv_id, $coiffeur_profile_id);
            if (!$stmt->execute()) {
                error_log("Erreur SQL: " . $stmt->error);
            }
            $stmt->close();
        }
    } elseif ($action == "complete") {
        $sql = "UPDATE rendezvous SET statut = 'completed' WHERE id = ? AND coiffeur_id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("ii", $rdv_id, $coiffeur_profile_id);
            if (!$stmt->execute()) {
                error_log("Erreur SQL: " . $stmt->error);
            }
            $stmt->close();
        }
    }
    redirect(BASE_URL . "backend/coiffeur_rendezvous.php");
    exit;
}

// Récupérer les rendez-vous du coiffeur (ajout des champs adresse_domicile et telephone)
$sql = "SELECT r.id, r.date_heure, r.type_prestation, r.statut, r.adresse_domicile, r.telephone, 
        u.username as client_nom, s.nom as service_nom, s.prix_depart 
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
        
        <?php if (empty($rendezvous)): ?>
            <p>Aucun rendez-vous trouvé.</p>
        <?php else: ?>
            <!-- Nouveau design de tableau -->
            <div class="modern-table-container">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Date & Heure</th>
                            <th>Client</th>
                            <th>Service</th>
                            <th>Prix</th>
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
                                <td data-label="Prix"><?php echo number_format($rdv["prix_depart"], 2); ?>$</td>
                                <td data-label="Type"><?php echo ucfirst($rdv["type_prestation"]); ?></td>
                                <td data-label="Infos domicile">
                                    <?php if ($rdv["type_prestation"] == 'domicile'): ?>
                                        <button type="button" class="btn-details" 
                                                data-adresse="<?php echo htmlspecialchars($rdv['adresse_domicile']); ?>" 
                                                data-telephone="<?php echo htmlspecialchars($rdv['telephone']); ?>">
                                            Voir détails
                                        </button>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td data-label="Statut">
                                    <span class="status-badge status-<?php echo $rdv["statut"]; ?>">
                                        <?php 
                                        switch($rdv["statut"]) {
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
                                    <?php if ($rdv["statut"] == "pending"): ?>
                                        <div class="action-buttons">
                                            <form method="post">
                                                <input type="hidden" name="rdv_id" value="<?php echo $rdv["id"]; ?>">
                                                <input type="hidden" name="action" value="confirm">
                                                <button type="submit" class="btn-action btn-confirm">✓</button>
                                            </form>
                                            <form method="post">
                                                <input type="hidden" name="rdv_id" value="<?php echo $rdv["id"]; ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <button type="submit" class="btn-action btn-cancel">✕</button>
                                            </form>
                                        </div>
                                    <?php elseif ($rdv["statut"] == "confirmed"): ?>
                                        <form method="post">
                                            <input type="hidden" name="rdv_id" value="<?php echo $rdv["id"]; ?>">
                                            <input type="hidden" name="action" value="complete">
                                            <button type="submit" class="btn-action btn-complete">Terminer</button>
                                        </form>
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
    
    .actions-cell {
        white-space: nowrap;
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
    }
    
    .btn-action {
        border: none;
        border-radius: 4px;
        padding: 8px 12px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.2s;
    }
    
    .btn-confirm {
        background: #28a745;
        color: white;
    }
    
    .btn-cancel {
        background: #dc3545;
        color: white;
    }
    
    .btn-complete {
        background: #17a2b8;
        color: white;
    }
    
    /* Style pour la modale */
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
    
    .close-modal:hover {
        color: #000;
    }
    
    /* Style responsive pour mobiles */
    @media (max-width: 768px) {
        .modern-table {
            min-width: 100%;
        }
        
        .modern-table thead {
            display: none;
        }
        
        .modern-table, .modern-table tbody, .modern-table tr, .modern-table td {
            display: block;
            width: 100%;
        }
        
        .modern-table tr {
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .modern-table td {
            padding: 12px;
            text-align: right;
            position: relative;
            padding-left: 50%;
            border-bottom: 1px solid #e9ecef;
        }
        
        .modern-table td:before {
            content: attr(data-label);
            position: absolute;
            left: 15px;
            width: calc(50% - 15px);
            padding-right: 10px;
            text-align: left;
            font-weight: 600;
            color: #495057;
        }
        
        .actions-cell {
            text-align: center !important;
            padding-left: 15px !important;
        }
        
        .actions-cell:before {
            display: none;
        }
        
        .action-buttons {
            justify-content: center;
        }
    }
</style>

<!-- Modal pour afficher les détails domicile -->
<div id="detailsModal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h3>Détails du domicile</h3>
        <p><strong>Adresse :</strong> <span id="modal-adresse"></span></p>
        <p><strong>Téléphone :</strong> <span id="modal-telephone"></span></p>
    </div>
</div>

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

<?php include __DIR__ . "/../includes/footer.php"; ?>