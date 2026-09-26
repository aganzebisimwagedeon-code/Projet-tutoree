<?php
/**
 * Service métier de gestion des réservations, disponibilités, annulations et reprogrammations
 * (Phases 2, 3, 5 et 6).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/NotificationService.php';

class BookingService
{
    private const DAYS_FR = [
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi',
        7 => 'Dimanche',
    ];

    /**
     * Retourne le nom du jour en français à partir d'une date.
     */
    public static function getDayNameFr(DateTimeInterface $dt): string {
        $isoDay = (int) $dt->format('N');
        return self::DAYS_FR[$isoDay] ?? 'Lundi';
    }

    /**
     * Vérifie si deux intervalles [startA, endA[ et [startB, endB[ se chevauchent.
     */
    public static function intervalsOverlap(int $startA, int $endA, int $startB, int $endB): bool {
        return ($startA < $endB) && ($endA > $startB);
    }

    /**
     * Récupère les services proposés (filtrés par coiffeur si spécifié et si la table coiffeur_services est alimentée).
     */
    public static function getServicesForCoiffeur(mysqli $mysqli, ?int $coiffeurId = null): array {
        $services = [];
        if ($coiffeurId !== null && $coiffeurId > 0) {
            $sql = "SELECT s.id, s.nom, s.description,
                           COALESCE(cs.prix_personnalise, s.prix_depart) AS prix_depart,
                           COALESCE(s.duree_minutes, 30) AS duree_minutes,
                           s.prix_discutable
                    FROM coiffeur_services cs
                    JOIN services s ON cs.service_id = s.id
                    WHERE cs.coiffeur_id = ?
                    ORDER BY s.nom ASC";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("i", $coiffeurId);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $services[] = $row;
                    }
                }
                $stmt->close();
            }
            if (!empty($services)) {
                return $services;
            }
        }

        // Fallback : retourner toutes les prestations
        $sqlAll = "SELECT id, nom, description, prix_depart, COALESCE(duree_minutes, 30) AS duree_minutes, prix_discutable FROM services ORDER BY nom ASC";
        if ($res = $mysqli->query($sqlAll)) {
            while ($row = $res->fetch_assoc()) {
                $services[] = $row;
            }
            $res->free();
        }
        return $services;
    }

    /**
     * Récupère la correspondance complète coiffeur_id => [service_id, ...] pour le filtrage dynamique JS.
     */
    public static function getCoiffeurServicesMap(mysqli $mysqli): array {
        $map = [];
        $sql = "SELECT coiffeur_id, service_id, prix_personnalise FROM coiffeur_services";
        if ($res = $mysqli->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $cid = (int) $row['coiffeur_id'];
                $map[$cid][] = [
                    'service_id' => (int) $row['service_id'],
                    'prix_personnalise' => $row['prix_personnalise'] !== null ? (float) $row['prix_personnalise'] : null
                ];
            }
            $res->free();
        }
        return $map;
    }

    /**
     * Vérifie toutes les règles métier de disponibilité côté serveur (Phase 2.2) :
     * - Date valide et dans le futur
     * - Coiffeur existant avec rôle 'coiffeur'
     * - Service existant et proposé par ce coiffeur
     * - Salon ouvert ce jour-là et créneau compris dans les horaires d'ouverture
     * - Coiffeur en service ce jour-là (planning) et créneau compris dans sa plage horaire
     * - Aucune absence exceptionnelle du coiffeur sur ce créneau
     * - Aucun chevauchement avec un autre rendez-vous 'pending' ou 'confirmed'
     *
     * @return array{valid: bool, errors: string[], service?: array, start_dt?: DateTimeImmutable, end_dt?: DateTimeImmutable}
     */
    public static function validateAvailability(
        mysqli $mysqli,
        int $coiffeurId,
        int $serviceId,
        string $dateStr,
        string $timeStr,
        ?int $excludeRdvId = null
    ): array {
        $errors = [];

        $dateObj = validate_date_format($dateStr, 'Y-m-d');
        $normalizedTime = validate_time_format($timeStr);
        if (!$dateObj || !$normalizedTime) {
            return ['valid' => false, 'errors' => ["Format de date ou d'heure invalide."]];
        }

        $startDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateObj->format('Y-m-d') . ' ' . $normalizedTime);
        if (!$startDt) {
            return ['valid' => false, 'errors' => ["Impossible d'analyser la date et l'heure choisies."]];
        }

        $now = new DateTimeImmutable('now');
        if ($startDt <= $now) {
            $errors[] = "La date et l'heure du rendez-vous doivent être dans le futur.";
        }

        // 1. Vérifier que le coiffeur existe et a bien le rôle 'coiffeur'
        $sqlCoiffeur = "SELECT c.id, u.username
                        FROM coiffeurs c
                        JOIN users u ON c.user_id = u.id
                        WHERE c.id = ? AND u.role = 'coiffeur'
                        LIMIT 1";
        $coiffeurRow = null;
        if ($stmt = $mysqli->prepare($sqlCoiffeur)) {
            $stmt->bind_param("i", $coiffeurId);
            if ($stmt->execute()) {
                $coiffeurRow = $stmt->get_result()->fetch_assoc();
            }
            $stmt->close();
        }
        if (!$coiffeurRow) {
            $errors[] = "Le coiffeur sélectionné est invalide ou n'est plus actif.";
        }

        // 2. Vérifier que le service existe et récupérer sa durée et son prix
        $sqlService = "SELECT id, nom, prix_depart, COALESCE(duree_minutes, 30) AS duree_minutes FROM services WHERE id = ? LIMIT 1";
        $serviceRow = null;
        if ($stmt = $mysqli->prepare($sqlService)) {
            $stmt->bind_param("i", $serviceId);
            if ($stmt->execute()) {
                $serviceRow = $stmt->get_result()->fetch_assoc();
            }
            $stmt->close();
        }
        if (!$serviceRow) {
            $errors[] = "La prestation sélectionnée est introuvable.";
            return ['valid' => false, 'errors' => $errors];
        }

        $dureeMinutes = max(5, (int) $serviceRow['duree_minutes']);
        $prixFinal = (float) $serviceRow['prix_depart'];
        $endDt = $startDt->modify("+{$dureeMinutes} minutes");

        // 3. Vérifier que le service est bien proposé par ce coiffeur (si coiffeur_services est renseigné pour ce coiffeur)
        if ($coiffeurRow) {
            $hasAnyAssignedService = false;
            if ($stmtCount = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM coiffeur_services WHERE coiffeur_id = ?")) {
                $stmtCount->bind_param("i", $coiffeurId);
                if ($stmtCount->execute()) {
                    $cntRow = $stmtCount->get_result()->fetch_assoc();
                    $hasAnyAssignedService = ((int) ($cntRow['cnt'] ?? 0)) > 0;
                }
                $stmtCount->close();
            }

            if ($hasAnyAssignedService) {
                $sqlCs = "SELECT prix_personnalise FROM coiffeur_services WHERE coiffeur_id = ? AND service_id = ? LIMIT 1";
                if ($stmtCs = $mysqli->prepare($sqlCs)) {
                    $stmtCs->bind_param("ii", $coiffeurId, $serviceId);
                    $stmtCs->execute();
                    $resCs = $stmtCs->get_result();
                    if ($csRow = $resCs->fetch_assoc()) {
                        if ($csRow['prix_personnalise'] !== null) {
                            $prixFinal = (float) $csRow['prix_personnalise'];
                        }
                    } else {
                        $errors[] = "Ce coiffeur ne propose pas la prestation « " . $serviceRow['nom'] . " ».";
                    }
                    $stmtCs->close();
                }
            }
        }

        $jourSemaine = self::getDayNameFr($startDt);
        $reqStartTime = $startDt->format('H:i:s');
        $reqEndTime = $endDt->format('H:i:s');

        // Vérifier que le rendez-vous ne déborde pas sur le jour suivant
        if ($endDt->format('Y-m-d') !== $startDt->format('Y-m-d')) {
            $errors[] = "La durée de la prestation dépasse la fin de journée.";
        }

        // 4. Vérifier que le salon est ouvert ce jour-là et que la durée respecte la fermeture
        $sqlHoraire = "SELECT heure_ouverture, heure_fermeture, ferme FROM horaires_ouverture WHERE jour_semaine = ? LIMIT 1";
        if ($stmtH = $mysqli->prepare($sqlHoraire)) {
            $stmtH->bind_param("s", $jourSemaine);
            if ($stmtH->execute()) {
                $resH = $stmtH->get_result();
                if ($hRow = $resH->fetch_assoc()) {
                    if ((int) $hRow['ferme'] === 1) {
                        $errors[] = "Le salon est fermé le {$jourSemaine}.";
                    } else {
                        $openTime = $hRow['heure_ouverture'];
                        $closeTime = $hRow['heure_fermeture'];
                        if ($reqStartTime < $openTime || $reqEndTime > $closeTime) {
                            $errors[] = sprintf(
                                "Le créneau choisi (%s - %s) est en dehors des horaires d'ouverture du salon le %s (%s - %s).",
                                substr($reqStartTime, 0, 5),
                                substr($reqEndTime, 0, 5),
                                $jourSemaine,
                                substr($openTime, 0, 5),
                                substr($closeTime, 0, 5)
                            );
                        }
                    }
                }
            }
            $stmtH->close();
        }

        // 5. Vérifier que le coiffeur travaille ce jour-là et sur cette plage horaire
        if ($coiffeurRow) {
            $sqlPlan = "SELECT heure_debut, heure_fin FROM plannings WHERE coiffeur_id = ? AND jour_semaine = ?";
            if ($stmtP = $mysqli->prepare($sqlPlan)) {
                $stmtP->bind_param("is", $coiffeurId, $jourSemaine);
                if ($stmtP->execute()) {
                    $resP = $stmtP->get_result();
                    $shifts = [];
                    while ($pRow = $resP->fetch_assoc()) {
                        $shifts[] = $pRow;
                    }
                    if (empty($shifts)) {
                        $errors[] = "Le coiffeur " . $coiffeurRow['username'] . " ne travaille pas le {$jourSemaine}.";
                    } else {
                        $fitsInShift = false;
                        $shiftLabels = [];
                        foreach ($shifts as $shift) {
                            $shiftLabels[] = substr($shift['heure_debut'], 0, 5) . ' - ' . substr($shift['heure_fin'], 0, 5);
                            if ($reqStartTime >= $shift['heure_debut'] && $reqEndTime <= $shift['heure_fin']) {
                                $fitsInShift = true;
                                break;
                            }
                        }
                        if (!$fitsInShift) {
                            $errors[] = sprintf(
                                "Le créneau (%s - %s) ne correspond pas aux horaires de travail de %s le %s (%s).",
                                substr($reqStartTime, 0, 5),
                                substr($reqEndTime, 0, 5),
                                $coiffeurRow['username'],
                                $jourSemaine,
                                implode(', ', $shiftLabels)
                            );
                        }
                    }
                }
                $stmtP->close();
            }

            // 6. Vérifier les absences exceptionnelles du coiffeur (Phase 2.4)
            $startSql = $startDt->format('Y-m-d H:i:s');
            $endSql = $endDt->format('Y-m-d H:i:s');
            $sqlAbs = "SELECT motif, date_debut, date_fin
                       FROM absences_coiffeurs
                       WHERE coiffeur_id = ? AND date_debut < ? AND date_fin > ?
                       LIMIT 1";
            if ($stmtAbs = $mysqli->prepare($sqlAbs)) {
                $stmtAbs->bind_param("iss", $coiffeurId, $endSql, $startSql);
                if ($stmtAbs->execute()) {
                    if ($absRow = $stmtAbs->get_result()->fetch_assoc()) {
                        $errors[] = "Ce coiffeur est indisponible / en congé sur ce créneau"
                            . (!empty($absRow['motif']) ? " (" . $absRow['motif'] . ")" : "") . ".";
                    }
                }
                $stmtAbs->close();
            }

            // 7. Vérifier l'absence de chevauchement avec d'autres rendez-vous (Phase 2.2)
            $dayStart = $startDt->format('Y-m-d') . ' 00:00:00';
            $dayEnd = $startDt->format('Y-m-d') . ' 23:59:59';
            $sqlOverlap = "SELECT r.id, r.date_heure, r.date_heure_fin,
                                  COALESCE(r.duree_minutes_snapshot, s.duree_minutes, 30) AS duree_minutes
                           FROM rendezvous r
                           JOIN services s ON r.service_id = s.id
                           WHERE r.coiffeur_id = ?
                             AND r.statut IN ('pending', 'confirmed')
                             AND r.date_heure BETWEEN ? AND ?";
            if ($excludeRdvId !== null) {
                $sqlOverlap .= " AND r.id != ?";
            }

            if ($stmtOv = $mysqli->prepare($sqlOverlap)) {
                if ($excludeRdvId !== null) {
                    $stmtOv->bind_param("issi", $coiffeurId, $dayStart, $dayEnd, $excludeRdvId);
                } else {
                    $stmtOv->bind_param("iss", $coiffeurId, $dayStart, $dayEnd);
                }
                if ($stmtOv->execute()) {
                    $resOv = $stmtOv->get_result();
                    $reqStartTs = $startDt->getTimestamp();
                    $reqEndTs = $endDt->getTimestamp();
                    while ($ovRow = $resOv->fetch_assoc()) {
                        $exStartTs = strtotime($ovRow['date_heure']);
                        $exEndTs = !empty($ovRow['date_heure_fin'])
                            ? strtotime($ovRow['date_heure_fin'])
                            : ($exStartTs + ((int) $ovRow['duree_minutes'] * 60));
                        if (self::intervalsOverlap($reqStartTs, $reqEndTs, $exStartTs, $exEndTs)) {
                            $errors[] = sprintf(
                                "Ce créneau chevauche un rendez-vous déjà réservé (%s - %s). Veuillez choisir un autre horaire.",
                                date('H:i', $exStartTs),
                                date('H:i', $exEndTs)
                            );
                            break;
                        }
                    }
                }
                $stmtOv->close();
            }
        }

        $serviceRow['duree_minutes'] = $dureeMinutes;
        $serviceRow['prix_final'] = $prixFinal;

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'service' => $serviceRow,
            'start_dt' => $startDt,
            'end_dt' => $endDt
        ];
    }

    /**
     * Crée un rendez-vous de manière atomique avec transaction SQL et verrouillage (Phase 2.3 & Phase 3.2).
     *
     * @param array{
     *   client_id: int,
     *   coiffeur_id: int,
     *   service_id: int,
     *   date: string,
     *   time: string,
     *   type_prestation: string,
     *   adresse_domicile?: ?string,
     *   telephone?: ?string
     * } $input
     * @return array{success: bool, rdv_id?: int, errors: string[]}
     */
    public static function createBooking(mysqli $mysqli, array $input): array {
        $clientId = (int) ($input['client_id'] ?? 0);
        $coiffeurId = (int) ($input['coiffeur_id'] ?? 0);
        $serviceId = (int) ($input['service_id'] ?? 0);
        $date = trim((string) ($input['date'] ?? ''));
        $time = trim((string) ($input['time'] ?? ''));
        $typePrestation = trim((string) ($input['type_prestation'] ?? 'salon'));
        $adresseDomicile = isset($input['adresse_domicile']) ? trim((string) $input['adresse_domicile']) : null;
        $telephone = isset($input['telephone']) ? trim((string) $input['telephone']) : null;

        $errors = [];
        if ($clientId <= 0 || $coiffeurId <= 0 || $serviceId <= 0 || $date === '' || $time === '') {
            return ['success' => false, 'errors' => ["Tous les champs obligatoires doivent être renseignés."]];
        }
        if (!in_array($typePrestation, ['salon', 'domicile'], true)) {
            return ['success' => false, 'errors' => ["Type de prestation invalide."]];
        }

        if ($typePrestation === 'domicile') {
            if (empty($adresseDomicile) || mb_strlen($adresseDomicile) < 5) {
                $errors[] = "Veuillez fournir une adresse complète (minimum 5 caractères) pour une prestation à domicile.";
            }
            $validPhone = validate_phone($telephone);
            if ($validPhone === null) {
                $errors[] = "Veuillez fournir un numéro de téléphone valide (8 à 20 chiffres) pour une prestation à domicile.";
            } else {
                $telephone = $validPhone;
            }
        } else {
            $adresseDomicile = null;
            $telephone = ($telephone !== null && $telephone !== '') ? validate_phone($telephone) : null;
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Démarrer la transaction pour éviter deux réservations simultanées sur le même créneau (Phase 2.3)
        $mysqli->begin_transaction();
        try {
            // Verrouiller la ligne du coiffeur pour sérialiser les vérifications concurrentes
            if ($stmtLock = $mysqli->prepare("SELECT id FROM coiffeurs WHERE id = ? FOR UPDATE")) {
                $stmtLock->bind_param("i", $coiffeurId);
                $stmtLock->execute();
                $stmtLock->close();
            }

            // Recontrôler la disponibilité à l'intérieur de la transaction
            $validation = self::validateAvailability($mysqli, $coiffeurId, $serviceId, $date, $time);
            if (!$validation['valid']) {
                $mysqli->rollback();
                return ['success' => false, 'errors' => $validation['errors']];
            }

            $startSql = $validation['start_dt']->format('Y-m-d H:i:s');
            $endSql = $validation['end_dt']->format('Y-m-d H:i:s');
            $dureeSnapshot = (int) $validation['service']['duree_minutes'];
            $prixFinal = (float) $validation['service']['prix_final'];

            $sqlInsert = "INSERT INTO rendezvous (
                              client_id, coiffeur_id, service_id, date_heure, date_heure_fin,
                              duree_minutes_snapshot, prix_final, type_prestation, adresse_domicile, telephone, statut
                          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

            if ($stmtIns = $mysqli->prepare($sqlInsert)) {
                $stmtIns->bind_param(
                    "iiissidsss",
                    $clientId,
                    $coiffeurId,
                    $serviceId,
                    $startSql,
                    $endSql,
                    $dureeSnapshot,
                    $prixFinal,
                    $typePrestation,
                    $adresseDomicile,
                    $telephone
                );
                if ($stmtIns->execute()) {
                    $newRdvId = (int) $stmtIns->insert_id;
                    $stmtIns->close();
                    $mysqli->commit();

                    // Envoyer les notifications et emails (Phase 7)
                    NotificationService::notifyBookingCreated($mysqli, $newRdvId);

                    return ['success' => true, 'rdv_id' => $newRdvId, 'errors' => []];
                }
                $dbErr = $stmtIns->error;
                $stmtIns->close();
                $mysqli->rollback();
                error_log("Erreur SQL lors de l'insertion du rendez-vous : $dbErr");
                return ['success' => false, 'errors' => ["Impossible d'enregistrer le rendez-vous. Veuillez réessayer."]];
            }

            $mysqli->rollback();
            return ['success' => false, 'errors' => ["Erreur interne lors de la préparation de la réservation."]];
        } catch (\Throwable $e) {
            $mysqli->rollback();
            error_log("Exception BookingService::createBooking : " . $e->getMessage());
            return ['success' => false, 'errors' => ["Une erreur inattendue est survenue lors de la réservation."]];
        }
    }

    /**
     * Calcule la liste des créneaux horaires réellement disponibles pour un coiffeur, un service et une date (Phase 6).
     * @return array{available_slots: string[], message: string}
     */
    public static function getAvailableSlots(mysqli $mysqli, int $coiffeurId, int $serviceId, string $dateStr): array {
        $dateObj = validate_date_format($dateStr, 'Y-m-d');
        if (!$dateObj) {
            return ['available_slots' => [], 'message' => "Date invalide."];
        }

        // Récupérer la durée de la prestation
        $dureeMinutes = 30;
        if ($stmtS = $mysqli->prepare("SELECT COALESCE(duree_minutes, 30) AS duree_minutes FROM services WHERE id = ? LIMIT 1")) {
            $stmtS->bind_param("i", $serviceId);
            if ($stmtS->execute()) {
                if ($sRow = $stmtS->get_result()->fetch_assoc()) {
                    $dureeMinutes = max(15, (int) $sRow['duree_minutes']);
                }
            }
            $stmtS->close();
        }

        $jourSemaine = self::getDayNameFr($dateObj);

        // Horaires du salon
        $salonOpen = '09:00:00';
        $salonClose = '18:00:00';
        if ($stmtH = $mysqli->prepare("SELECT heure_ouverture, heure_fermeture, ferme FROM horaires_ouverture WHERE jour_semaine = ? LIMIT 1")) {
            $stmtH->bind_param("s", $jourSemaine);
            if ($stmtH->execute()) {
                if ($hRow = $stmtH->get_result()->fetch_assoc()) {
                    if ((int) $hRow['ferme'] === 1) {
                        $stmtH->close();
                        return ['available_slots' => [], 'message' => "Le salon est fermé le {$jourSemaine}."];
                    }
                    $salonOpen = $hRow['heure_ouverture'];
                    $salonClose = $hRow['heure_fermeture'];
                }
            }
            $stmtH->close();
        }

        // Plannings du coiffeur
        $shifts = [];
        if ($stmtP = $mysqli->prepare("SELECT heure_debut, heure_fin FROM plannings WHERE coiffeur_id = ? AND jour_semaine = ? ORDER BY heure_debut ASC")) {
            $stmtP->bind_param("is", $coiffeurId, $jourSemaine);
            if ($stmtP->execute()) {
                $resP = $stmtP->get_result();
                while ($pRow = $resP->fetch_assoc()) {
                    $shifts[] = $pRow;
                }
            }
            $stmtP->close();
        }

        if (empty($shifts)) {
            return ['available_slots' => [], 'message' => "Ce coiffeur ne travaille pas le {$jourSemaine}."];
        }

        // Rendez-vous déjà réservés ce jour-là
        $dayStart = $dateObj->format('Y-m-d') . ' 00:00:00';
        $dayEnd = $dateObj->format('Y-m-d') . ' 23:59:59';
        $busyIntervals = [];

        $sqlBusy = "SELECT r.date_heure, r.date_heure_fin,
                           COALESCE(r.duree_minutes_snapshot, s.duree_minutes, 30) AS duree_minutes
                    FROM rendezvous r
                    JOIN services s ON r.service_id = s.id
                    WHERE r.coiffeur_id = ?
                      AND r.statut IN ('pending', 'confirmed')
                      AND r.date_heure BETWEEN ? AND ?";
        if ($stmtB = $mysqli->prepare($sqlBusy)) {
            $stmtB->bind_param("iss", $coiffeurId, $dayStart, $dayEnd);
            if ($stmtB->execute()) {
                $resB = $stmtB->get_result();
                while ($bRow = $resB->fetch_assoc()) {
                    $bStart = strtotime($bRow['date_heure']);
                    $bEnd = !empty($bRow['date_heure_fin'])
                        ? strtotime($bRow['date_heure_fin'])
                        : ($bStart + ((int) $bRow['duree_minutes'] * 60));
                    $busyIntervals[] = [$bStart, $bEnd];
                }
            }
            $stmtB->close();
        }

        // Absences du coiffeur couvrant ce jour
        $sqlAbs = "SELECT date_debut, date_fin FROM absences_coiffeurs WHERE coiffeur_id = ? AND date_debut < ? AND date_fin > ?";
        if ($stmtA = $mysqli->prepare($sqlAbs)) {
            $stmtA->bind_param("iss", $coiffeurId, $dayEnd, $dayStart);
            if ($stmtA->execute()) {
                $resA = $stmtA->get_result();
                while ($aRow = $resA->fetch_assoc()) {
                    $busyIntervals[] = [strtotime($aRow['date_debut']), strtotime($aRow['date_fin'])];
                }
            }
            $stmtA->close();
        }

        $nowTs = time();
        $stepSeconds = 30 * 60; // Créneaux toutes les 30 minutes
        $durationSeconds = $dureeMinutes * 60;
        $slots = [];

        foreach ($shifts as $shift) {
            $effectiveStart = max($salonOpen, $shift['heure_debut']);
            $effectiveEnd = min($salonClose, $shift['heure_fin']);
            if ($effectiveStart >= $effectiveEnd) {
                continue;
            }
            $cursorTs = strtotime($dateObj->format('Y-m-d') . ' ' . $effectiveStart);
            $limitEndTs = strtotime($dateObj->format('Y-m-d') . ' ' . $effectiveEnd);

            while (($cursorTs + $durationSeconds) <= $limitEndTs) {
                if ($cursorTs > $nowTs) {
                    $slotEndTs = $cursorTs + $durationSeconds;
                    $hasConflict = false;
                    foreach ($busyIntervals as [$bStart, $bEnd]) {
                        if (self::intervalsOverlap($cursorTs, $slotEndTs, $bStart, $bEnd)) {
                            $hasConflict = true;
                            break;
                        }
                    }
                    if (!$hasConflict) {
                        $slots[] = date('H:i', $cursorTs);
                    }
                }
                $cursorTs += $stepSeconds;
            }
        }

        $slots = array_values(array_unique($slots));
        return [
            'available_slots' => $slots,
            'message' => empty($slots) ? "Aucun créneau disponible à cette date." : count($slots) . " créneau(x) disponible(s)."
        ];
    }

    /**
     * Reprogramme un rendez-vous existant d'un client en vérifiant la politique de délai et la disponibilité (Phase 6).
     * @return array{success: bool, errors: string[]}
     */
    public static function rescheduleBooking(
        mysqli $mysqli,
        int $rdvId,
        int $clientId,
        string $newDate,
        string $newTime
    ): array {
        $sql = "SELECT id, client_id, coiffeur_id, service_id, date_heure, statut FROM rendezvous WHERE id = ? AND client_id = ? LIMIT 1";
        $rdv = null;
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("ii", $rdvId, $clientId);
            if ($stmt->execute()) {
                $rdv = $stmt->get_result()->fetch_assoc();
            }
            $stmt->close();
        }

        if (!$rdv) {
            return ['success' => false, 'errors' => ["Rendez-vous introuvable ou accès non autorisé."]];
        }
        if (!in_array($rdv['statut'], ['pending', 'confirmed'], true)) {
            return ['success' => false, 'errors' => ["Seuls les rendez-vous en attente ou confirmés peuvent être reprogrammés."]];
        }

        // Vérifier le délai minimum avant le rendez-vous initial
        $minHours = defined('CLIENT_CANCEL_POLICY_HOURS') ? CLIENT_CANCEL_POLICY_HOURS : 24;
        if ($resP = $mysqli->query("SELECT delai_minimum_heures FROM politiques_annulation WHERE type_acteur = 'client' LIMIT 1")) {
            if ($pRow = $resP->fetch_assoc()) {
                $minHours = (int) $pRow['delai_minimum_heures'];
            }
            $resP->free();
        }

        $diffHours = floor((strtotime($rdv['date_heure']) - time()) / 3600);
        if ($diffHours < $minHours) {
            return [
                'success' => false,
                'errors' => ["Impossible de reprogrammer ce rendez-vous moins de {$minHours} heures avant l'horaire prévu."]
            ];
        }

        $mysqli->begin_transaction();
        try {
            $coiffeurId = (int) $rdv['coiffeur_id'];
            $serviceId = (int) $rdv['service_id'];

            if ($stmtLock = $mysqli->prepare("SELECT id FROM coiffeurs WHERE id = ? FOR UPDATE")) {
                $stmtLock->bind_param("i", $coiffeurId);
                $stmtLock->execute();
                $stmtLock->close();
            }

            $validation = self::validateAvailability($mysqli, $coiffeurId, $serviceId, $newDate, $newTime, $rdvId);
            if (!$validation['valid']) {
                $mysqli->rollback();
                return ['success' => false, 'errors' => $validation['errors']];
            }

            $newStartSql = $validation['start_dt']->format('Y-m-d H:i:s');
            $newEndSql = $validation['end_dt']->format('Y-m-d H:i:s');

            $sqlUpd = "UPDATE rendezvous SET date_heure = ?, date_heure_fin = ?, statut = 'pending' WHERE id = ?";
            if ($stmtUpd = $mysqli->prepare($sqlUpd)) {
                $stmtUpd->bind_param("ssi", $newStartSql, $newEndSql, $rdvId);
                if ($stmtUpd->execute()) {
                    $stmtUpd->close();
                    $mysqli->commit();
                    NotificationService::notifyBookingRescheduled($mysqli, $rdvId, $rdv['date_heure'], $newStartSql);
                    return ['success' => true, 'errors' => []];
                }
                $stmtUpd->close();
            }

            $mysqli->rollback();
            return ['success' => false, 'errors' => ["Erreur lors de la mise à jour du rendez-vous."]];
        } catch (\Throwable $e) {
            $mysqli->rollback();
            error_log("Exception rescheduleBooking : " . $e->getMessage());
            return ['success' => false, 'errors' => ["Erreur interne lors de la reprogrammation."]];
        }
    }
}
