<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();

$services = [];
$sql = "SELECT * FROM services ORDER BY nom ASC";
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
        <p class="intro-text">Découvrez l'ensemble de nos services de coiffure et de bien-être. Nos tarifs sont indiqués à titre indicatif, certains prix peuvent être ajustés en fonction de la complexité du service ou des spécificités de votre demande, après discussion avec votre coiffeur.</p>

        <div class="service-category">
            <h2>Coiffure Homme</h2>
            <div class="service-grid">
                <?php foreach ($services as $service): ?>
                    <?php if (strpos($service["nom"], "Homme") !== false || strpos($service["nom"], "Barbe") !== false): ?>
                        <div class="service-item">
                            <h3><?php echo htmlspecialchars($service["nom"]); ?></h3>
                            <p><?php echo nl2br(htmlspecialchars($service["description"])); ?></p>
                            <p class="price">À partir de <strong><?php echo number_format($service["prix_depart"], 2, ",", " "); ?> $</strong></p>
                            <?php if ($service["prix_discutable"]): ?>
                                <p class="note"><em>Prix discutable avec le coiffeur.</em></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="service-category">
            <h2>Coiffure Dame</h2>
            <div class="service-grid">
                <?php foreach ($services as $service): ?>
                    <?php if (strpos($service["nom"], "Dame") !== false || strpos($service["nom"], "Tresses") !== false || strpos($service["nom"], "Dreadlocks") !== false || strpos($service["nom"], "Maquillage") !== false || strpos($service["nom"], "tête") !== false ): ?>
                        <div class="service-item">
                            <h3><?php echo htmlspecialchars($service["nom"]); ?></h3>
                            <p><?php echo nl2br(htmlspecialchars($service["description"])); ?></p>
                            <p class="price">À partir de <strong><?php echo number_format($service["prix_depart"], 2, ",", " "); ?> $</strong></n>
                            <?php if ($service["prix_discutable"]): ?>
                                <p class="note"><em>Prix discutable avec le coiffeur.</em></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="service-category">
            <h2>Soins Esthétiques</h2>
            <div class="service-grid">
                <?php foreach ($services as $service): ?>
                    <?php if (strpos($service["nom"], "Manucure") !== false || strpos($service["nom"], "Pédicure") !== false || strpos($service["nom"], "soin") !== false || strpos($service["nom"], "Locks") !== false ): ?>
                        <div class="service-item">
                            <h3><?php echo htmlspecialchars($service["nom"]); ?></h3>
                            <p><?php echo nl2br(htmlspecialchars($service["description"])); ?></p>
                            <p class="price">À partir de <strong><?php echo number_format($service["prix_depart"], 2, ",", " "); ?> $</strong></p>
                            <?php if ($service["prix_discutable"]): ?>
                                <p class="note"><em>Prix discutable avec le coiffeur.</em></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>

