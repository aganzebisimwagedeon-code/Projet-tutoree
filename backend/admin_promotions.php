<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();
require_role("admin");

$promotions = [];
$horaires = [];
$errors = [];
$success = "";

$jours_semaine = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        $errors[] = "Jeton de sécurité invalide. Veuillez réessayer.";
    } elseif (isset($_POST["delete_promotion_id"])) {
        $promotion_id_to_delete = validate_int($_POST["delete_promotion_id"], 1);
        if ($promotion_id_to_delete && ($stmt = $mysqli->prepare("DELETE FROM promotions WHERE id = ?"))) {
            $stmt->bind_param("i", $promotion_id_to_delete);
            if ($stmt->execute()) {
                $success = "Promotion supprimée avec succès.";
            } else {
                $errors[] = "Erreur lors de la suppression de la promotion.";
            }
            $stmt->close();
        }
    } elseif (isset($_POST["delete_horaire_id"])) {
        $horaire_id_to_delete = validate_int($_POST["delete_horaire_id"], 1);
        if ($horaire_id_to_delete && ($stmt = $mysqli->prepare("DELETE FROM horaires_ouverture WHERE id = ?"))) {
            $stmt->bind_param("i", $horaire_id_to_delete);
            if ($stmt->execute()) {
                $success = "Horaire supprimé avec succès.";
            } else {
                $errors[] = "Erreur lors de la suppression de l'horaire.";
            }
            $stmt->close();
        }
    } elseif (isset($_POST["submit_promotion"])) {
        $promotion_id = validate_int($_POST["promotion_id"] ?? null, 1);
        $titre = sanitize_input($_POST["titre"] ?? "", 255);
        $description = sanitize_input($_POST["description"] ?? "", 2000);
        $date_debut = sanitize_input($_POST["date_debut"] ?? "", 20);
        $date_fin = sanitize_input($_POST["date_fin"] ?? "", 20);

        $dtDebut = validate_date_format($date_debut, 'Y-m-d');
        $dtFin = validate_date_format($date_fin, 'Y-m-d');

        if ($titre === "" || !$dtDebut || !$dtFin) {
            $errors[] = "Le titre, la date de début et la date de fin sont obligatoires.";
        } elseif ($dtDebut > $dtFin) {
            $errors[] = "La date de début ne peut pas être postérieure à la date de fin.";
        } else {
            if ($promotion_id === null) {
                $sql = "INSERT INTO promotions (titre, description, date_debut, date_fin) VALUES (?, ?, ?, ?)";
                if ($stmt = $mysqli->prepare($sql)) {
                    $stmt->bind_param("ssss", $titre, $description, $date_debut, $date_fin);
                    if ($stmt->execute()) {
                        $success = "Promotion ajoutée avec succès.";
                    } else {
                        $errors[] = "Erreur lors de l'ajout de la promotion.";
                    }
                    $stmt->close();
                }
            } else {
                $sql = "UPDATE promotions SET titre = ?, description = ?, date_debut = ?, date_fin = ? WHERE id = ?";
                if ($stmt = $mysqli->prepare($sql)) {
                    $stmt->bind_param("ssssi", $titre, $description, $date_debut, $date_fin, $promotion_id);
                    if ($stmt->execute()) {
                        $success = "Promotion modifiée avec succès.";
                    } else {
                        $errors[] = "Erreur lors de la modification de la promotion.";
                    }
                    $stmt->close();
                }
            }
        }
    } elseif (isset($_POST["submit_horaires"])) {
        $horaire_id = validate_int($_POST["horaire_id"] ?? null, 1);
        $jour_semaine = sanitize_input($_POST["jour_semaine"] ?? "", 20);
        $ferme = isset($_POST["ferme"]) ? 1 : 0;
        $heure_ouverture = $ferme ? '00:00:00' : validate_time_format($_POST["heure_ouverture"] ?? "");
        $heure_fermeture = $ferme ? '00:00:00' : validate_time_format($_POST["heure_fermeture"] ?? "");

        if (!in_array($jour_semaine, $jours_semaine, true)) {
            $errors[] = "Veuillez sélectionner un jour de la semaine valide.";
        }
        if (!$ferme) {
            if (!$heure_ouverture || !$heure_fermeture) {
                $errors[] = "Les heures d'ouverture et de fermeture sont obligatoires si le salon est ouvert.";
            } elseif ($heure_ouverture >= $heure_fermeture) {
                $errors[] = "L'heure d'ouverture doit être antérieure à l'heure de fermeture.";
            }
        }

        if (empty($errors)) {
            $sql = "INSERT INTO horaires_ouverture (jour_semaine, heure_ouverture, heure_fermeture, ferme)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE heure_ouverture = VALUES(heure_ouverture), heure_fermeture = VALUES(heure_fermeture), ferme = VALUES(ferme)";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("sssi", $jour_semaine, $heure_ouverture, $heure_fermeture, $ferme);
                if ($stmt->execute()) {
                    $success = "Horaires enregistrés avec succès.";
                } else {
                    $errors[] = "Erreur lors de l'enregistrement des horaires.";
                }
                $stmt->close();
            }
        }
    }
}

$sql_promotions = "SELECT * FROM promotions ORDER BY date_debut DESC";
if ($result = $mysqli->query($sql_promotions)) {
    while ($row = $result->fetch_assoc()) {
        $promotions[] = $row;
    }
    $result->free();
}

