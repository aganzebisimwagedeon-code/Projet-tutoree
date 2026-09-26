<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();
require_role('admin');

$admin_username = $_SESSION["username"] ?? "Admin";

// Statistiques du tableau de bord regroupées en une seule requête (Phase 5.3)
$total_appointments = 0;
$pending_appointments = 0;
$total_reviews = 0;
$pending_reviews = 0;
$total_promotions = 0;

$current_date = date('Y-m-d');
$sql_stats = "SELECT
                (SELECT COUNT(*) FROM rendezvous) AS total_appointments,
                (SELECT COUNT(*) FROM rendezvous WHERE statut = 'pending') AS pending_appointments,
                (SELECT COUNT(*) FROM avis) AS total_reviews,
                (SELECT COUNT(*) FROM avis WHERE statut = 'pending') AS pending_reviews,
                (SELECT COUNT(*) FROM promotions WHERE date_fin >= ?) AS total_promotions";

if ($stmt = $mysqli->prepare($sql_stats)) {
    $stmt->bind_param("s", $current_date);
    if ($stmt->execute()) {
        $stats = $stmt->get_result()->fetch_assoc();
        $total_appointments = (int) ($stats["total_appointments"] ?? 0);
        $pending_appointments = (int) ($stats["pending_appointments"] ?? 0);
        $total_reviews = (int) ($stats["total_reviews"] ?? 0);
        $pending_reviews = (int) ($stats["pending_reviews"] ?? 0);
        $total_promotions = (int) ($stats["total_promotions"] ?? 0);
    }
    $stmt->close();
}

// Derniers journaux d'envoi d'emails (Phase 7)
$recent_emails = [];
if ($resMail = $mysqli->query("SELECT destinataire_email, sujet, type_notification, statut, created_at FROM email_logs ORDER BY created_at DESC LIMIT 5")) {
    while ($rowM = $resMail->fetch_assoc()) {
        $recent_emails[] = $rowM;
    }
    $resMail->free();
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
                <li><a href="<?php echo BASE_URL; ?>backend/admin_plannings.php">Gérer les Plannings &amp; Absences</a></li>
                <li><a href="<?php echo BASE_URL; ?>backend/admin_avis.php">Modérer les Avis</a></li>
                <li><a href="<?php echo BASE_URL; ?>backend/admin_promotions.php">Gérer les Promotions &amp; Horaires</a></li>
                <li><a href="<?php echo BASE_URL; ?>backend/admin_users.php">Gérer les Utilisateurs</a></li>
            </ul>
        </div>

        <?php if (!empty($recent_emails)): ?>
            <hr style="margin:30px 0;">
            <h3>Journal récent des notifications emails</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Destinataire</th>
                            <th>Sujet</th>
                            <th>Type</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_emails as $log): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($log['destinataire_email']); ?></td>
                                <td><?php echo htmlspecialchars($log['sujet']); ?></td>
                                <td><?php echo htmlspecialchars($log['type_notification']); ?></td>
                                <td><?php echo htmlspecialchars(strtoupper($log['statut'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>
