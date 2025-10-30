<?php
// Activer le rapport d'erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

// Démarrer le buffer pour capturer les erreurs
ob_start();

start_secure_session();

if (!is_logged_in() || !has_role("admin")) {
    redirect(BASE_URL . "backend/admin_login.php");
    exit;
}

$coiffeurs = [];
$errors = [];
$success = "";

// Gérer l'ajout/modification de coiffeur
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $coiffeur_id = isset($_POST["coiffeur_id"]) ? sanitize_input($_POST["coiffeur_id"]) : null;
    $username = sanitize_input($_POST["username"]);
    $email = sanitize_input($_POST["email"]);
    $password = sanitize_input($_POST["password"]);
    $specialite = sanitize_input($_POST["specialite"]);
    $photo_name = null;

    // Validation
    if (empty($username) || empty($email) || empty($specialite)) {
        $errors[] = "Le nom d'utilisateur, l'email et la spécialité sont obligatoires.";
    }

    // Gestion de l'upload de photo
    if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] == UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5 MB

        $file_type = mime_content_type($_FILES["photo"]["tmp_name"]);
        $file_extension = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($file_type, $allowed_types) || !in_array(strtolower($file_extension), $allowed_extensions)) {
            $errors[] = "Type de fichier non autorisé pour la photo. Seuls JPG, PNG, GIF et WEBP sont acceptés.";
        }
        if ($_FILES["photo"]["size"] > $max_size) {
            $errors[] = "La taille de la photo ne doit pas dépasser 5 Mo.";
        }

        if (empty($errors)) {
            $upload_dir = __DIR__ . "/../assets/images/coiffeurs/";
            
            // Créer le répertoire s'il n'existe pas
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    $errors[] = "Impossible de créer le répertoire de destination pour les photos.";
                }
            }
            
            // Vérifier les permissions
            if (is_dir($upload_dir) && !is_writable($upload_dir)) {
                $errors[] = "Le répertoire de destination n'est pas accessible en écriture.";
            }

            if (empty($errors)) {
                $safe_filename = preg_replace('/[^a-zA-Z0-9\-\._]/', '', $_FILES["photo"]["name"]);
                $photo_name = uniqid() . "_" . $safe_filename;
                $target_file = $upload_dir . $photo_name;
                
                if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
                    $errors[] = "Erreur lors de l'upload de la photo. Vérifiez les permissions du répertoire.";
                    $photo_name = null;
                } else {
                    // Réduire la taille de l'image si nécessaire
                    resizeImage($target_file, 500, 500);
                }
            }
        }
    }

    if (empty($errors)) {
        if (empty($coiffeur_id)) { // Ajout d'un nouveau coiffeur
            if (empty($password)) {
                $errors[] = "Le mot de passe est obligatoire pour un nouvel utilisateur.";
            }
            if (empty($errors)) {
                $hashed_password = hash_password($password);
                // Insérer dans la table users
                $sql_user = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'coiffeur')";
                if ($stmt_user = $mysqli->prepare($sql_user)) {
                    $stmt_user->bind_param("sss", $username, $email, $hashed_password);
                    if ($stmt_user->execute()) {
                        $new_user_id = $stmt_user->insert_id;
                        // Insérer dans la table coiffeurs
                        $sql_coiffeur = "INSERT INTO coiffeurs (user_id, specialite, photo) VALUES (?, ?, ?)";
                        if ($stmt_coiffeur = $mysqli->prepare($sql_coiffeur)) {
                            $stmt_coiffeur->bind_param("iss", $new_user_id, $specialite, $photo_name);
                            if ($stmt_coiffeur->execute()) {
                                $success = "Coiffeur ajouté avec succès.";
                            } else {
                                $errors[] = "Erreur lors de l'ajout du coiffeur: " . $stmt_coiffeur->error;
                                // Rollback user creation if coiffeur creation fails
                                $mysqli->query("DELETE FROM users WHERE id = " . $new_user_id);
                            }
                            $stmt_coiffeur->close();
                        } else {
                            $errors[] = "Erreur de préparation de la requête coiffeur: " . $mysqli->error;
                        }
                    } else {
                        $errors[] = "Erreur lors de l'ajout de l'utilisateur: " . $stmt_user->error;
                    }
                    $stmt_user->close();
                } else {
                    $errors[] = "Erreur de préparation de la requête utilisateur: " . $mysqli->error;
                }
            }
        } else { // Modification d'un coiffeur existant
            // Récupérer l'ID utilisateur associé au coiffeur
            $sql_get_user_id = "SELECT user_id, photo FROM coiffeurs WHERE id = ?";
            $stmt_get_user_id = $mysqli->prepare($sql_get_user_id);
            $stmt_get_user_id->bind_param("i", $coiffeur_id);
            $stmt_get_user_id->execute();
            $result_get_user_id = $stmt_get_user_id->get_result();
            $coiffeur_data = $result_get_user_id->fetch_assoc();
            $user_id_to_update = $coiffeur_data["user_id"];
            $old_photo_name = $coiffeur_data["photo"];
            $stmt_get_user_id->close();

            // Mettre à jour la table users
            $sql_user_update = "UPDATE users SET username = ?, email = ?";
            $params = [$username, $email];
            $types = "ss";
            
            if (!empty($password)) {
                $hashed_password = hash_password($password);
                $sql_user_update .= ", password = ?";
                $params[] = $hashed_password;
                $types .= "s";
            }
            
            $sql_user_update .= " WHERE id = ?";
            $params[] = $user_id_to_update;
            $types .= "i";

            if ($stmt_user_update = $mysqli->prepare($sql_user_update)) {
                $stmt_user_update->bind_param($types, ...$params);
                if ($stmt_user_update->execute()) {
                    // Mettre à jour la table coiffeurs
                    $sql_coiffeur_update = "UPDATE coiffeurs SET specialite = ?";
                    $params_coiffeur = [$specialite];
                    $types_coiffeur = "s";
                    
                    if ($photo_name) {
                        $sql_coiffeur_update .= ", photo = ?";
                        $params_coiffeur[] = $photo_name;
                        $types_coiffeur .= "s";
                    }
                    
                    $sql_coiffeur_update .= " WHERE id = ?";
                    $params_coiffeur[] = $coiffeur_id;
                    $types_coiffeur .= "i";

                    if ($stmt_coiffeur_update = $mysqli->prepare($sql_coiffeur_update)) {
                        $stmt_coiffeur_update->bind_param($types_coiffeur, ...$params_coiffeur);
                        if ($stmt_coiffeur_update->execute()) {
                            // Supprimer l'ancienne photo si une nouvelle a été uploadée
                            if ($photo_name && $old_photo_name && file_exists(__DIR__ . "/../assets/images/coiffeurs/" . $old_photo_name)) {
                                unlink(__DIR__ . "/../assets/images/coiffeurs/" . $old_photo_name);
                            }
                            $success = "Coiffeur modifié avec succès.";
                        } else {
                            $errors[] = "Erreur lors de la modification du coiffeur: " . $stmt_coiffeur_update->error;
                        }
                        $stmt_coiffeur_update->close();
                    } else {
                        $errors[] = "Erreur de préparation de la requête coiffeur: " . $mysqli->error;
                    }
                } else {
                    $errors[] = "Erreur lors de la modification de l'utilisateur: " . $stmt_user_update->error;
                }
                $stmt_user_update->close();
            } else {
                $errors[] = "Erreur de préparation de la requête utilisateur: " . $mysqli->error;
            }
        }
    }
}

