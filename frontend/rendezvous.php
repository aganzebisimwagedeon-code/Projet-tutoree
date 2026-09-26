<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../services/BookingService.php";

start_secure_session();

// Endpoint AJAX pour récupérer les créneaux réellement disponibles (Phase 6)
if (isset($_GET['ajax_slots']) && $_GET['ajax_slots'] === '1') {
    header('Content-Type: application/json; charset=UTF-8');
    $cid = validate_int($_GET['coiffeur_id'] ?? null, 1);
    $sid = validate_int($_GET['service_id'] ?? null, 1);
    $dt = sanitize_input($_GET['date'] ?? '', 20);
    if (!$cid || !$sid || $dt === '') {
        echo json_encode(['available_slots' => [], 'message' => 'Veuillez sélectionner un coiffeur, une prestation et une date.']);
        exit;
    }
    echo json_encode(BookingService::getAvailableSlots($mysqli, $cid, $sid, $dt));
    exit;
}

// Rediriger si l'utilisateur n'est pas connecté
require_login();

$user_id = (int) $_SESSION["user_id"];
$coiffeurs = [];
$services = [];
$errors = [];
$success = "";
$bookingConfirmation = null;

// Récupérer la liste des coiffeurs
$sql_coiffeurs = "SELECT c.id AS coiffeur_id, u.username, c.specialite 
                  FROM users u 
                  JOIN coiffeurs c ON u.id = c.user_id 
                  WHERE u.role = 'coiffeur'
                  ORDER BY u.username ASC";
if ($result_coiffeurs = $mysqli->query($sql_coiffeurs)) {
    while ($row = $result_coiffeurs->fetch_assoc()) {
        $coiffeurs[] = $row;
    }
    $result_coiffeurs->free();
} else {
    $errors[] = "Erreur lors de la récupération des coiffeurs.";
}

// Récupérer la liste des services avec leur durée (Phase 2.1)
$services = BookingService::getServicesForCoiffeur($mysqli, null);
$coiffeurServicesMap = BookingService::getCoiffeurServicesMap($mysqli);

