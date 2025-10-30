<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();

// Vérifier si l'utilisateur est connecté et a le rôle admin
if (!is_logged_in() || !has_role('admin')) {
    redirect(BASE_URL . "backend/admin_login.php");
}

$admin_username = $_SESSION["username"];

// Statistiques du tableau de bord
$total_appointments = 0;
$pending_appointments = 0;
$total_reviews = 0;
$pending_reviews = 0;
$total_promotions = 0;

// Total des rendez-vous
$sql_total_appointments = "SELECT COUNT(*) AS total FROM rendezvous";
if ($result = $mysqli->query($sql_total_appointments)) {
    $total_appointments = $result->fetch_assoc()["total"];
    $result->free();
}

// Rendez-vous en attente
$sql_pending_appointments = "SELECT COUNT(*) AS pending FROM rendezvous WHERE statut = 'pending'";
if ($result = $mysqli->query($sql_pending_appointments)) {
    $pending_appointments = $result->fetch_assoc()["pending"];
    $result->free();
}

// Total des avis
$sql_total_reviews = "SELECT COUNT(*) AS total FROM avis";
if ($result = $mysqli->query($sql_total_reviews)) {
    $total_reviews = $result->fetch_assoc()["total"];
    $result->free();
}

// Avis en attente de modération
$sql_pending_reviews = "SELECT COUNT(*) AS pending FROM avis WHERE statut = 'pending'";
if ($result = $mysqli->query($sql_pending_reviews)) {
    $pending_reviews = $result->fetch_assoc()["pending"];
    $result->free();
}

// Total des promotions
$sql_total_promotions = "SELECT COUNT(*) AS total FROM promotions";
if ($result = $mysqli->query($sql_total_promotions)) {
    $total_promotions = $result->fetch_assoc()["total"];
    $result->free();
}

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Tableau de Bord Administrateur</h1>
    </div>
</section>

<section class="admin-dashboard">
    <div class="container">
        <h2>Bienvenue, <?php echo htmlspecialchars($admin_username); ?> !</h2>

        <div class="dashboard-stats">
            <div class="stat-card">
                <h3>Rendez-vous Total</h3>
                <p><?php echo $total_appointments; ?></p>
            </div>
            <div class="stat-card">
                <h3>Rendez-vous en Attente</h3>
                <p><?php echo $pending_appointments; ?></p>
            </div>
            <div class="stat-card">
                <h3>Avis Total</h3>
                <p><?php echo $total_reviews; ?></p>
            </div>
            <div class="stat-card">
                <h3>Avis en Attente</h3>
                <p><?php echo $pending_reviews; ?></p>
            </div>
            <div class="stat-card">
                <h3>Promotions Actives</h3>
                <p><?php echo $total_promotions; ?></p>
            </div>
        </div>

        <div class="admin-menu">
            <h3>Gestion</h3>
            <ul>
                <li><a href="<?php echo BASE_URL; ?>backend/admin_rendezvous.php">Gérer les Rendez-vous</a></li>
                <li><a href="<?php echo BASE_URL; ?>backend/admin_services.php">Gérer les Services</a></li>
                <li><a href="<?php echo BASE_URL; ?>backend/admin_coiffeurs.php">Gérer les Coiffeurs</a></li>
                <li><a href="<?php echo BASE_URL; ?>backend/admin_plannings.php">Gérer les Plannings</a></li>
                <li><a href="<?php echo BASE_URL; ?>backend/admin_avis.php">Modérer les Avis</a></li>
                <li><a href="<?php echo BASE_URL; ?>backend/admin_promotions.php">Gérer les Promotions & Horaires</a></li>
                <li><a href="<?php echo BASE_URL; ?>backend/admin_users.php">Gérer les Utilisateurs</a></li>
            </ul>
        </div>

        <!-- Vous pouvez ajouter ici des graphiques ou des listes rapides des derniers éléments -->

    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>

