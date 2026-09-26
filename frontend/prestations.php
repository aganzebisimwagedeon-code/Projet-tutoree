<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();

$services = [];
$sql = "SELECT id, nom, description, prix_depart, COALESCE(duree_minutes, 30) AS duree_minutes, prix_discutable FROM services ORDER BY nom ASC";
if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    $result->free();
}

include __DIR__ . "/../includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Nos Prestations et Tarifs</h1>
    </div>
</section>

<section class="services-list">
    <div class="container">
        <p class="intro-text">Découvrez l'ensemble de nos services de coiffure et de bien-être. Nos tarifs et durées sont indiqués ci-dessous ; certains prix peuvent être ajustés selon la complexité de votre demande.</p>

        <div class="service-category">
            <h2>Toutes nos Prestations</h2>
            <div class="service-grid">
                <?php foreach ($services as $service): ?>
                    <div class="service-item">
                        <h3><?php echo htmlspecialchars($service["nom"]); ?></h3>
                        <p><?php echo nl2br(htmlspecialchars($service["description"] ?? '')); ?></p>
                        <p class="price">
                            À partir de <strong><?php echo number_format((float) $service["prix_depart"], 2, ",", " "); ?> $</strong>
                            <span>(Durée : <?php echo (int) $service["duree_minutes"]; ?> min)</span>
                        </p>
                        <?php if (!empty($service["prix_discutable"])): ?>
                            <p class="note"><em>Prix discutable avec le coiffeur.</em></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>
