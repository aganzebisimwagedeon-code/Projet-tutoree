<?php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();

// Rediriger si l'utilisateur n'est pas connecté
if (!is_logged_in()) {
    redirect(BASE_URL . "login.php?redirect=" . urlencode($_SERVER["REQUEST_URI"]));
}

$user_id = $_SESSION["user_id"];
$coiffeurs = [];
$services = [];
$errors = [];
$success = "";

// Récupérer la liste des coiffeurs - CORRECTION ICI : utiliser c.id au lieu de u.id
$sql_coiffeurs = "SELECT c.id AS coiffeur_id, u.username, c.specialite 
                  FROM users u 
                  JOIN coiffeurs c ON u.id = c.user_id 
                  WHERE u.role = 'coiffeur'";
if ($result_coiffeurs = $mysqli->query($sql_coiffeurs)) {
    while ($row = $result_coiffeurs->fetch_assoc()) {
        $coiffeurs[] = $row;
    }
    $result_coiffeurs->free();
} else {
    $errors[] = "Erreur lors de la récupération des coiffeurs : " . $mysqli->error;
}

// Récupérer la liste des services
$sql_services = "SELECT id, nom, prix_depart FROM services ORDER BY nom ASC";
if ($result_services = $mysqli->query($sql_services)) {
    while ($row = $result_services->fetch_assoc()) {
        $services[] = $row;
    }
    $result_services->free();
} else {
    $errors[] = "Erreur lors de la récupération des services : " . $mysqli->error;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $coiffeur_id = sanitize_input($_POST["coiffeur_id"]);
        $service_id = sanitize_input($_POST["service_id"]);
        $date = sanitize_input($_POST["date"]);
        $time = sanitize_input($_POST["time"]);
        $type_prestation = sanitize_input($_POST["type_prestation"]);

         $adresse_domicile = null;
         $telephone = null;

        // Validation simple
        if (empty($coiffeur_id) || empty($service_id) || empty($date) || empty($time) || empty($type_prestation)) {
            $errors[] = "Tous les champs sont obligatoires.";
        }

        $date_heure = $date . " " . $time . ":00";

        if ($type_prestation == 'domicile') {
        $adresse_domicile = sanitize_input($_POST["adresse_domicile"]);
        $telephone = sanitize_input($_POST["telephone"]);

        if (empty($adresse_domicile)) {
            $errors[] = "L'adresse est obligatoire pour une prestation à domicile.";
        }
        if (empty($telephone)) {
            $errors[] = "Le téléphone est obligatoire pour une prestation à domicile.";
        }
    }

        // Vérifier que la date est dans le futur
        if (strtotime($date_heure) < time()) {
            $errors[] = "La date et l'heure du rendez-vous doivent être futures.";
        }

        if (empty($errors)) {
            $sql_insert ="INSERT INTO rendezvous (client_id, coiffeur_id, service_id, date_heure, type_prestation, adresse_domicile, telephone, statut) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
            if ($stmt = $mysqli->prepare($sql_insert)) {
                $stmt->bind_param("iiissss", $user_id, $coiffeur_id, $service_id, $date_heure, $type_prestation, $adresse_domicile, $telephone);
                if ($stmt->execute()) {
                    $success = "Votre rendez-vous a été pris avec succès et est en attente de confirmation.";
                } else {
                    $errors[] = "Erreur lors de l'exécution de la requête : " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = "Erreur lors de la préparation de la requête : " . $mysqli->error;
            }
        }
    } catch (Exception $e) {
        $errors[] = "Exception capturée : " . $e->getMessage();
    }
}

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Prendre un Rendez-vous</h1>
    </div>
</section>

<section class="appointment-form">
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

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="coiffeur_id">Choisissez votre coiffeur :</label>
                <select name="coiffeur_id" id="coiffeur_id" required>
                    <option value="">-- Sélectionnez un coiffeur --</option>
                    <?php foreach ($coiffeurs as $coiffeur): ?>
                        <option value="<?php echo $coiffeur["coiffeur_id"]; ?>"><?php echo htmlspecialchars($coiffeur["username"]); ?> (<?php echo htmlspecialchars($coiffeur["specialite"]); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="service_id">Choisissez la prestation :</label>
                <select name="service_id" id="service_id" required>
                    <option value="">-- Sélectionnez une prestation --</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?php echo $service["id"]; ?>"><?php echo htmlspecialchars($service["nom"]); ?> (<?php echo number_format($service["prix_depart"], 2, ",", " "); ?> $)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="date">Date du rendez-vous :</label>
                <input type="date" name="date" id="date" required min="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="form-group">
                <label for="time">Heure du rendez-vous :</label>
                <input type="time" name="time" id="time" required>
            </div>

            <div class="form-group">
                <label for="type_prestation">Lieu de la prestation :</label>
                <select name="type_prestation" id="type_prestation" required>
                    <option value="salon">Au salon</option>
                    <option value="domicile">À domicile</option>
                </select>
            </div>
               <!-- Après le champ type_prestation -->
                <div id="domicile-fields" style="display: none;">
                   <div class="form-group">
                       <label for="adresse_domicile">Adresse complète :</label>
                       <textarea name="adresse_domicile" id="adresse_domicile" rows="3" required></textarea>
                  </div>
                  <div class="form-group">
                       <label for="telephone">Téléphone (pour contact) :</label>
                       <input type="tel" name="telephone" id="telephone" required>
                 </div>
               </div>

            <script>
               document.getElementById('type_prestation').addEventListener('change', function() {
               const domicileFields = document.getElementById('domicile-fields');
               if (this.value === 'domicile') {
                 domicileFields.style.display = 'block';
             } else {
                 domicileFields.style.display = 'none';
             }
           });
           </script>

            <button type="submit" class="btn">Confirmer le rendez-vous</button>
        </form>
    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>