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

// Récupérer l'ID du coiffeur dans la table coiffeurs
$sql_coiffeur = "SELECT id FROM coiffeurs WHERE user_id = ?";
$coiffeur_table_id = null;
if ($stmt = $mysqli->prepare($sql_coiffeur)) {
    $stmt->bind_param("i", $coiffeur_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $coiffeur_table_id = $row["id"];
    }
    $stmt->close();
}

// Traitement des actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && $coiffeur_table_id) {
    if (isset($_POST["action"])) {
        $action = $_POST["action"];
        
        if ($action == "add") {
            $jour_semaine = $_POST["jour_semaine"];
            $heure_debut = $_POST["heure_debut"];
            $heure_fin = $_POST["heure_fin"];
            
            $sql = "INSERT INTO plannings (coiffeur_id, jour_semaine, heure_debut, heure_fin) VALUES (?, ?, ?, ?)";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("isss", $coiffeur_table_id, $jour_semaine, $heure_debut, $heure_fin);
                $stmt->execute();
                $stmt->close();
            }
        } elseif ($action == "delete") {
            $planning_id = intval($_POST["planning_id"]);
            $sql = "DELETE FROM plannings WHERE id = ? AND coiffeur_id = ?";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("ii", $planning_id, $coiffeur_table_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

// Récupérer le planning du coiffeur
$sql = "SELECT * FROM plannings WHERE coiffeur_id = ? ORDER BY FIELD(jour_semaine, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche')";
$plannings = [];
if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("i", $coiffeur_table_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $plannings[] = $row;
    }
    $stmt->close();
}

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Mon Planning</h1>
    </div>
</section>


<section class="coiffeur-planning">
    <div class="container">
        <a href="<?php echo BASE_URL; ?>backend/coiffeur_dashboard.php" class="btn-back">← Retour au tableau de bord</a>
        
        <h2>Gestion de mon planning</h2>
        
        <div class="add-planning">
            <h3>Ajouter une plage horaire</h3>
            <form method="post" class="planning-form">
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <div class="form-group">
                        <label for="jour_semaine">Jour de la semaine:</label>
                        <select name="jour_semaine" required>
                            <!-- options existantes -->
                            <option value="">Sélectionner un jour</option>
                             <option value="Lundi">Lundi</option>
                              <option value="Mardi">Mardi</option>
                              <option value="Mercredi">Mercredi</option>
                              <option value="Jeudi">Jeudi</option>
                            <option value="Vendredi">Vendredi</option>
                            <option value="Samedi">Samedi</option>
                            <option value="Dimanche">Dimanche</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="heure_debut">Heure de début:</label>
                        <input type="time" name="heure_debut" required>
                    </div>
                    <div class="form-group">
                        <label for="heure_fin">Heure de fin:</label>
                        <input type="time" name="heure_fin" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn-primary">+ Ajouter</button>
                    </div>
                </div>
            </form>
        </div>
        
        <h3>Mon planning actuel</h3>
        <?php if (empty($plannings)): ?>
            <p>Aucune plage horaire définie.</p>
        <?php else: ?>
            <div class="modern-table-container">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Jour</th>
                            <th>Heure de début</th>
                            <th>Heure de fin</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plannings as $planning): ?>
                            <tr>
                                <td data-label="Jour"><?php echo htmlspecialchars($planning["jour_semaine"]); ?></td>
                                <td data-label="Heure de début"><?php echo date("H:i", strtotime($planning["heure_debut"])); ?></td>
                                <td data-label="Heure de fin"><?php echo date("H:i", strtotime($planning["heure_fin"])); ?></td>
                                <td data-label="Actions">
                                    <form method="post">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="planning_id" value="<?php echo $planning["id"]; ?>">
                                        <button type="submit" class="btn-action btn-delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette plage horaire ?')">
                                            Supprimer
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</style>

<style>
    /* Styles identiques à ceux de coiffeur_rendezvous.php */
    .modern-table-container {
        overflow-x: auto;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        margin: 20px 0;
    }
    
    /* ... (copier les mêmes styles CSS que dans coiffeur_rendezvous.php) ... */

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

    /* Styles spécifiques au formulaire */
    .planning-form {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 30px;
    }
    
    .form-row {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }
    
    .form-group {
        flex: 1;
        min-width: 200px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
    }
    
    .form-group select, 
    .form-group input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ced4da;
        border-radius: 4px;
    }
    
    .btn-primary {
        background: #007bff;
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
        width: 100%;
    }
    
    /* Responsive pour mobiles */
    @media (max-width: 768px) {
        .form-row {
            flex-direction: column;
        }
        
        .form-group {
            width: 100%;
            min-width: unset;
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
        /* Copier les styles responsive de table du fichier précédent */
    }
</style>

<?php include __DIR__ . "/../includes/footer.php"; ?>