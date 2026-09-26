<?php
/**
 * Service centralisé d'envoi d'emails (PHPMailer), de journalisation (email_logs)
 * et de notifications internes dans le tableau de bord (Phase 5.2 & Phase 7).
 */

require_once __DIR__ . '/../includes/config.php';

// Charger l'autoloader Composer si présent
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class NotificationService
{
    /**
     * Envoie un email via PHPMailer et journalise l'opération dans `email_logs`.
     */
    public static function sendEmail(
        mysqli $mysqli,
        string $toEmail,
        string $subject,
        string $htmlBody,
        string $notificationType = 'general'
    ): bool {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            self::logEmail($mysqli, $toEmail, $subject, $notificationType, 'failed', 'Adresse email destinataire invalide');
            return false;
        }

        // Si l'envoi SMTP est désactivé ou non configuré en local, on journalise en 'skipped' sans bloquer l'application
        if (
            !defined('MAIL_ENABLED') || !MAIL_ENABLED ||
            !class_exists(PHPMailer::class) ||
            MAIL_HOST === '' || MAIL_HOST === 'smtp.example.com' || MAIL_USERNAME === ''
        ) {
            self::logEmail(
                $mysqli,
                $toEmail,
                $subject,
                $notificationType,
                'skipped',
                'Envoi SMTP désactivé ou paramètres SMTP d\'exemple (mode local)'
            );
            return true;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->Port = MAIL_PORT;

            if (defined('MAIL_ENCRYPTION') && strtolower(MAIL_ENCRYPTION) === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $fromEmail = (defined('MAIL_FROM_EMAIL') && MAIL_FROM_EMAIL !== '') ? MAIL_FROM_EMAIL : MAIL_USERNAME;
            $fromName = (defined('MAIL_FROM_NAME') && MAIL_FROM_NAME !== '') ? MAIL_FROM_NAME : 'King and Qween Salon';

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = self::wrapEmailTemplate($subject, $htmlBody);
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlBody));

            $mail->send();
            self::logEmail($mysqli, $toEmail, $subject, $notificationType, 'sent', null);
            return true;
        } catch (MailException $e) {
            $err = $mail->ErrorInfo ?: $e->getMessage();
            error_log("Erreur d'envoi d'email ($notificationType) à $toEmail : $err");
            self::logEmail($mysqli, $toEmail, $subject, $notificationType, 'failed', $err);
            return false;
        } catch (\Throwable $t) {
            error_log("Exception d'envoi d'email ($notificationType) à $toEmail : " . $t->getMessage());
            self::logEmail($mysqli, $toEmail, $subject, $notificationType, 'failed', $t->getMessage());
            return false;
        }
    }

    /**
     * Enregistre une entrée dans la table `email_logs`.
     */
    public static function logEmail(
        mysqli $mysqli,
        string $toEmail,
        string $subject,
        string $type,
        string $status,
        ?string $errorMessage = null
    ): void {
        $sql = "INSERT INTO email_logs (destinataire_email, sujet, type_notification, statut, erreur_message) VALUES (?, ?, ?, ?, ?)";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("sssss", $toEmail, $subject, $type, $status, $errorMessage);
            @$stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Crée une notification interne pour le tableau de bord d'un utilisateur.
     */
    public static function createNotification(mysqli $mysqli, int $userId, string $title, string $message): void {
        if ($userId <= 0) {
            return;
        }
        $sql = "INSERT INTO notifications (user_id, titre, message, lu) VALUES (?, ?, ?, 0)";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("iss", $userId, $title, $message);
            @$stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Récupère les notifications récentes d'un utilisateur.
     */
    public static function getUserNotifications(mysqli $mysqli, int $userId, int $limit = 10): array {
        $items = [];
        $sql = "SELECT id, titre, message, lu, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("ii", $userId, $limit);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $items[] = $row;
                }
            }
            $stmt->close();
        }
        return $items;
    }

    /**
     * Marque toutes les notifications d'un utilisateur comme lues.
     */
    public static function markAllRead(mysqli $mysqli, int $userId): void {
        $sql = "UPDATE notifications SET lu = 1 WHERE user_id = ? AND lu = 0";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("i", $userId);
            @$stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Notifie le client et le coiffeur lors d'une nouvelle demande de rendez-vous.
     */
    public static function notifyBookingCreated(mysqli $mysqli, int $rdvId): void {
        $details = self::fetchBookingDetails($mysqli, $rdvId);
        if (!$details) {
            return;
        }
        $dateFormatted = date('d/m/Y à H:i', strtotime($details['date_heure']));
        $lieu = $details['type_prestation'] === 'domicile'
            ? "À domicile (" . htmlspecialchars($details['adresse_domicile'] ?? '') . ")"
            : "Au salon";

        // Notification Client
        $clientTitle = "Demande de rendez-vous enregistrée";
        $clientMsg = "Votre demande de rendez-vous pour « {$details['service_nom']} » avec {$details['coiffeur_nom']} le {$dateFormatted} est en attente de confirmation.";
        self::createNotification($mysqli, (int) $details['client_user_id'], $clientTitle, $clientMsg);

        $clientHtml = "<p>Bonjour <strong>" . htmlspecialchars($details['client_nom']) . "</strong>,</p>"
            . "<p>Votre demande de rendez-vous a bien été enregistrée et est en attente de confirmation.</p>"
            . "<ul>"
            . "<li><strong>Prestation :</strong> " . htmlspecialchars($details['service_nom']) . " (" . (int) $details['duree_minutes_snapshot'] . " min)</li>"
            . "<li><strong>Coiffeur :</strong> " . htmlspecialchars($details['coiffeur_nom']) . "</li>"
            . "<li><strong>Date et heure :</strong> {$dateFormatted}</li>"
            . "<li><strong>Prix estimé :</strong> " . number_format((float) $details['prix_final'], 2, ',', ' ') . " $</li>"
            . "<li><strong>Lieu :</strong> {$lieu}</li>"
            . "</ul>";
        self::sendEmail($mysqli, $details['client_email'], $clientTitle . " - King and Qween", $clientHtml, 'booking_created_client');

        // Notification Coiffeur
        $coiffeurTitle = "Nouveau rendez-vous en attente";
        $coiffeurMsg = "Nouveau rendez-vous demandé par {$details['client_nom']} pour « {$details['service_nom']} » le {$dateFormatted}.";
        self::createNotification($mysqli, (int) $details['coiffeur_user_id'], $coiffeurTitle, $coiffeurMsg);
        if (!empty($details['coiffeur_email'])) {
            $coiffeurHtml = "<p>Bonjour <strong>" . htmlspecialchars($details['coiffeur_nom']) . "</strong>,</p>"
                . "<p>" . htmlspecialchars($coiffeurMsg) . "</p>";
            self::sendEmail($mysqli, $details['coiffeur_email'], $coiffeurTitle . " - King and Qween", $coiffeurHtml, 'booking_created_coiffeur');
        }
    }

    /**
     * Notifie lors d'un changement de statut (confirmation, annulation, finalisation).
     */
    public static function notifyBookingStatusChanged(mysqli $mysqli, int $rdvId, string $newStatus, ?string $motif = null): void {
        $details = self::fetchBookingDetails($mysqli, $rdvId);
        if (!$details) {
            return;
        }
        $dateFormatted = date('d/m/Y à H:i', strtotime($details['date_heure']));
        $motifText = ($motif !== null && trim($motif) !== '') ? " Motif : " . trim($motif) : "";

        if ($newStatus === 'confirmed') {
            $title = "Rendez-vous confirmé";
            $msg = "Votre rendez-vous pour « {$details['service_nom']} » avec {$details['coiffeur_nom']} le {$dateFormatted} est confirmé !";
            self::createNotification($mysqli, (int) $details['client_user_id'], $title, $msg);
            self::sendEmail(
                $mysqli,
                $details['client_email'],
                $title . " - King and Qween",
                "<p>Bonjour <strong>" . htmlspecialchars($details['client_nom']) . "</strong>,</p><p>" . htmlspecialchars($msg) . "</p>",
                'booking_confirmed'
            );
        } elseif ($newStatus === 'cancelled_by_client') {
            $title = "Rendez-vous annulé par le client";
            $msg = "Le client {$details['client_nom']} a annulé le rendez-vous « {$details['service_nom']} » prévu le {$dateFormatted}.{$motifText}";
            self::createNotification($mysqli, (int) $details['coiffeur_user_id'], $title, $msg);
            self::createNotification($mysqli, (int) $details['client_user_id'], "Confirmation d'annulation", "Votre rendez-vous du {$dateFormatted} a bien été annulé.");
            if (!empty($details['coiffeur_email'])) {
                self::sendEmail($mysqli, $details['coiffeur_email'], $title, "<p>" . htmlspecialchars($msg) . "</p>", 'booking_cancelled_by_client');
            }
        } elseif ($newStatus === 'cancelled_by_coiffeur') {
            $title = "Rendez-vous annulé";
            $msg = "Votre rendez-vous « {$details['service_nom']} » du {$dateFormatted} avec {$details['coiffeur_nom']} a été annulé.{$motifText} Vous pouvez le reprogrammer depuis votre espace client.";
            self::createNotification($mysqli, (int) $details['client_user_id'], $title, $msg);
            self::sendEmail($mysqli, $details['client_email'], $title . " - King and Qween", "<p>" . htmlspecialchars($msg) . "</p>", 'booking_cancelled_by_coiffeur');
        } elseif ($newStatus === 'completed') {
            $title = "Prestation terminée – Donnez votre avis !";
            $msg = "Merci de votre visite pour « {$details['service_nom']} » le {$dateFormatted}. Vous pouvez désormais laisser un avis sur votre prestation.";
            self::createNotification($mysqli, (int) $details['client_user_id'], $title, $msg);
            self::sendEmail($mysqli, $details['client_email'], $title, "<p>" . htmlspecialchars($msg) . "</p>", 'booking_completed');
        }
    }

    /**
     * Notifie lors d'une reprogrammation de rendez-vous.
     */
    public static function notifyBookingRescheduled(mysqli $mysqli, int $rdvId, string $oldDateHeure, string $newDateHeure): void {
        $details = self::fetchBookingDetails($mysqli, $rdvId);
        if (!$details) {
            return;
        }
        $oldFmt = date('d/m/Y à H:i', strtotime($oldDateHeure));
        $newFmt = date('d/m/Y à H:i', strtotime($newDateHeure));

        $title = "Rendez-vous reprogrammé";
        $clientMsg = "Votre rendez-vous « {$details['service_nom']} » avec {$details['coiffeur_nom']} initialement prévu le {$oldFmt} a été déplacé au {$newFmt} (en attente de confirmation).";
        self::createNotification($mysqli, (int) $details['client_user_id'], $title, $clientMsg);
        self::sendEmail($mysqli, $details['client_email'], $title . " - King and Qween", "<p>" . htmlspecialchars($clientMsg) . "</p>", 'booking_rescheduled');

        $coiffeurMsg = "Le rendez-vous de {$details['client_nom']} (« {$details['service_nom']} ») a été reprogrammé du {$oldFmt} au {$newFmt}.";
        self::createNotification($mysqli, (int) $details['coiffeur_user_id'], $title, $coiffeurMsg);
        if (!empty($details['coiffeur_email'])) {
            self::sendEmail($mysqli, $details['coiffeur_email'], $title . " - King and Qween", "<p>" . htmlspecialchars($coiffeurMsg) . "</p>", 'booking_rescheduled_coiffeur');
        }
    }

    /**
     * Récupère les informations complètes d'un rendez-vous pour les notifications.
     */
    private static function fetchBookingDetails(mysqli $mysqli, int $rdvId): ?array {
        $sql = "SELECT r.id, r.date_heure, r.type_prestation, r.adresse_domicile, r.telephone,
                       COALESCE(r.duree_minutes_snapshot, s.duree_minutes, 30) AS duree_minutes_snapshot,
                       COALESCE(r.prix_final, s.prix_depart) AS prix_final,
                       s.nom AS service_nom,
                       uc.id AS client_user_id, uc.username AS client_nom, uc.email AS client_email,
                       uco.id AS coiffeur_user_id, uco.username AS coiffeur_nom, uco.email AS coiffeur_email
                FROM rendezvous r
                JOIN services s ON r.service_id = s.id
                JOIN users uc ON r.client_id = uc.id
                JOIN coiffeurs c ON r.coiffeur_id = c.id
                JOIN users uco ON c.user_id = uco.id
                WHERE r.id = ?
                LIMIT 1";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("i", $rdvId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $stmt->close();
                return $row ?: null;
            }
            $stmt->close();
        }
        return null;
    }

    /**
     * Enveloppe le corps de l'email dans un gabarit HTML propre.
     */
    private static function wrapEmailTemplate(string $title, string $bodyHtml): string {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>'
            . '<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">'
            . '<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.08);">'
            . '<div style="background-color: #222; color: #d4af37; padding: 20px; text-align: center;">'
            . '<h2 style="margin: 0;">King and Qween</h2>'
            . '<p style="margin: 5px 0 0; color: #eee; font-size: 14px;">' . $safeTitle . '</p>'
            . '</div>'
            . '<div style="padding: 25px; color: #333; line-height: 1.6;">' . $bodyHtml . '</div>'
            . '<div style="background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #777;">'
            . '&copy; ' . date('Y') . ' King and Qween Salon. Tous droits réservés.'
            . '</div></div></body></html>';
    }
}
