<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();
require_role("admin");

$services = [];
$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        $errors[] = "Jeton de sécurité invalide. Veuillez réessayer.";
    } elseif (isset($_POST["delete_service_id"])) {
        $service_id_to_delete = validate_int($_POST["delete_service_id"], 1);
        if (!$service_id_to_delete) {
            $errors[] = "Identifiant de service invalide.";
        } else {
            $sql_delete = "DELETE FROM services WHERE id = ?";
            if ($stmt = $mysqli->prepare($sql_delete)) {
                $stmt->bind_param("i", $service_id_to_delete);
                if ($stmt->execute()) {
                    $success = "Service supprimé avec succès.";
                } else {
                    $errors[] = "Erreur lors de la suppression du service.";
                }
                $stmt->close();
            }
        }
    } else {
        $service_id = validate_int($_POST["service_id"] ?? null, 1);
        $nom = sanitize_input($_POST["nom"] ?? "", 255);
        $description = sanitize_input($_POST["description"] ?? "", 2000);
        $prix_depart = filter_var($_POST["prix_depart"] ?? null, FILTER_VALIDATE_FLOAT);
        $duree_minutes = validate_int($_POST["duree_minutes"] ?? 30, 5, 480) ?? 30;
        $prix_discutable = isset($_POST["prix_discutable"]) ? 1 : 0;

        if ($nom === "" || $prix_depart === false || $prix_depart < 0) {
            $errors[] = "Le nom, un prix de départ valide (>= 0) et la durée sont obligatoires.";
        }

        if (empty($errors)) {
            if ($service_id === null) {
                $sql = "INSERT INTO services (nom, description, prix_depart, duree_minutes, prix_discutable) VALUES (?, ?, ?, ?, ?)";
                if ($stmt = $mysqli->prepare($sql)) {
                    $stmt->bind_param("ssdii", $nom, $description, $prix_depart, $duree_minutes, $prix_discutable);
                    if ($stmt->execute()) {
                        $success = "Service ajouté avec succès.";
                    } else {
                        $errors[] = "Erreur lors de l'ajout du service.";
                    }
                    $stmt->close();
                }
            } else {
                $sql = "UPDATE services SET nom = ?, description = ?, prix_depart = ?, duree_minutes = ?, prix_discutable = ? WHERE id = ?";
                if ($stmt = $mysqli->prepare($sql)) {
                    $stmt->bind_param("ssdiii", $nom, $description, $prix_depart, $duree_minutes, $prix_discutable, $service_id);
                    if ($stmt->execute()) {
                        $success = "Service modifié avec succès.";
                    } else {
                        $errors[] = "Erreur lors de la modification du service.";
                    }
                    $stmt->close();
                }
            }
        }
    }
}

$sql_services = "SELECT id, nom, description, prix_depart, COALESCE(duree_minutes, 30) AS duree_minutes, prix_discutable FROM services ORDER BY nom ASC";
if ($result = $mysqli->query($sql_services)) {
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    $result->free();
}

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Gérer les Services</h1>
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

        <h2>Ajouter ou Modifier un Service</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="service_id" id="service_id" value="">
            <div class="form-group">
                <label for="nom">Nom du service :</label>
                <input type="text" name="nom" id="nom" maxlength="255" required>
            </div>
            <div class="form-group">
                <label for="description">Description :</label>
                <textarea name="description" id="description" rows="3" maxlength="2000"></textarea>
            </div>
            <div class="form-group">
                <label for="prix_depart">Prix de départ ($) :</label>
                <input type="number" name="prix_depart" id="prix_depart" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label for="duree_minutes">Durée de la prestation (en minutes) :</label>
                <input type="number" name="duree_minutes" id="duree_minutes" min="5" max="480" step="5" value="30" required>
            </div>
            <div class="form-group">
                <input type="checkbox" name="prix_discutable" id="prix_discutable" value="1">
                <label for="prix_discutable">Prix discutable</label>
            </div>
            <button type="submit" class="btn">Enregistrer</button>
            <button type="reset" class="btn btn-secondary" onclick="document.getElementById('service_id').value = '';">Nouveau</button>
        </form>

        <hr>

        <h2>Liste des Services</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Prix de départ</th>
                        <th>Durée</th>
                        <th>Prix discutable</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($service["nom"]); ?></td>
                            <td><?php echo number_format((float) $service["prix_depart"], 2, ',', ' '); ?> $</td>
                            <td><?php echo (int) $service["duree_minutes"]; ?> min</td>
                            <td><?php echo $service["prix_discutable"] ? 'Oui' : 'Non'; ?></td>
                            <td>
                                <button type="button" class="btn btn-sm" onclick="editService(<?php echo htmlspecialchars(json_encode($service), ENT_QUOTES, 'UTF-8'); ?>)">Modifier</button>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce service ?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="delete_service_id" value="<?php echo (int) $service['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
function editService(service) {
    document.getElementById('service_id').value = service.id;
    document.getElementById('nom').value = service.nom;
    document.getElementById('description').value = service.description || '';
    document.getElementById('prix_depart').value = service.prix_depart;
    document.getElementById('duree_minutes').value = service.duree_minutes || 30;
    document.getElementById('prix_discutable').checked = service.prix_discutable == 1;
    window.scrollTo(0, 0);
}
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>
