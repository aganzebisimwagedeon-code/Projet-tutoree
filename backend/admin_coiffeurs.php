<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();
require_role("admin");

$coiffeurs = [];
$all_services = [];
$errors = [];
$success = "";

// Récupérer toutes les prestations pour l'affectation coiffeur <-> services (Phase 3.1)
if ($resS = $mysqli->query("SELECT id, nom FROM services ORDER BY nom ASC")) {
    while ($rowS = $resS->fetch_assoc()) {
        $all_services[] = $rowS;
    }
    $resS->free();
}

/**
 * Synchronise la table `coiffeur_services` pour un coiffeur donné.
 */
function sync_coiffeur_services(mysqli $mysqli, int $coiffeurId, array $serviceIds): void {
    if ($stmtDel = $mysqli->prepare("DELETE FROM coiffeur_services WHERE coiffeur_id = ?")) {
        $stmtDel->bind_param("i", $coiffeurId);
        @$stmtDel->execute();
        $stmtDel->close();
    }
    if (empty($serviceIds)) {
        return;
    }
    if ($stmtIns = $mysqli->prepare("INSERT IGNORE INTO coiffeur_services (coiffeur_id, service_id) VALUES (?, ?)")) {
        foreach ($serviceIds as $sid) {
            $sidInt = validate_int($sid, 1);
            if ($sidInt) {
                $stmtIns->bind_param("ii", $coiffeurId, $sidInt);
                @$stmtIns->execute();
            }
        }
        $stmtIns->close();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        $errors[] = "Jeton de sécurité invalide. Veuillez réessayer.";
    } elseif (isset($_POST["delete_coiffeur_id"])) {
        $coiffeur_id_to_delete = validate_int($_POST["delete_coiffeur_id"], 1);
        if (!$coiffeur_id_to_delete) {
            $errors[] = "Identifiant de coiffeur invalide.";
        } else {
            $sql_get_info = "SELECT user_id, photo FROM coiffeurs WHERE id = ? LIMIT 1";
            if ($stmt_get_info = $mysqli->prepare($sql_get_info)) {
                $stmt_get_info->bind_param("i", $coiffeur_id_to_delete);
                $stmt_get_info->execute();
                $result_info = $stmt_get_info->get_result();
                if ($result_info->num_rows === 1) {
                    $coiffeur_info = $result_info->fetch_assoc();
                    $user_id_to_delete = (int) $coiffeur_info["user_id"];
                    $photo_to_delete = $coiffeur_info["photo"];

                    $sql_delete_user = "DELETE FROM users WHERE id = ?";
                    if ($stmt_delete_user = $mysqli->prepare($sql_delete_user)) {
                        $stmt_delete_user->bind_param("i", $user_id_to_delete);
                        if ($stmt_delete_user->execute()) {
                            if ($photo_to_delete && file_exists(__DIR__ . "/../assets/images/coiffeurs/" . basename($photo_to_delete))) {
                                @unlink(__DIR__ . "/../assets/images/coiffeurs/" . basename($photo_to_delete));
                            }
                            $success = "Coiffeur supprimé avec succès.";
                        } else {
                            $errors[] = "Erreur lors de la suppression du coiffeur.";
                        }
                        $stmt_delete_user->close();
                    }
                } else {
                    $errors[] = "Coiffeur introuvable.";
                }
                $stmt_get_info->close();
            }
        }
    } else {
        $coiffeur_id = validate_int($_POST["coiffeur_id"] ?? null, 1);
        $username = sanitize_input($_POST["username"] ?? "", 100);
        $email = validate_email_address($_POST["email"] ?? "");
        $password = (string) ($_POST["password"] ?? "");
        $specialite = sanitize_input($_POST["specialite"] ?? "", 255);
        $selected_services = isset($_POST["service_ids"]) && is_array($_POST["service_ids"]) ? $_POST["service_ids"] : [];
        $photo_name = null;

        if ($username === "" || $email === null || $specialite === "") {
            $errors[] = "Le nom d'utilisateur, un email valide et la spécialité sont obligatoires.";
        }

        if ($password !== "") {
            $strength = validate_password_strength($password);
            if (!$strength['valid']) {
                $errors[] = $strength['error'];
            }
        } elseif ($coiffeur_id === null) {
            $errors[] = "Le mot de passe est obligatoire pour un nouveau coiffeur.";
        }

        // Gestion de l'upload de photo
        if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024;

            $file_type = mime_content_type($_FILES["photo"]["tmp_name"]);
            $file_extension = strtolower(pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($file_type, $allowed_types, true) || !in_array($file_extension, $allowed_extensions, true)) {
                $errors[] = "Type de fichier non autorisé. Seuls JPG, PNG, GIF et WEBP sont acceptés.";
            }
            if ($_FILES["photo"]["size"] > $max_size) {
                $errors[] = "La taille de la photo ne doit pas dépasser 5 Mo.";
            }

            if (empty($errors)) {
                $upload_dir = __DIR__ . "/../assets/images/coiffeurs/";
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0755, true);
                }
                if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
                    $errors[] = "Le répertoire de destination des photos n'est pas accessible en écriture.";
                } else {
                    $safe_filename = preg_replace('/[^a-zA-Z0-9\-\._]/', '', basename($_FILES["photo"]["name"]));
                    $photo_name = uniqid('coiffeur_', true) . "_" . $safe_filename;
                    $target_file = $upload_dir . $photo_name;

                    if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
                        $errors[] = "Erreur lors du téléchargement de la photo.";
                        $photo_name = null;
                    } else {
                        resizeImage($target_file, 500, 500);
                    }
                }
            }
        }

        if (empty($errors)) {
            if ($coiffeur_id === null) {
                $hashed_password = hash_password($password);
                $sql_user = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'coiffeur')";
                if ($stmt_user = $mysqli->prepare($sql_user)) {
                    $stmt_user->bind_param("sss", $username, $email, $hashed_password);
                    if ($stmt_user->execute()) {
                        $new_user_id = (int) $stmt_user->insert_id;
                        $sql_coiffeur = "INSERT INTO coiffeurs (user_id, specialite, photo) VALUES (?, ?, ?)";
                        if ($stmt_coiffeur = $mysqli->prepare($sql_coiffeur)) {
                            $stmt_coiffeur->bind_param("iss", $new_user_id, $specialite, $photo_name);
                            if ($stmt_coiffeur->execute()) {
                                $new_coiffeur_id = (int) $stmt_coiffeur->insert_id;
                                sync_coiffeur_services($mysqli, $new_coiffeur_id, $selected_services);
                                $success = "Coiffeur ajouté avec succès.";
                            } else {
                                $errors[] = "Erreur lors de la création du profil coiffeur.";
                                $mysqli->query("DELETE FROM users WHERE id = " . (int) $new_user_id);
                            }
                            $stmt_coiffeur->close();
                        }
                    } else {
                        $errors[] = "Erreur lors de l'ajout de l'utilisateur (email déjà utilisé ?).";
                    }
                    $stmt_user->close();
                }
            } else {
                $sql_get_user_id = "SELECT user_id, photo FROM coiffeurs WHERE id = ? LIMIT 1";
                if ($stmt_get = $mysqli->prepare($sql_get_user_id)) {
                    $stmt_get->bind_param("i", $coiffeur_id);
                    $stmt_get->execute();
                    $coiffeur_data = $stmt_get->get_result()->fetch_assoc();
                    $stmt_get->close();

                    if ($coiffeur_data) {
                        $user_id_to_update = (int) $coiffeur_data["user_id"];
                        $old_photo_name = $coiffeur_data["photo"];

                        $sql_user_update = "UPDATE users SET username = ?, email = ?";
                        $params = [$username, $email];
                        $types = "ss";
                        if ($password !== "") {
                            $sql_user_update .= ", password = ?";
                            $params[] = hash_password($password);
                            $types .= "s";
                        }
                        $sql_user_update .= " WHERE id = ?";
                        $params[] = $user_id_to_update;
                        $types .= "i";

                        if ($stmt_u = $mysqli->prepare($sql_user_update)) {
                            $stmt_u->bind_param($types, ...$params);
                            if ($stmt_u->execute()) {
                                $sql_c_upd = "UPDATE coiffeurs SET specialite = ?";
                                $c_params = [$specialite];
                                $c_types = "s";
                                if ($photo_name) {
                                    $sql_c_upd .= ", photo = ?";
                                    $c_params[] = $photo_name;
                                    $c_types .= "s";
                                }
                                $sql_c_upd .= " WHERE id = ?";
                                $c_params[] = $coiffeur_id;
                                $c_types .= "i";

                                if ($stmt_c = $mysqli->prepare($sql_c_upd)) {
                                    $stmt_c->bind_param($c_types, ...$c_params);
                                    if ($stmt_c->execute()) {
                                        if ($photo_name && $old_photo_name && file_exists(__DIR__ . "/../assets/images/coiffeurs/" . basename($old_photo_name))) {
                                            @unlink(__DIR__ . "/../assets/images/coiffeurs/" . basename($old_photo_name));
                                        }
                                        sync_coiffeur_services($mysqli, $coiffeur_id, $selected_services);
                                        $success = "Coiffeur modifié avec succès.";
                                    } else {
                                        $errors[] = "Erreur lors de la mise à jour du profil coiffeur.";
                                    }
                                    $stmt_c->close();
                                }
                            } else {
                                $errors[] = "Erreur lors de la mise à jour de l'utilisateur.";
                            }
                            $stmt_u->close();
                        }
                    }
                }
            }
        }
    }
}

