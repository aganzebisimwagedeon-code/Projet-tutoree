<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();
require_role("admin");

$plannings = [];
$absences = [];
$coiffeurs = [];
$errors = [];
$success = "";

$jours_valides = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

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
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        $errors[] = "Jeton de sécurité invalide. Veuillez réessayer.";
    } elseif (isset($_POST["delete_planning_id"])) {
        $planning_id_to_delete = validate_int($_POST["delete_planning_id"], 1);
        if ($planning_id_to_delete && ($stmt = $mysqli->prepare("DELETE FROM plannings WHERE id = ?"))) {
            $stmt->bind_param("i", $planning_id_to_delete);
            if ($stmt->execute()) {
                $success = "Planning supprimé avec succès.";
            } else {
                $errors[] = "Erreur lors de la suppression du planning.";
            }
            $stmt->close();
        }
    } elseif (isset($_POST["delete_absence_id"])) {
        $abs_id = validate_int($_POST["delete_absence_id"], 1);
        if ($abs_id && ($stmt = $mysqli->prepare("DELETE FROM absences_coiffeurs WHERE id = ?"))) {
            $stmt->bind_param("i", $abs_id);
            if ($stmt->execute()) {
                $success = "Absence supprimée avec succès.";
            } else {
                $errors[] = "Erreur lors de la suppression de l'absence.";
            }
            $stmt->close();
        }
    } elseif (isset($_POST["submit_absence"])) {
        $coiffeur_id = validate_int($_POST["coiffeur_id"] ?? null, 1);
        $date_debut = sanitize_input($_POST["date_debut"] ?? "", 30);
        $date_fin = sanitize_input($_POST["date_fin"] ?? "", 30);
        $motif = sanitize_input($_POST["motif"] ?? "", 255);

        $tsDebut = strtotime($date_debut);
        $tsFin = strtotime($date_fin);

        if (!$coiffeur_id || !$tsDebut || !$tsFin) {
            $errors[] = "Veuillez renseigner un coiffeur et des dates de début/fin valides.";
        } elseif ($tsDebut >= $tsFin) {
            $errors[] = "La date de fin de l'absence doit être postérieure à la date de début.";
        } else {
            $sqlStart = date('Y-m-d H:i:s', $tsDebut);
            $sqlEnd = date('Y-m-d H:i:s', $tsFin);
            $sqlAbs = "INSERT INTO absences_coiffeurs (coiffeur_id, date_debut, date_fin, motif) VALUES (?, ?, ?, ?)";
            if ($stmt = $mysqli->prepare($sqlAbs)) {
                $stmt->bind_param("isss", $coiffeur_id, $sqlStart, $sqlEnd, $motif);
                if ($stmt->execute()) {
                    $success = "Absence / congé enregistré avec succès.";
                } else {
                    $errors[] = "Erreur lors de l'enregistrement de l'absence.";
                }
                $stmt->close();
            }
        }
    } elseif (isset($_POST["coiffeur_id"])) {
        $planning_id = validate_int($_POST["planning_id"] ?? null, 1);
        $coiffeur_id = validate_int($_POST["coiffeur_id"] ?? null, 1);
        $jour_semaine = sanitize_input($_POST["jour_semaine"] ?? "", 20);
        $heure_debut = validate_time_format($_POST["heure_debut"] ?? "");
        $heure_fin = validate_time_format($_POST["heure_fin"] ?? "");

        if (!$coiffeur_id || !in_array($jour_semaine, $jours_valides, true) || !$heure_debut || !$heure_fin) {
            $errors[] = "Tous les champs du planning sont obligatoires.";
        } elseif ($heure_debut >= $heure_fin) {
            $errors[] = "L'heure de fin doit être postérieure à l'heure de début.";
        } else {
            if ($planning_id === null) {
                $sql = "INSERT INTO plannings (coiffeur_id, jour_semaine, heure_debut, heure_fin) VALUES (?, ?, ?, ?)";
                if ($stmt = $mysqli->prepare($sql)) {
                    $stmt->bind_param("isss", $coiffeur_id, $jour_semaine, $heure_debut, $heure_fin);
                    if ($stmt->execute()) {
                        $success = "Planning ajouté avec succès.";
                    } else {
                        $errors[] = "Erreur lors de l'ajout du planning (un planning identique existe peut-être déjà).";
                    }
                    $stmt->close();
                }
            } else {
                $sql = "UPDATE plannings SET coiffeur_id = ?, jour_semaine = ?, heure_debut = ?, heure_fin = ? WHERE id = ?";
                if ($stmt = $mysqli->prepare($sql)) {
                    $stmt->bind_param("isssi", $coiffeur_id, $jour_semaine, $heure_debut, $heure_fin, $planning_id);
                    if ($stmt->execute()) {
                        $success = "Planning modifié avec succès.";
                    } else {
                        $errors[] = "Erreur lors de la modification du planning.";
                    }
                    $stmt->close();
                }
            }
        }
    }
}

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
}

