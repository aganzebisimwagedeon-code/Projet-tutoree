<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();
require_role('coiffeur');

$coiffeur_id = (int) $_SESSION["user_id"];
$errors = [];
$success = "";

$sql_coiffeur = "SELECT id FROM coiffeurs WHERE user_id = ? LIMIT 1";
$coiffeur_table_id = null;
if ($stmt = $mysqli->prepare($sql_coiffeur)) {
    $stmt->bind_param("i", $coiffeur_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $coiffeur_table_id = (int) $row["id"];
    }
    $stmt->close();
}

$jours_valides = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && $coiffeur_table_id) {
    if (!verify_csrf_token()) {
        $errors[] = "Jeton de sécurité invalide. Veuillez réessayer.";
    } elseif (isset($_POST["action"])) {
        $action = sanitize_input($_POST["action"], 30);

        if ($action === "add") {
            $jour_semaine = sanitize_input($_POST["jour_semaine"] ?? "", 20);
            $heure_debut = validate_time_format($_POST["heure_debut"] ?? "");
            $heure_fin = validate_time_format($_POST["heure_fin"] ?? "");

            if (!in_array($jour_semaine, $jours_valides, true) || !$heure_debut || !$heure_fin) {
                $errors[] = "Veuillez sélectionner un jour et des horaires valides.";
            } elseif ($heure_debut >= $heure_fin) {
                $errors[] = "L'heure de fin doit être postérieure à l'heure de début.";
            } else {
                $sql = "INSERT INTO plannings (coiffeur_id, jour_semaine, heure_debut, heure_fin) VALUES (?, ?, ?, ?)";
                if ($stmt = $mysqli->prepare($sql)) {
                    $stmt->bind_param("isss", $coiffeur_table_id, $jour_semaine, $heure_debut, $heure_fin);
                    if ($stmt->execute()) {
                        $success = "Plage horaire ajoutée avec succès.";
                    } else {
                        $errors[] = "Impossible d'ajouter ce créneau (il existe peut-être déjà).";
                    }
                    $stmt->close();
                }
            }
        } elseif ($action === "delete") {
            $planning_id = validate_int($_POST["planning_id"] ?? null, 1);
            if ($planning_id && ($stmt = $mysqli->prepare("DELETE FROM plannings WHERE id = ? AND coiffeur_id = ?"))) {
                $stmt->bind_param("ii", $planning_id, $coiffeur_table_id);
                if ($stmt->execute()) {
                    $success = "Plage horaire supprimée.";
                }
                $stmt->close();
            }
        } elseif ($action === "add_absence") {
            $date_debut = sanitize_input($_POST["date_debut"] ?? "", 30);
            $date_fin = sanitize_input($_POST["date_fin"] ?? "", 30);
            $motif = sanitize_input($_POST["motif"] ?? "", 255);
            $tsDebut = strtotime($date_debut);
            $tsFin = strtotime($date_fin);

            if (!$tsDebut || !$tsFin || $tsDebut >= $tsFin) {
                $errors[] = "Veuillez fournir des dates de début et de fin valides.";
            } else {
                $sqlStart = date('Y-m-d H:i:s', $tsDebut);
                $sqlEnd = date('Y-m-d H:i:s', $tsFin);
                $sql = "INSERT INTO absences_coiffeurs (coiffeur_id, date_debut, date_fin, motif) VALUES (?, ?, ?, ?)";
                if ($stmt = $mysqli->prepare($sql)) {
                    $stmt->bind_param("isss", $coiffeur_table_id, $sqlStart, $sqlEnd, $motif);
                    if ($stmt->execute()) {
                        $success = "Absence déclarée avec succès.";
                    } else {
                        $errors[] = "Erreur lors de l'enregistrement de l'absence.";
                    }
                    $stmt->close();
                }
            }
        } elseif ($action === "delete_absence") {
            $abs_id = validate_int($_POST["absence_id"] ?? null, 1);
            if ($abs_id && ($stmt = $mysqli->prepare("DELETE FROM absences_coiffeurs WHERE id = ? AND coiffeur_id = ?"))) {
                $stmt->bind_param("ii", $abs_id, $coiffeur_table_id);
                if ($stmt->execute()) {
                    $success = "Absence supprimée.";
                }
                $stmt->close();
            }
        }
    }
}

