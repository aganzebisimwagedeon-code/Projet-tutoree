<?php
// Activation du rapport d'erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

// Début du buffer pour capturer les erreurs
ob_start();

start_secure_session();

// Redirection si non admin
if (!is_logged_in() || !has_role("admin")) {
    redirect(BASE_URL . "backend/admin_login.php");
    exit; // Important après une redirection
}

$plannings = [];
$coiffeurs = [];
$errors = [];
$success = "";

// Récupérer la liste des coiffeurs (correction des quotes)
$sql_coiffeurs = "SELECT u.id AS user_id, u.username, c.id AS coiffeur_id 
                  FROM users u 
                  JOIN coiffeurs c ON u.id = c.user_id 
                  WHERE u.role = 'coiffeur' 
                  ORDER BY u.username ASC";

if ($result = $mysqli->query($sql_coiffeurs)) {
    while ($row = $result->fetch_assoc()) {
        $coiffeurs[] = $row;
    }
    $result->free();
} else {
    $errors[] = "Erreur lors de la récupération des coiffeurs: " . $mysqli->error;
}

// Gérer l'ajout/modification de planning
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["coiffeur_id"])) {
    $planning_id = isset($_POST["planning_id"]) ? sanitize_input($_POST["planning_id"]) : null;
    $coiffeur_id = sanitize_input($_POST["coiffeur_id"]);
    $jour_semaine = sanitize_input($_POST["jour_semaine"]);
    $heure_debut = sanitize_input($_POST["heure_debut"]);
    $heure_fin = sanitize_input($_POST["heure_fin"]);

    if (empty($coiffeur_id) || empty($jour_semaine) || empty($heure_debut) || empty($heure_fin)) {
        $errors[] = "Tous les champs sont obligatoires.";
    }

    // Validation des heures
    if (strtotime($heure_debut) >= strtotime($heure_fin)) {
        $errors[] = "L'heure de fin doit être après l'heure de début.";
    }

    if (empty($errors)) {
        if (empty($planning_id)) { // Ajout
            $sql = "INSERT INTO plannings (coiffeur_id, jour_semaine, heure_debut, heure_fin) VALUES (?, ?, ?, ?)";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("isss", $coiffeur_id, $jour_semaine, $heure_debut, $heure_fin);
                if ($stmt->execute()) {
                    $success = "Planning ajouté avec succès.";
                } else {
                    $errors[] = "Erreur lors de l'ajout du planning: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = "Erreur de préparation de la requête: " . $mysqli->error;
            }
        } else { // Modification
            $sql = "UPDATE plannings SET coiffeur_id = ?, jour_semaine = ?, heure_debut = ?, heure_fin = ? WHERE id = ?";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("isssi", $coiffeur_id, $jour_semaine, $heure_debut, $heure_fin, $planning_id);
                if ($stmt->execute()) {
                    $success = "Planning modifié avec succès.";
                } else {
                    $errors[] = "Erreur lors de la modification du planning: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = "Erreur de préparation de la requête: " . $mysqli->error;
            }
        }
    }
}

