<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../services/NotificationService.php";

start_secure_session();
require_role('coiffeur');

$coiffeur_username = $_SESSION["username"] ?? "Coiffeur";
$user_id = (int) $_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["mark_notifications_read"])) {
    if (verify_csrf_token()) {
        NotificationService::markAllRead($mysqli, $user_id);
    }
}

$sql_coiffeur = "SELECT id FROM coiffeurs WHERE user_id = ? LIMIT 1";
$coiffeur_table_id = null;
if ($stmt = $mysqli->prepare($sql_coiffeur)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $coiffeur_table_id = (int) $row["id"];
    }
    $stmt->close();
}

$total_appointments = 0;
$pending_appointments = 0;
$completed_appointments = 0;

if ($coiffeur_table_id) {
    $sql_stats = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN statut = 'pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN statut = 'completed' THEN 1 ELSE 0 END) AS completed
                  FROM rendezvous
                  WHERE coiffeur_id = ?";
    if ($stmt = $mysqli->prepare($sql_stats)) {
        $stmt->bind_param("i", $coiffeur_table_id);
        if ($stmt->execute()) {
            $stats = $stmt->get_result()->fetch_assoc();
            $total_appointments = (int) ($stats["total"] ?? 0);
            $pending_appointments = (int) ($stats["pending"] ?? 0);
            $completed_appointments = (int) ($stats["completed"] ?? 0);
        }
        $stmt->close();
    }
}

$notifications = NotificationService::getUserNotifications($mysqli, $user_id, 8);

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Tableau de Bord Coiffeur</h1>
    </div>
</section>

<section class="coiffeur-dashboard">
    <div class="container">
        <div class="welcome-header">
            <h2>Bienvenue, <?php echo htmlspecialchars($coiffeur_username); ?> !</h2>
            <p>Voici votre activité aujourd'hui</p>
        </div>

        <?php if (!empty($notifications)): ?>
            <div style="background:#fff; border-left:4px solid #4361ee; padding:15px; margin-bottom:25px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0;">Notifications récentes</h3>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" style="margin:0;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="mark_notifications_read" value="1">
                        <button type="submit" class="btn btn-sm">Tout marquer comme lu</button>
                    </form>
                </div>
                <ul style="margin:10px 0 0; padding-left:20px;">
                    <?php foreach ($notifications as $notif): ?>
                        <li style="margin-bottom:6px; <?php echo empty($notif['lu']) ? 'font-weight:bold;' : 'color:#666;'; ?>">
                            <?php echo htmlspecialchars($notif['titre']); ?> — <?php echo htmlspecialchars($notif['message']); ?>
                            <small style="color:#888;">(<?php echo date('d/m/Y H:i', strtotime($notif['created_at'])); ?>)</small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="dashboard-stats">
            <div class="stat-card bg-primary">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3>Rendez-vous Total</h3>
                    <p><?php echo $total_appointments; ?></p>
                </div>
            </div>

            <div class="stat-card bg-warning">
                <div class="stat-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-content">
                    <h3>En Attente</h3>
                    <p><?php echo $pending_appointments; ?></p>
                </div>
            </div>

            <div class="stat-card bg-success">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Terminés</h3>
                    <p><?php echo $completed_appointments; ?></p>
                </div>
            </div>
        </div>

        <div class="dashboard-actions">
            <h3>Actions rapides</h3>
            <div class="action-grid">
                <a href="<?php echo BASE_URL; ?>backend/coiffeur_rendezvous.php" class="action-card">
                    <i class="fas fa-calendar-check"></i>
                    <span>Gérer mes Rendez-vous</span>
                </a>

                <a href="<?php echo BASE_URL; ?>backend/coiffeur_planning.php" class="action-card">
                    <i class="fas fa-clock"></i>
                    <span>Gérer mon Planning &amp; Absences</span>
                </a>

                <a href="<?php echo BASE_URL; ?>backend/coiffeur_profil.php" class="action-card">
                    <i class="fas fa-user"></i>
                    <span>Mon Profil</span>
                </a>
            </div>
        </div>
    </div>
</section>

<style>
.welcome-header {
    background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
}
.welcome-header h2 {
    margin: 0;
    font-size: 2rem;
}
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}
.stat-card {
    display: flex;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    color: white;
}
.stat-icon {
    background: rgba(0,0,0,0.15);
    width: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
}
.stat-content {
    flex: 1;
    padding: 20px;
}
.stat-content h3 {
    margin: 0 0 10px 0;
    font-size: 1.2rem;
}
.stat-content p {
    margin: 0;
    font-size: 2.5rem;
    font-weight: bold;
}
.bg-primary { background: #4361ee; }
.bg-warning { background: #f8961e; }
.bg-success { background: #2a9d8f; }
.dashboard-actions h3 {
    font-size: 1.5rem;
    margin-bottom: 20px;
    color: #333;
}
.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}
.action-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: white;
    border-radius: 10px;
    padding: 30px 20px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
    text-decoration: none;
    color: #333;
    border: 1px solid #eee;
}
.action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}
.action-card i {
    font-size: 3rem;
    margin-bottom: 15px;
    color: #4361ee;
}
.action-card span {
    font-size: 1.2rem;
    font-weight: 500;
}
</style>

<?php include __DIR__ . "/../includes/footer.php"; ?>