$sql_absences = "SELECT a.id, a.date_debut, a.date_fin, a.motif, u.username AS coiffeur_nom
                 FROM absences_coiffeurs a
                 JOIN coiffeurs c ON a.coiffeur_id = c.id
                 JOIN users u ON c.user_id = u.id
                 ORDER BY a.date_debut DESC";
if ($resultA = $mysqli->query($sql_absences)) {
    while ($rowA = $resultA->fetch_assoc()) {
        $absences[] = $rowA;
    }
    $resultA->free();
}

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Gérer les Plannings &amp; Absences des Coiffeurs</h1>
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

        <h2>Ajouter ou Modifier un Planning Hebdomadaire</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="planning_id" id="planning_id" value="">
            <div class="form-group">
                <label for="coiffeur_id_select">Coiffeur :</label>
                <select name="coiffeur_id" id="coiffeur_id_select" required>
                    <option value="">-- Sélectionnez un coiffeur --</option>
                    <?php foreach ($coiffeurs as $coiffeur): ?>
                        <option value="<?php echo (int) $coiffeur["coiffeur_id"]; ?>"><?php echo htmlspecialchars($coiffeur["username"]); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="jour_semaine_select">Jour de la semaine :</label>
                <select name="jour_semaine" id="jour_semaine_select" required>
                    <option value="">-- Sélectionnez un jour --</option>
                    <?php foreach ($jours_valides as $j): ?>
                        <option value="<?php echo $j; ?>"><?php echo $j; ?></option>
                    <?php endforeach; ?>
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
            <button type="submit" class="btn">Enregistrer Planning</button>
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
                                    <button type="button" class="btn btn-sm btn-edit" onclick="editPlanning(<?php echo htmlspecialchars(json_encode($planning), ENT_QUOTES, 'UTF-8'); ?>)">Modifier</button>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce planning ?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="delete_planning_id" value="<?php echo (int) $planning["id"]; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <hr>

        <h2>Déclarer une Absence / Congé Exceptionnel (Phase 2.4)</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="submit_absence" value="1">
            <div class="form-group">
                <label for="abs_coiffeur_id">Coiffeur :</label>
                <select name="coiffeur_id" id="abs_coiffeur_id" required>
                    <option value="">-- Sélectionnez un coiffeur --</option>
                    <?php foreach ($coiffeurs as $coiffeur): ?>
                        <option value="<?php echo (int) $coiffeur["coiffeur_id"]; ?>"><?php echo htmlspecialchars($coiffeur["username"]); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="date_debut">Début de l'indisponibilité :</label>
                <input type="datetime-local" name="date_debut" id="date_debut" required>
            </div>
            <div class="form-group">
                <label for="date_fin">Fin de l'indisponibilité :</label>
                <input type="datetime-local" name="date_fin" id="date_fin" required>
            </div>
            <div class="form-group">
                <label for="motif">Motif (optionnel) :</label>
                <input type="text" name="motif" id="motif" maxlength="255" placeholder="Congé, formation, indisponibilité...">
            </div>
            <button type="submit" class="btn">Enregistrer l'absence</button>
        </form>

        <h3>Absences Enregistrées</h3>
        <?php if (empty($absences)): ?>
            <p>Aucune absence enregistrée.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Coiffeur</th>
                            <th>Du</th>
                            <th>Au</th>
                            <th>Motif</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($absences as $abs): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($abs["coiffeur_nom"]); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($abs["date_debut"])); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($abs["date_fin"])); ?></td>
                                <td><?php echo htmlspecialchars($abs["motif"] ?? '-'); ?></td>
                                <td>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette absence ?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="delete_absence_id" value="<?php echo (int) $abs["id"]; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
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

<?php include __DIR__ . "/../includes/footer.php"; ?>
