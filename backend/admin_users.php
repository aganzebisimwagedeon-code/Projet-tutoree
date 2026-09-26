<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();
require_role("admin");

$users = [];
$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        $errors[] = "Jeton de sécurité invalide. Veuillez réessayer.";
    } elseif (isset($_POST["delete_user_id"])) {
        $user_id_to_delete = validate_int($_POST["delete_user_id"], 1);
        if (!$user_id_to_delete) {
            $errors[] = "Identifiant d'utilisateur invalide.";
        } elseif ($user_id_to_delete === (int) $_SESSION["user_id"]) {
            $errors[] = "Vous ne pouvez pas supprimer votre propre compte.";
        } else {
            $sql_delete = "DELETE FROM users WHERE id = ?";
            if ($stmt = $mysqli->prepare($sql_delete)) {
                $stmt->bind_param("i", $user_id_to_delete);
                if ($stmt->execute()) {
                    $success = "Utilisateur supprimé avec succès.";
                } else {
                    $errors[] = "Erreur lors de la suppression de l'utilisateur.";
                }
                $stmt->close();
            }
        }
    } else {
        $user_id = validate_int($_POST["user_id"] ?? null, 1);
        $username = sanitize_input($_POST["username"] ?? "", 100);
        $email = validate_email_address($_POST["email"] ?? "");
        $password = (string) ($_POST["password"] ?? "");
        $role = sanitize_input($_POST["role"] ?? "", 20);

        if ($username === "" || $email === null || !in_array($role, ['client', 'coiffeur', 'admin'], true)) {
            $errors[] = "Un nom d'utilisateur, un email valide et un rôle valide sont obligatoires.";
        }

        if ($password !== "") {
            $strength = validate_password_strength($password);
            if (!$strength['valid']) {
                $errors[] = $strength['error'];
            }
        } elseif ($user_id === null) {
            $errors[] = "Le mot de passe est obligatoire pour un nouvel utilisateur.";
        }

        if (empty($errors)) {
            if ($user_id === null) {
                $hashed_password = hash_password($password);
                $sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
                if ($stmt = $mysqli->prepare($sql)) {
                    $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
                    if ($stmt->execute()) {
                        $success = "Utilisateur ajouté avec succès.";
                    } else {
                        $errors[] = "Erreur lors de l'ajout de l'utilisateur (email déjà utilisé ?).";
                    }
                    $stmt->close();
                }
            } else {
                $sql = "UPDATE users SET username = ?, email = ?, role = ?";
                if ($password !== "") {
                    $hashed_password = hash_password($password);
                    $sql .= ", password = ?";
                }
                $sql .= " WHERE id = ?";

                if ($stmt = $mysqli->prepare($sql)) {
                    if ($password !== "") {
                        $stmt->bind_param("ssssi", $username, $email, $role, $hashed_password, $user_id);
                    } else {
                        $stmt->bind_param("sssi", $username, $email, $role, $user_id);
                    }
                    if ($stmt->execute()) {
                        $success = "Utilisateur modifié avec succès.";
                    } else {
                        $errors[] = "Erreur lors de la modification de l'utilisateur.";
                    }
                    $stmt->close();
                }
            }
        }
    }
}

$sql_users = "SELECT id, username, email, role FROM users ORDER BY username ASC";
if ($result = $mysqli->query($sql_users)) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $result->free();
}

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Gérer les Utilisateurs</h1>
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

        <h2>Ajouter ou Modifier un Utilisateur</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="user_id" id="user_id" value="">
            <div class="form-group">
                <label for="username">Nom d'utilisateur :</label>
                <input type="text" name="username" id="username" maxlength="100" required>
            </div>
            <div class="form-group">
                <label for="email">Email :</label>
                <input type="email" name="email" id="email" maxlength="255" required>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe (min. 8 caractères, lettres et chiffres ; laisser vide pour ne pas changer) :</label>
                <input type="password" name="password" id="password" minlength="8">
            </div>
            <div class="form-group">
                <label for="role">Rôle :</label>
                <select name="role" id="role" required>
                    <option value="client">Client</option>
                    <option value="coiffeur">Coiffeur</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" class="btn">Enregistrer</button>
            <button type="reset" class="btn btn-secondary" onclick="resetUserForm();">Nouveau</button>
        </form>

        <hr>

        <h2>Liste des Utilisateurs</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user["username"]); ?></td>
                            <td><?php echo htmlspecialchars($user["email"]); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($user["role"])); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm" onclick="editUser(<?php echo htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8'); ?>)">Modifier</button>
                                <?php if ((int) $user["id"] !== (int) $_SESSION["user_id"]): ?>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="delete_user_id" value="<?php echo (int) $user["id"]; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                                    </form>
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
function editUser(user) {
    document.getElementById('user_id').value = user.id;
    document.getElementById('username').value = user.username;
    document.getElementById('email').value = user.email;
    document.getElementById('password').value = '';
    document.getElementById('role').value = user.role;
    window.scrollTo(0, 0);
}

function resetUserForm() {
    document.getElementById('user_id').value = '';
    document.getElementById('username').value = '';
    document.getElementById('email').value = '';
    document.getElementById('password').value = '';
    document.getElementById('role').value = 'client';
}
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>