// Récupérer tous les coiffeurs et leurs services associés
$sql_coiffeurs = "SELECT u.id AS user_id, u.username, u.email, c.id AS coiffeur_id, c.specialite, c.photo
                  FROM users u
                  JOIN coiffeurs c ON u.id = c.user_id
                  WHERE u.role = 'coiffeur'
                  ORDER BY u.username ASC";
if ($result = $mysqli->query($sql_coiffeurs)) {
    while ($row = $result->fetch_assoc()) {
        $row['service_ids'] = [];
        $coiffeurs[$row['coiffeur_id']] = $row;
    }
    $result->free();
}

if (!empty($coiffeurs)) {
    if ($resCs = $mysqli->query("SELECT coiffeur_id, service_id FROM coiffeur_services")) {
        while ($csRow = $resCs->fetch_assoc()) {
            $cid = (int) $csRow['coiffeur_id'];
            if (isset($coiffeurs[$cid])) {
                $coiffeurs[$cid]['service_ids'][] = (int) $csRow['service_id'];
            }
        }
        $resCs->free();
    }
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

        <h2>Ajouter ou Modifier un Coiffeur</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="coiffeur_id" id="coiffeur_id" value="">
            <div class="form-group">
                <label for="username">Nom d'utilisateur :</label>
                <input type="text" name="username" id="username" maxlength="100" required class="form-control">
            </div>
            <div class="form-group">
                <label for="email">Email :</label>
                <input type="email" name="email" id="email" maxlength="255" required class="form-control">
            </div>
            <div class="form-group">
                <label for="password">Mot de passe (laisser vide pour ne pas changer) :</label>
                <input type="password" name="password" id="password" minlength="8" class="form-control">
                <small class="form-text text-muted">Minimum 8 caractères avec lettres et chiffres</small>
            </div>
            <div class="form-group">
                <label for="specialite">Résumé des spécialités :</label>
                <input type="text" name="specialite" id="specialite" maxlength="255" required class="form-control">
            </div>
            <div class="form-group">
                <label>Prestations réalisées par ce coiffeur :</label>
                <div style="display:flex; flex-wrap:wrap; gap:12px; margin-top:6px;">
                    <?php foreach ($all_services as $srv): ?>
                        <label style="font-weight:normal;">
                            <input type="checkbox" name="service_ids[]" class="coiffeur-service-cb" value="<?php echo (int) $srv['id']; ?>">
                            <?php echo htmlspecialchars($srv['nom']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
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
                                    <?php if (!empty($coiffeur["photo"])): ?>
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
                                    <button type="button" class="btn btn-sm btn-warning" onclick="editCoiffeur(<?php echo htmlspecialchars(json_encode($coiffeur), ENT_QUOTES, 'UTF-8'); ?>)">
                                        Modifier
                                    </button>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce coiffeur et ses données associées ?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="delete_coiffeur_id" value="<?php echo (int) $coiffeur["coiffeur_id"]; ?>">
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
function editCoiffeur(coiffeur) {
    document.getElementById('coiffeur_id').value = coiffeur.coiffeur_id;
    document.getElementById('username').value = coiffeur.username;
    document.getElementById('email').value = coiffeur.email;
    document.getElementById('specialite').value = coiffeur.specialite || '';
    document.getElementById('password').value = '';

    const assignedIds = Array.isArray(coiffeur.service_ids) ? coiffeur.service_ids.map(Number) : [];
    document.querySelectorAll('.coiffeur-service-cb').forEach(cb => {
        cb.checked = assignedIds.includes(parseInt(cb.value, 10));
    });

    const currentPhotoDisplay = document.getElementById('current_photo_display');
    if (coiffeur.photo) {
        currentPhotoDisplay.innerHTML = '<strong>Photo actuelle :</strong><br><img src="<?php echo BASE_URL; ?>assets/images/coiffeurs/' + encodeURIComponent(coiffeur.photo) + '" alt="Photo actuelle" width="100">';
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
    document.querySelectorAll('.coiffeur-service-cb').forEach(cb => { cb.checked = false; });
    document.getElementById('current_photo_display').innerHTML = '';
}
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>