// Gérer la suppression de coiffeur
if (isset($_GET["delete"])) {
    $coiffeur_id_to_delete = sanitize_input($_GET["delete"]);

    // Récupérer l'ID utilisateur et le nom de la photo avant de supprimer
    $sql_get_info = "SELECT user_id, photo FROM coiffeurs WHERE id = ?";
    if ($stmt_get_info = $mysqli->prepare($sql_get_info)) {
        $stmt_get_info->bind_param("i", $coiffeur_id_to_delete);
        $stmt_get_info->execute();
        $result_info = $stmt_get_info->get_result();
        if ($result_info->num_rows == 1) {
            $coiffeur_info = $result_info->fetch_assoc();
            $user_id_to_delete = $coiffeur_info["user_id"];
            $photo_to_delete = $coiffeur_info["photo"];

            // Supprimer d'abord de la table coiffeurs
            $sql_delete_coiffeur = "DELETE FROM coiffeurs WHERE id = ?";
            if ($stmt_delete_coiffeur = $mysqli->prepare($sql_delete_coiffeur)) {
                $stmt_delete_coiffeur->bind_param("i", $coiffeur_id_to_delete);
                if ($stmt_delete_coiffeur->execute()) {
                    // Supprimer l'utilisateur associé
                    $sql_delete_user = "DELETE FROM users WHERE id = ?";
                    if ($stmt_delete_user = $mysqli->prepare($sql_delete_user)) {
                        $stmt_delete_user->bind_param("i", $user_id_to_delete);
                        if ($stmt_delete_user->execute()) {
                            // Supprimer la photo si elle existe
                            if ($photo_to_delete && file_exists(__DIR__ . "/../assets/images/coiffeurs/" . $photo_to_delete)) {
                                unlink(__DIR__ . "/../assets/images/coiffeurs/" . $photo_to_delete);
                            }
                            $success = "Coiffeur supprimé avec succès.";
                        } else {
                            $errors[] = "Erreur lors de la suppression de l'utilisateur associé: " . $stmt_delete_user->error;
                        }
                        $stmt_delete_user->close();
                    } else {
                        $errors[] = "Erreur de préparation de la requête de suppression d'utilisateur: " . $mysqli->error;
                    }
                } else {
                    $errors[] = "Erreur lors de la suppression du coiffeur: " . $stmt_delete_coiffeur->error;
                }
                $stmt_delete_coiffeur->close();
            } else {
                $errors[] = "Erreur de préparation de la requête de suppression de coiffeur: " . $mysqli->error;
            }
        } else {
            $errors[] = "Coiffeur introuvable.";
        }
        $stmt_get_info->close();
    } else {
        $errors[] = "Erreur de préparation de la requête d'information: " . $mysqli->error;
    }
}