$plannings = [];
$absences = [];
if ($coiffeur_table_id) {
    $sql = "SELECT * FROM plannings WHERE coiffeur_id = ? ORDER BY FIELD(jour_semaine, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'), heure_debut ASC";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("i", $coiffeur_table_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $plannings[] = $row;
        }
        $stmt->close();
    }

    $sqlAbs = "SELECT * FROM absences_coiffeurs WHERE coiffeur_id = ? ORDER BY date_debut DESC";
    if ($stmtA = $mysqli->prepare($sqlAbs)) {
        $stmtA->bind_param("i", $coiffeur_table_id);
        $stmtA->execute();
        $resA = $stmtA->get_result();
        while ($rowA = $resA->fetch_assoc()) {
            $absences[] = $rowA;
        }
        $stmtA->close();
    }
}

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Mon Planning &amp; Mes Absences</h1>
    </div>
</section>

<section class="coiffeur-planning">
    <div class="container">
        <a href="<?php echo BASE_URL; ?>backend/coiffeur_dashboard.php" class="btn-back">← Retour au tableau de bord</a>

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

        <h2>Gestion de mon planning hebdomadaire</h2>

        <div class="add-planning">
            <h3>Ajouter une plage horaire</h3>
            <form method="post" class="planning-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <div class="form-group">
                        <label for="jour_semaine">Jour de la semaine :</label>
                        <select name="jour_semaine" id="jour_semaine" required>
                            <option value="">Sélectionner un jour</option>
                            <?php foreach ($jours_valides as $j): ?>
                                <option value="<?php echo $j; ?>"><?php echo $j; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="heure_debut">Heure de début :</label>
                        <input type="time" name="heure_debut" id="heure_debut" required>
                    </div>
                    <div class="form-group">
                        <label for="heure_fin">Heure de fin :</label>
                        <input type="time" name="heure_fin" id="heure_fin" required>
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
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="planning_id" value="<?php echo (int) $planning["id"]; ?>">
                                        <button type="submit" class="btn-action btn-cancel" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette plage horaire ?')">
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

        <hr style="margin:30px 0;">

        <h2>Mes Congés &amp; Absences Exceptionnelles</h2>
        <form method="post" class="planning-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_absence">
            <div class="form-row">
                <div class="form-group">
                    <label for="date_debut">Début :</label>
                    <input type="datetime-local" name="date_debut" id="date_debut" required>
                </div>
                <div class="form-group">
                    <label for="date_fin">Fin :</label>
                    <input type="datetime-local" name="date_fin" id="date_fin" required>
                </div>
                <div class="form-group">
                    <label for="motif">Motif :</label>
                    <input type="text" name="motif" id="motif" maxlength="255" placeholder="Congé, indisponibilité...">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn-primary">+ Déclarer une absence</button>
                </div>
            </div>
        </form>

        <?php if (!empty($absences)): ?>
            <div class="modern-table-container">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Du</th>
                            <th>Au</th>
                            <th>Motif</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($absences as $abs): ?>
                            <tr>
                                <td data-label="Du"><?php echo date('d/m/Y H:i', strtotime($abs['date_debut'])); ?></td>
                                <td data-label="Au"><?php echo date('d/m/Y H:i', strtotime($abs['date_fin'])); ?></td>
                                <td data-label="Motif"><?php echo htmlspecialchars($abs['motif'] ?? '-'); ?></td>
                                <td data-label="Actions">
                                    <form method="post">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_absence">
                                        <input type="hidden" name="absence_id" value="<?php echo (int) $abs['id']; ?>">
                                        <button type="submit" class="btn-action btn-cancel" onclick="return confirm('Supprimer cette absence ?')">Supprimer</button>
                                    </form>
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
    min-width: 600px;
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
.btn-action {
    border: none;
    border-radius: 4px;
    padding: 8px 12px;
    cursor: pointer;
    font-weight: 500;
}
.btn-cancel {
    background: #dc3545;
    color: white;
}
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
</style>

<?php include __DIR__ . "/../includes/footer.php"; ?>