$selected_coiffeur_id = validate_int($_POST["coiffeur_id"] ?? null, 1);
$selected_service_id = validate_int($_POST["service_id"] ?? null, 1);
$selected_date = sanitize_input($_POST["date"] ?? "", 20);
$selected_time = sanitize_input($_POST["time"] ?? "", 10);
$selected_type = sanitize_input($_POST["type_prestation"] ?? "salon", 20);
$selected_adresse = sanitize_input($_POST["adresse_domicile"] ?? "", 500);
$selected_tel = sanitize_input($_POST["telephone"] ?? "", 30);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        $errors[] = "Jeton de sécurité invalide. Veuillez soumettre à nouveau le formulaire.";
    } else {
        $result = BookingService::createBooking($mysqli, [
            'client_id' => $user_id,
            'coiffeur_id' => $selected_coiffeur_id ?? 0,
            'service_id' => $selected_service_id ?? 0,
            'date' => $selected_date,
            'time' => $selected_time,
            'type_prestation' => $selected_type,
            'adresse_domicile' => $selected_adresse,
            'telephone' => $selected_tel
        ]);

        if ($result['success']) {
            $success = "Votre rendez-vous a été enregistré avec succès et est en attente de confirmation.";
            // Récupérer le récapitulatif détaillé (Phase 6)
            $sqlRecap = "SELECT r.id, r.date_heure, r.date_heure_fin, r.duree_minutes_snapshot, r.prix_final,
                                r.type_prestation, r.adresse_domicile, r.telephone,
                                s.nom AS service_nom, u.username AS coiffeur_nom
                         FROM rendezvous r
                         JOIN services s ON r.service_id = s.id
                         JOIN coiffeurs c ON r.coiffeur_id = c.id
                         JOIN users u ON c.user_id = u.id
                         WHERE r.id = ? LIMIT 1";
            if ($stmtR = $mysqli->prepare($sqlRecap)) {
                $stmtR->bind_param("i", $result['rdv_id']);
                if ($stmtR->execute()) {
                    $bookingConfirmation = $stmtR->get_result()->fetch_assoc();
                }
                $stmtR->close();
            }
            $selected_coiffeur_id = $selected_service_id = null;
            $selected_date = $selected_time = $selected_adresse = $selected_tel = "";
            $selected_type = "salon";
        } else {
            $errors = array_merge($errors, $result['errors']);
        }
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
                <p><strong><?php echo htmlspecialchars($success); ?></strong></p>
                <?php if ($bookingConfirmation): ?>
                    <hr>
                    <h4>Récapitulatif de votre demande :</h4>
                    <ul>
                        <li><strong>Prestation :</strong> <?php echo htmlspecialchars($bookingConfirmation['service_nom']); ?> (<?php echo (int) $bookingConfirmation['duree_minutes_snapshot']; ?> min)</li>
                        <li><strong>Coiffeur :</strong> <?php echo htmlspecialchars($bookingConfirmation['coiffeur_nom']); ?></li>
                        <li><strong>Date &amp; Horaire :</strong> <?php echo date('d/m/Y de H:i', strtotime($bookingConfirmation['date_heure'])); ?> à <?php echo date('H:i', strtotime($bookingConfirmation['date_heure_fin'])); ?></li>
                        <li><strong>Prix estimé :</strong> <?php echo number_format((float) $bookingConfirmation['prix_final'], 2, ',', ' '); ?> $</li>
                        <li><strong>Lieu :</strong> <?php echo $bookingConfirmation['type_prestation'] === 'domicile' ? 'À domicile (' . htmlspecialchars($bookingConfirmation['adresse_domicile']) . ')' : 'Au salon'; ?></li>
                    </ul>
                    <p><a href="<?php echo BASE_URL; ?>frontend/client_dashboard.php" class="btn btn-sm">Voir mes rendez-vous</a></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="bookingForm">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label for="coiffeur_id">Choisissez votre coiffeur :</label>
                <select name="coiffeur_id" id="coiffeur_id" required>
                    <option value="">-- Sélectionnez un coiffeur --</option>
                    <?php foreach ($coiffeurs as $coiffeur): ?>
                        <option value="<?php echo (int) $coiffeur["coiffeur_id"]; ?>" <?php echo ($selected_coiffeur_id === (int) $coiffeur["coiffeur_id"]) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($coiffeur["username"]); ?> (<?php echo htmlspecialchars($coiffeur["specialite"] ?? ''); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="service_id">Choisissez la prestation :</label>
                <select name="service_id" id="service_id" required>
                    <option value="">-- Sélectionnez une prestation --</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?php echo (int) $service["id"]; ?>"
                                data-prix="<?php echo htmlspecialchars((string) $service["prix_depart"]); ?>"
                                data-duree="<?php echo (int) ($service["duree_minutes"] ?? 30); ?>"
                                <?php echo ($selected_service_id === (int) $service["id"]) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($service["nom"]); ?>
                            (<?php echo number_format((float) $service["prix_depart"], 2, ",", " "); ?> $ – <?php echo (int) ($service["duree_minutes"] ?? 30); ?> min)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small id="service-estimate" style="display:block; margin-top:6px; color:#555;"></small>
            </div>

            <div class="form-group">
                <label for="date">Date du rendez-vous :</label>
                <input type="date" name="date" id="date" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($selected_date); ?>">
            </div>

            <div class="form-group">
                <label for="time">Heure du rendez-vous :</label>
                <div id="available-slots-container" style="margin-bottom: 8px;">
                    <small id="slots-status" style="color:#666;">Sélectionnez un coiffeur, une prestation et une date pour voir les créneaux disponibles.</small>
                    <div id="slots-buttons" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:6px;"></div>
                </div>
                <input type="time" name="time" id="time" required value="<?php echo htmlspecialchars($selected_time); ?>">
            </div>

            <div class="form-group">
                <label for="type_prestation">Lieu de la prestation :</label>
                <select name="type_prestation" id="type_prestation" required>
                    <option value="salon" <?php echo ($selected_type === 'salon') ? 'selected' : ''; ?>>Au salon</option>
                    <option value="domicile" <?php echo ($selected_type === 'domicile') ? 'selected' : ''; ?>>À domicile</option>
                </select>
            </div>

            <!-- Champs à domicile obligatoires uniquement lorsque type_prestation === 'domicile' (Phase 6) -->
            <div id="domicile-fields" style="display: <?php echo ($selected_type === 'domicile') ? 'block' : 'none'; ?>;">
                <div class="form-group">
                    <label for="adresse_domicile">Adresse complète :</label>
                    <textarea name="adresse_domicile" id="adresse_domicile" rows="3" maxlength="500" <?php echo ($selected_type === 'domicile') ? 'required' : ''; ?>><?php echo htmlspecialchars($selected_adresse); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="telephone">Téléphone (pour contact) :</label>
                    <input type="tel" name="telephone" id="telephone" maxlength="25" placeholder="+243 ..." value="<?php echo htmlspecialchars($selected_tel); ?>" <?php echo ($selected_type === 'domicile') ? 'required' : ''; ?>>
                </div>
            </div>

            <button type="submit" class="btn">Confirmer le rendez-vous</button>
        </form>
    </div>
</section>

<script>
(function() {
    const coiffeurServicesMap = <?php echo json_encode($coiffeurServicesMap); ?>;
    const coiffeurSelect = document.getElementById('coiffeur_id');
    const serviceSelect = document.getElementById('service_id');
    const dateInput = document.getElementById('date');
    const timeInput = document.getElementById('time');
    const typeSelect = document.getElementById('type_prestation');
    const domicileFields = document.getElementById('domicile-fields');
    const adresseInput = document.getElementById('adresse_domicile');
    const telInput = document.getElementById('telephone');
    const estimateEl = document.getElementById('service-estimate');
    const slotsStatus = document.getElementById('slots-status');
    const slotsButtons = document.getElementById('slots-buttons');

    function updateDomicileRequired() {
        const isDomicile = (typeSelect.value === 'domicile');
        domicileFields.style.display = isDomicile ? 'block' : 'none';
        adresseInput.required = isDomicile;
        telInput.required = isDomicile;
    }

    function filterServicesByCoiffeur() {
        const cid = parseInt(coiffeurSelect.value, 10);
        const assigned = coiffeurServicesMap[cid] || null;
        const allowedIds = assigned ? assigned.map(item => item.service_id) : null;

        Array.from(serviceSelect.options).forEach(opt => {
            if (!opt.value) return;
            const sid = parseInt(opt.value, 10);
            if (!allowedIds || allowedIds.length === 0 || allowedIds.includes(sid)) {
                opt.hidden = false;
                opt.disabled = false;
            } else {
                opt.hidden = true;
                opt.disabled = true;
                if (serviceSelect.value === opt.value) {
                    serviceSelect.value = '';
                }
            }
        });
        updateServiceEstimate();
    }

    function updateServiceEstimate() {
        const selectedOpt = serviceSelect.options[serviceSelect.selectedIndex];
        if (!selectedOpt || !selectedOpt.value) {
            estimateEl.textContent = '';
            return;
        }
        const duree = selectedOpt.getAttribute('data-duree') || '30';
        const prix = parseFloat(selectedOpt.getAttribute('data-prix') || '0').toFixed(2).replace('.', ',');
        estimateEl.textContent = `Durée estimée : ${duree} minutes | Tarif de base : ${prix} $`;
    }

    function fetchAvailableSlots() {
        const cid = coiffeurSelect.value;
        const sid = serviceSelect.value;
        const dt = dateInput.value;
        slotsButtons.innerHTML = '';

        if (!cid || !sid || !dt) {
            slotsStatus.textContent = 'Sélectionnez un coiffeur, une prestation et une date pour voir les créneaux disponibles.';
            return;
        }

        slotsStatus.textContent = 'Recherche des créneaux disponibles...';
        fetch(`?ajax_slots=1&coiffeur_id=${encodeURIComponent(cid)}&service_id=${encodeURIComponent(sid)}&date=${encodeURIComponent(dt)}`)
            .then(r => r.json())
            .then(data => {
                slotsStatus.textContent = data.message || '';
                if (Array.isArray(data.available_slots) && data.available_slots.length > 0) {
                    data.available_slots.forEach(slot => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.textContent = slot;
                        btn.style.cssText = 'padding:5px 10px; border:1px solid #d4af37; background:#fff; color:#222; border-radius:4px; cursor:pointer; font-size:0.9rem;';
                        btn.addEventListener('click', function() {
                            timeInput.value = slot;
                            Array.from(slotsButtons.children).forEach(b => {
                                b.style.background = '#fff';
                                b.style.color = '#222';
                            });
                            btn.style.background = '#d4af37';
                            btn.style.color = '#fff';
                        });
                        slotsButtons.appendChild(btn);
                    });
                }
            })
            .catch(() => {
                slotsStatus.textContent = 'Veuillez saisir une heure comprise dans les horaires d\'ouverture.';
            });
    }

    typeSelect.addEventListener('change', updateDomicileRequired);
    coiffeurSelect.addEventListener('change', function() {
        filterServicesByCoiffeur();
        fetchAvailableSlots();
    });
    serviceSelect.addEventListener('change', function() {
        updateServiceEstimate();
        fetchAvailableSlots();
    });
    dateInput.addEventListener('change', fetchAvailableSlots);

    updateDomicileRequired();
    filterServicesByCoiffeur();
})();
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>