// Récupérer tous les coiffeurs
$sql_coiffeurs = "SELECT u.id AS user_id, u.username, u.email, c.id AS coiffeur_id, c.specialite, c.photo 
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

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Gérer les Coiffeurs</h1>
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

        <h2>Ajouter ou Modifier un Coiffeur</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            <input type="hidden" name="coiffeur_id" id="coiffeur_id" value="">
            <div class="form-group">
                <label for="username">Nom d'utilisateur :</label>
                <input type="text" name="username" id="username" required class="form-control">
            </div>
            <div class="form-group">
                <label for="email">Email :</label>
                <input type="email" name="email" id="email" required class="form-control">
            </div>
            <div class="form-group">
                <label for="password">Mot de passe (laisser vide pour ne pas changer) :</label>
                <input type="password" name="password" id="password" class="form-control">
                <small class="form-text text-muted">Minimum 8 caractères</small>
            </div>
            <div class="form-group">
                <label for="specialite">Spécialité :</label>
                <input type="text" name="specialite" id="specialite" required class="form-control">
            </div>
            <div class="form-group">
                <label for="photo">Photo de profil :</label>
                <input type="file" name="photo" id="photo" accept="image/*" class="form-control-file">
                <small class="form-text text-muted">Formats acceptés: JPG, PNG, GIF, WEBP (max 5 Mo)</small>
                <div id="current_photo_display" class="mt-2"></div>
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <button type="button" class="btn btn-secondary" onclick="resetCoiffeurForm();">Nouveau</button>
        </form>

        <hr>

        <h2>Liste des Coiffeurs</h2>
        <?php if (empty($coiffeurs)): ?>
            <div class="alert alert-info">
                <p>Aucun coiffeur enregistré pour le moment.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead class="thead-dark">
                        <tr>
                            <th>Photo</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Spécialité</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coiffeurs as $coiffeur): ?>
                            <tr>
                                <td>
                                    <?php if ($coiffeur["photo"]): ?>
                                        <img src="<?php echo BASE_URL; ?>assets/images/coiffeurs/<?php echo htmlspecialchars($coiffeur["photo"]); ?>" 
                                             alt="Photo de <?php echo htmlspecialchars($coiffeur["username"]); ?>" 
                                             class="img-thumbnail" width="80">
                                    <?php else: ?>
                                        <span class="text-muted">Aucune photo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($coiffeur["username"]); ?></td>
                                <td><?php echo htmlspecialchars($coiffeur["email"]); ?></td>
                                <td><?php echo htmlspecialchars($coiffeur["specialite"]); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editCoiffeur(<?php echo htmlspecialchars(json_encode($coiffeur)); ?>)">
                                        <i class="fas fa-edit"></i> Modifier
                                    </button>
                                    <a href="?delete=<?php echo $coiffeur["coiffeur_id"]; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce coiffeur et toutes ses données associées ?');">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </a>
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
function editCoiffeur(coiffeur) {
    document.getElementById('coiffeur_id').value = coiffeur.coiffeur_id;
    document.getElementById('username').value = coiffeur.username;
    document.getElementById('email').value = coiffeur.email;
    document.getElementById('specialite').value = coiffeur.specialite;
    document.getElementById('password').value = '';
    
    const currentPhotoDisplay = document.getElementById('current_photo_display');
    if (coiffeur.photo) {
        currentPhotoDisplay.innerHTML = `
            <strong>Photo actuelle :</strong><br>
            <img src="<?php echo BASE_URL; ?>assets/images/coiffeurs/${coiffeur.photo}" 
                 alt="Photo actuelle" 
                 class="img-thumbnail" 
                 width="100">`;
    } else {
        currentPhotoDisplay.innerHTML = '<strong>Aucune photo actuelle</strong>';
    }
    window.scrollTo(0, 0);
}

function resetCoiffeurForm() {
    document.getElementById('coiffeur_id').value = '';
    document.getElementById('username').value = '';
    document.getElementById('email').value = '';
    document.getElementById('password').value = '';
    document.getElementById('specialite').value = '';
    document.getElementById('photo').value = '';
    document.getElementById('current_photo_display').innerHTML = '';
}
</script>

<?php 
include __DIR__ . "/../includes/footer.php";

// Vider le buffer et afficher
ob_end_flush();