$sql_horaires = "SELECT * FROM horaires_ouverture ORDER BY FIELD(jour_semaine, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche')";
if ($result = $mysqli->query($sql_horaires)) {
    while ($row = $result->fetch_assoc()) {
        $horaires[$row["jour_semaine"]] = $row;
    }
    $result->free();
}

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Gérer les Promotions et Horaires</h1>
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

        <h2>Gérer les Promotions</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="promotion_id" id="promotion_id" value="">
            <div class="form-group">
                <label for="titre">Titre de la promotion :</label>
                <input type="text" name="titre" id="titre" maxlength="255" required>
            </div>
            <div class="form-group">
                <label for="description">Description :</label>
                <textarea name="description" id="description" rows="3" maxlength="2000"></textarea>
            </div>
            <div class="form-group">
                <label for="date_debut">Date de début :</label>
                <input type="date" name="date_debut" id="date_debut" required>
            </div>
            <div class="form-group">
                <label for="date_fin">Date de fin :</label>
                <input type="date" name="date_fin" id="date_fin" required>
            </div>
            <button type="submit" name="submit_promotion" class="btn">Enregistrer Promotion</button>
            <button type="reset" class="btn btn-secondary" onclick="resetPromotionForm();">Nouvelle Promotion</button>
        </form>

        <hr>

        <h3>Liste des Promotions</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Description</th>
                        <th>Début</th>
                        <th>Fin</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($promotions)): ?>
                        <tr>
                            <td colspan="5">Aucune promotion trouvée.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($promotions as $promo): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($promo["titre"]); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($promo["description"] ?? '')); ?></td>
                                <td><?php echo date("d/m/Y", strtotime($promo["date_debut"])); ?></td>
                                <td><?php echo date("d/m/Y", strtotime($promo["date_fin"])); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm" onclick="editPromotion(<?php echo htmlspecialchars(json_encode($promo), ENT_QUOTES, 'UTF-8'); ?>)">Modifier</button>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette promotion ?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="delete_promotion_id" value="<?php echo (int) $promo["id"]; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <hr>

        <h2>Gérer les Horaires d'Ouverture</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="horaire_id" id="horaire_id" value="">
            <div class="form-group">
                <label for="jour_semaine">Jour de la semaine :</label>
                <select name="jour_semaine" id="jour_semaine" required>
                    <option value="">-- Sélectionnez un jour --</option>
                    <?php foreach ($jours_semaine as $jour): ?>
                        <option value="<?php echo $jour; ?>"><?php echo $jour; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="heure_ouverture">Heure d'ouverture :</label>
                <input type="time" name="heure_ouverture" id="heure_ouverture">
            </div>
            <div class="form-group">
                <label for="heure_fermeture">Heure de fermeture :</label>
                <input type="time" name="heure_fermeture" id="heure_fermeture">
            </div>
            <div class="form-group">
                <input type="checkbox" name="ferme" id="ferme" value="1" onchange="toggleHeures()">
                <label for="ferme">Fermé toute la journée</label>
            </div>
            <button type="submit" name="submit_horaires" class="btn">Enregistrer Horaires</button>
            <button type="reset" class="btn btn-secondary" onclick="resetHorairesForm();">Nouveaux Horaires</button>
        </form>

        <hr>

        <h3>Horaires Actuels</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Jour</th>
                        <th>Ouverture</th>
                        <th>Fermeture</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jours_semaine as $jour): ?>
                        <?php $h = $horaires[$jour] ?? null; ?>
                        <tr>
                            <td><?php echo $jour; ?></td>
                            <td><?php echo $h && !$h["ferme"] ? substr($h["heure_ouverture"], 0, 5) : "-"; ?></td>
                            <td><?php echo $h && !$h["ferme"] ? substr($h["heure_fermeture"], 0, 5) : "-"; ?></td>
                            <td><?php echo $h ? ($h["ferme"] ? "Fermé" : "Ouvert") : "Non défini"; ?></td>
                            <td>
                                <?php if ($h): ?>
                                    <button type="button" class="btn btn-sm" onclick="editHoraires(<?php echo htmlspecialchars(json_encode($h), ENT_QUOTES, 'UTF-8'); ?>)">Modifier</button>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet horaire ?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="delete_horaire_id" value="<?php echo (int) $h["id"]; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">Non défini</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
function editPromotion(promo) {
    document.getElementById('promotion_id').value = promo.id;
    document.getElementById('titre').value = promo.titre;
    document.getElementById('description').value = promo.description || '';
    document.getElementById('date_debut').value = promo.date_debut;
    document.getElementById('date_fin').value = promo.date_fin;
    window.scrollTo(0, 0);
}

function resetPromotionForm() {
    document.getElementById('promotion_id').value = '';
    document.getElementById('titre').value = '';
    document.getElementById('description').value = '';
    document.getElementById('date_debut').value = '';
    document.getElementById('date_fin').value = '';
}

function editHoraires(horaire) {
    document.getElementById('horaire_id').value = horaire.id;
    document.getElementById('jour_semaine').value = horaire.jour_semaine;
    document.getElementById('heure_ouverture').value = horaire.heure_ouverture ? horaire.heure_ouverture.substring(0, 5) : '';
    document.getElementById('heure_fermeture').value = horaire.heure_fermeture ? horaire.heure_fermeture.substring(0, 5) : '';
    document.getElementById('ferme').checked = horaire.ferme == 1;
    toggleHeures();
    window.scrollTo(0, 0);
}

function resetHorairesForm() {
    document.getElementById('horaire_id').value = '';
    document.getElementById('jour_semaine').value = '';
    document.getElementById('heure_ouverture').value = '';
    document.getElementById('heure_fermeture').value = '';
    document.getElementById('ferme').checked = false;
    toggleHeures();
}

function toggleHeures() {
    const ferme = document.getElementById('ferme').checked;
    document.getElementById('heure_ouverture').disabled = ferme;
    document.getElementById('heure_fermeture').disabled = ferme;
}

toggleHeures();
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>
