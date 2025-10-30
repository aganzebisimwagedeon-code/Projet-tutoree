<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/functions.php";

start_secure_session();

// Récupérer les promotions en cours
$promotions = [];
$current_date = date("Y-m-d");
$sql = "SELECT * FROM promotions WHERE date_debut <= ? AND date_fin >= ? ORDER BY date_debut DESC";
if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("ss", $current_date, $current_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $promotions[] = $row;
    }
    $stmt->close();
}

include __DIR__ . "/includes/header.php";
?>

<section class="hero">
    <div class="container">
        <h1>Bienvenue chez King and Qween</h1>
        <p>Votre salon de coiffure mixte pour hommes et femmes, où l'élégance rencontre le style.</p>
        <a href="<?php echo BASE_URL; ?>frontend/rendezvous.php" class="btn">Prenez rendez-vous</a>
    </div>
</section>

<section class="about-us">
    <div class="container">
        <h2>Notre Histoire</h2>
        <p>Fondé le <strong>23 juillet 2015</strong>, King and Qween est né d'une passion pour la coiffure et le bien-être. Depuis nos débuts, nous nous engageons à offrir des services de qualité supérieure, dans une ambiance chaleureuse et accueillante. Nous croyons que chaque coupe, chaque couleur, chaque soin est une œuvre d'art qui révèle la personnalité de nos clients.</p>
        <p>Au fil des ans, nous avons grandi, mais notre engagement envers l'excellence et la satisfaction client est resté le même. Venez découvrir l'expérience King and Qween, où tradition et modernité se rencontrent pour sublimer votre style.</p>
    </div>
</section>

<?php if (!empty($promotions)): ?>
<section class="promotions">
    <div class="container">
        <h2>Nos Promotions en Cours</h2>
        <div class="promotion-list">
            <?php foreach ($promotions as $promo): ?>
                <div class="promotion-item">
                    <h3><?php echo htmlspecialchars($promo["titre"]); ?></h3>
                    <p><?php echo nl2br(htmlspecialchars($promo["description"])); ?></p>
                    <p class="date">Valable du <?php echo date("d/m/Y", strtotime($promo["date_debut"])); ?> au <?php echo date("d/m/Y", strtotime($promo["date_fin"])); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="services-preview">
    <div class="container">
        <h2>Nos Services</h2>
        <div class="service-grid">
            <div class="service-card">
                <h3>Coiffure Homme</h3>
                <p>Coupes modernes et classiques, taille de barbe.</p>
            </div>
            <div class="service-card">
                <h3>Coiffure Femme</h3>
                <p>Coupes, couleurs, mèches, balayages, coiffures de soirée.</p>
            </div>
            <div class="service-card">
                <h3>Manucure & Pédicure</h3>
                <p>Soins des mains et des pieds, pose de vernis.</p>
            </div>
            <div class="service-card">
                <h3>Soins Spécifiques</h3>
                <p>Dreadlocks, tresses, soins du visage.</p>
            </div>
        </div>
        <a href="<?php echo BASE_URL; ?>frontend/prestations.php" class="btn">Voir toutes les prestations</a>
    </div>
</section>

<section class="team">
    <div class="container">
        <h2>Notre Équipe</h2>
        <div class="team-grid">
            <?php
            // Récupérer les coiffeurs avec leurs informations
            $sql_team = "SELECT u.username, c.specialite, c.photo 
                         FROM users u 
                         JOIN coiffeurs c ON u.id = c.user_id 
                         WHERE u.role = 'coiffeur' 
                         ORDER BY u.username ASC";
            if ($result_team = $mysqli->query($sql_team)) {
                while ($member = $result_team->fetch_assoc()): ?>
                    <div class="team-member">
                        <div class="member-photo">
                            <?php if ($member["photo"]): ?>
                                <img src="<?php echo BASE_URL; ?>assets/images/coiffeurs/<?php echo htmlspecialchars($member["photo"]); ?>" alt="Photo de <?php echo htmlspecialchars($member["username"]); ?>">
                            <?php else: ?>
                                <div class="no-photo">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h3><?php echo htmlspecialchars($member["username"]); ?></h3>
                        <p class="specialite"><?php echo htmlspecialchars($member["specialite"]); ?></p>
                    </div>
                <?php endwhile;
                $result_team->free();
            }
            ?>
        </div>
    </div>
</section>


<?php include __DIR__ . "/includes/footer.php"; ?>