// Gérer la suppression de planning
if (isset($_GET["delete"])) {
    $planning_id_to_delete = sanitize_input($_GET["delete"]);
    $sql_delete = "DELETE FROM plannings WHERE id = ?";
    if ($stmt = $mysqli->prepare($sql_delete)) {
        $stmt->bind_param("i", $planning_id_to_delete);
        if ($stmt->execute()) {
            $success = "Planning supprimé avec succès.";
            // Redirection pour éviter les problèmes de re-soumission
            redirect($_SERVER['PHP_SELF']);
            exit;
        } else {
            $errors[] = "Erreur lors de la suppression du planning: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $errors[] = "Erreur de préparation de la requête: " . $mysqli->error;
    }
}

// Récupérer tous les plannings avec les noms des coiffeurs (correction FIELD)
$sql_plannings = "SELECT p.id, p.jour_semaine, p.heure_debut, p.heure_fin, u.username AS coiffeur_nom, c.id AS coiffeur_id 
                  FROM plannings p 
                  JOIN coiffeurs c ON p.coiffeur_id = c.id 
                  JOIN users u ON c.user_id = u.id 
                  ORDER BY u.username, 
                  CASE p.jour_semaine
                    WHEN 'Lundi' THEN 1
                    WHEN 'Mardi' THEN 2
                    WHEN 'Mercredi' THEN 3
                    WHEN 'Jeudi' THEN 4
                    WHEN 'Vendredi' THEN 5
                    WHEN 'Samedi' THEN 6
                    WHEN 'Dimanche' THEN 7
                  END, p.heure_debut ASC";

if ($result = $mysqli->query($sql_plannings)) {
    while ($row = $result->fetch_assoc()) {
        $plannings[] = $row;
    }
    $result->free();
} else {
    $errors[] = "Erreur lors de la récupération des plannings: " . $mysqli->error;
}

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Gérer les Plannings des Coiffeurs</h1>
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

        <h2>Ajouter ou Modifier un Planning</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="hidden" name="planning_id" id="planning_id" value="">
            <div class="form-group">
                <label for="coiffeur_id_select">Coiffeur :</label>
                <select name="coiffeur_id" id="coiffeur_id_select" required>
                    <option value="">-- Sélectionnez un coiffeur --</option>
                    <?php foreach ($coiffeurs as $coiffeur): ?>
                        <option value="<?php echo $coiffeur["coiffeur_id"]; ?>"><?php echo htmlspecialchars($coiffeur["username"]); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="jour_semaine_select">Jour de la semaine :</label>
                <select name="jour_semaine" id="jour_semaine_select" required>
                    <option value="">-- Sélectionnez un jour --</option>
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
                <label for="heure_debut_input">Heure de début :</label>
                <input type="time" name="heure_debut" id="heure_debut_input" required>
            </div>
            <div class="form-group">
                <label for="heure_fin_input">Heure de fin :</label>
                <input type="time" name="heure_fin" id="heure_fin_input" required>
            </div>
            <button type="submit" class="btn">Enregistrer</button>
            <button type="button" class="btn btn-secondary" onclick="resetPlanningForm();">Nouveau</button>
        </form>

        <hr>

        <h2>Liste des Plannings</h2>
        <?php if (empty($plannings)): ?>
            <div class="alert alert-info">
                <p>Aucun planning n'a été créé pour le moment.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Coiffeur</th>
                            <th>Jour</th>
                            <th>Heure Début</th>
                            <th>Heure Fin</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plannings as $planning): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($planning["coiffeur_nom"]); ?></td>
                                <td><?php echo htmlspecialchars($planning["jour_semaine"]); ?></td>
                                <td><?php echo htmlspecialchars(substr($planning["heure_debut"], 0, 5)); ?></td>
                                <td><?php echo htmlspecialchars(substr($planning["heure_fin"], 0, 5)); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-edit" onclick="editPlanning(<?php echo htmlspecialchars(json_encode($planning)); ?>)">Modifier</button>
                                    <a href="?delete=<?php echo $planning["id"]; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce planning ?');">Supprimer</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
function editPlanning(planning) {
    document.getElementById('planning_id').value = planning.id;
    document.getElementById('coiffeur_id_select').value = planning.coiffeur_id;
    document.getElementById('jour_semaine_select').value = planning.jour_semaine;
    document.getElementById('heure_debut_input').value = planning.heure_debut.substring(0, 5);
    document.getElementById('heure_fin_input').value = planning.heure_fin.substring(0, 5);
    window.scrollTo(0, 0);
}

function resetPlanningForm() {
    document.getElementById('planning_id').value = '';
    document.getElementById('coiffeur_id_select').value = '';
    document.getElementById('jour_semaine_select').value = '';
    document.getElementById('heure_debut_input').value = '';
    document.getElementById('heure_fin_input').value = '';
}
</script>

<?php 
include __DIR__ . "/../includes/footer.php";

// Vider le buffer et afficher
ob_end_flush();