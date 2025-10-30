<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();

if (!is_logged_in() || !has_role("admin")) {
    redirect(BASE_URL . "backend/admin_login.php");
}

$services = [];
$errors = [];
$success = "";

// Gérer l'ajout/modification de service
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $service_id = isset($_POST["service_id"]) ? sanitize_input($_POST["service_id"]) : null;
    $nom = sanitize_input($_POST["nom"]);
    $description = sanitize_input($_POST["description"]);
    $prix_depart = sanitize_input($_POST["prix_depart"]);
    $prix_discutable = isset($_POST["prix_discutable"]) ? 1 : 0;

    if (empty($nom) || empty($prix_depart)) {
        $errors[] = "Le nom et le prix de départ sont obligatoires.";
    }

    if (empty($errors)) {
        if (empty($service_id)) { // Ajout
            $sql = "INSERT INTO services (nom, description, prix_depart, prix_discutable) VALUES (?, ?, ?, ?)";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("ssdi", $nom, $description, $prix_depart, $prix_discutable);
                if ($stmt->execute()) {
                    $success = "Service ajouté avec succès.";
                } else {
                    $errors[] = "Erreur lors de l'ajout du service: " . $stmt->error;
                }
                $stmt->close();
            }
        } else { // Modification
            $sql = "UPDATE services SET nom = ?, description = ?, prix_depart = ?, prix_discutable = ? WHERE id = ?";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("ssdii", $nom, $description, $prix_depart, $prix_discutable, $service_id);
                if ($stmt->execute()) {
                    $success = "Service modifié avec succès.";
                } else {
                    $errors[] = "Erreur lors de la modification du service: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Gérer la suppression de service
if (isset($_GET["delete"])) {
    $service_id_to_delete = sanitize_input($_GET["delete"]);
    $sql_delete = "DELETE FROM services WHERE id = ?";
    if ($stmt = $mysqli->prepare($sql_delete)) {
        $stmt->bind_param("i", $service_id_to_delete);
        if ($stmt->execute()) {
            $success = "Service supprimé avec succès.";
        } else {
            $errors[] = "Erreur lors de la suppression du service: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Récupérer tous les services
$sql_services = "SELECT * FROM services ORDER BY nom ASC";
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
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <p><?php echo $success; ?></p>
            </div>
        <?php endif; ?>

        <h2>Ajouter ou Modifier un Service</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="hidden" name="service_id" id="service_id" value="">
            <div class="form-group">
                <label for="nom">Nom du service :</label>
                <input type="text" name="nom" id="nom" required>
            </div>
            <div class="form-group">
                <label for="description">Description :</label>
                <textarea name="description" id="description" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label for="prix_depart">Prix de départ ($) :</label>
                <input type="number" name="prix_depart" id="prix_depart" step="0.01" required>
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
                        <th>Prix discutable</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($service["nom"]); ?></td>
                            <td><?php echo number_format($service["prix_depart"], 2, ',', ' '); ?> $</td>
                            <td><?php echo $service["prix_discutable"] ? 'Oui' : 'Non'; ?></td>
                            <td>
                                <button class="btn btn-sm" onclick="editService(<?php echo htmlspecialchars(json_encode($service)); ?>)">Modifier</button>
                                <a href="?delete=<?php echo $service['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce service ?');">Supprimer</a>
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
    document.getElementById('description').value = service.description;
    document.getElementById('prix_depart').value = service.prix_depart;
    document.getElementById('prix_discutable').checked = service.prix_discutable == 1;
    window.scrollTo(0, 0);
}
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>

