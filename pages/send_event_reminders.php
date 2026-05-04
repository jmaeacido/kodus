<?php
// Run from cron, for example:
// php /opt/apps/crg-kodus/pages/send_event_reminders.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../base_url.php';
require_once __DIR__ . '/../mail_config.php';
require_once __DIR__ . '/../notification_helpers.php';
require_once __DIR__ . '/../app_notification_helpers.php';
require_once __DIR__ . '/sendEventEmails.php';

calendarEventGuestsEnsureSchema($conn);
app_notification_ensure_schema($conn);

if (!$conn->query("
    CREATE TABLE IF NOT EXISTS event_reminder_logs (
        id BIGINT NOT NULL AUTO_INCREMENT,
        event_id INT NOT NULL,
        reminder_key VARCHAR(40) NOT NULL,
        recipient_email VARCHAR(255) NOT NULL,
        user_id INT DEFAULT NULL,
        sent_at DATETIME NOT NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'sent',
        message TEXT DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_event_reminder_recipient (event_id, reminder_key, recipient_email),
        KEY idx_event_reminder_event (event_id),
        KEY idx_event_reminder_sent_at (sent_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
")) {
    throw new RuntimeException('Unable to ensure event reminder log table: ' . $conn->error);
}

function event_reminder_copy(string $title): string
{
    $variants = [
        'What are you planning for %s?',
        'A quick heads-up for %s.',
        '%s is coming up soon.',
        'Getting ready for %s?',
    ];
    return sprintf($variants[array_rand($variants)], $title);
}

function event_reminder_when(array $event): string
{
    $start = trim((string) ($event['start'] ?? ''));
    $end = trim((string) ($event['end'] ?? ''));
    if ($start === '') {
        return 'Schedule unavailable';
    }

    $startLabel = date('M j, Y g:i A', strtotime($start));
    if ($end === '') {
        return $startLabel;
    }

    return $startLabel . ' - ' . date('g:i A', strtotime($end));
}

$eventStmt = $conn->prepare("
    SELECT e.id, e.title, e.description, e.start, e.end, e.location, e.created_by,
           u.email AS creator_email, u.username AS creator_name, u.first_name AS creator_first_name
    FROM events e
    LEFT JOIN users u ON u.id = e.created_by
    WHERE e.deleted_at IS NULL
      AND e.start >= NOW()
      AND e.start <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
    ORDER BY e.start ASC
");
if (!$eventStmt) {
    throw new RuntimeException('Unable to prepare event reminder query: ' . $conn->error);
}
$eventStmt->execute();
$events = db_stmt_fetch_all_assoc($eventStmt);
$eventStmt->close();

$sent = 0;
$skipped = 0;

foreach ($events as $event) {
    $eventId = (int) ($event['id'] ?? 0);
    $title = trim((string) ($event['title'] ?? 'Event')) ?: 'Event';
    $reminderKey = '24h';
    $copy = event_reminder_copy($title);
    $when = event_reminder_when($event);

    $recipients = [];
    $createdAppNotification = false;
    $guestStmt = $conn->prepare("
        SELECT eg.user_id, eg.guest_email, eg.guest_name, u.first_name, u.username
        FROM event_guests eg
        LEFT JOIN users u ON u.id = eg.user_id
        WHERE eg.event_id = ?
    ");
    if ($guestStmt) {
        $guestStmt->bind_param('i', $eventId);
        $guestStmt->execute();
        foreach (db_stmt_fetch_all_assoc($guestStmt) as $row) {
            $email = strtolower(trim((string) ($row['guest_email'] ?? '')));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[$email] = [
                    'email' => $email,
                    'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
                    'name' => trim((string) ($row['first_name'] ?? '')) ?: trim((string) ($row['username'] ?? $row['guest_name'] ?? '')),
                ];
            }
        }
        $guestStmt->close();
    }

    $creatorEmail = strtolower(trim((string) ($event['creator_email'] ?? '')));
    if ($creatorEmail !== '' && filter_var($creatorEmail, FILTER_VALIDATE_EMAIL)) {
        $recipients[$creatorEmail] = [
            'email' => $creatorEmail,
            'user_id' => (int) ($event['created_by'] ?? 0),
            'name' => trim((string) ($event['creator_first_name'] ?? '')) ?: trim((string) ($event['creator_name'] ?? '')),
        ];
    }

    foreach ($recipients as $recipient) {
        $email = (string) $recipient['email'];
        $userId = !empty($recipient['user_id']) ? (int) $recipient['user_id'] : null;

        $guardStmt = $conn->prepare("
            INSERT IGNORE INTO event_reminder_logs (event_id, reminder_key, recipient_email, user_id, sent_at, status, message)
            VALUES (?, ?, ?, ?, NOW(), 'queued', ?)
        ");
        if (!$guardStmt) {
            continue;
        }
        $guardStmt->bind_param('issis', $eventId, $reminderKey, $email, $userId, $copy);
        $guardStmt->execute();
        $queued = $guardStmt->affected_rows > 0;
        $guardStmt->close();
        if (!$queued) {
            $skipped++;
            continue;
        }

        if ($userId && !$createdAppNotification) {
            app_notification_create($conn, [
                'category' => 'event_reminder',
                'title' => 'Upcoming event',
                'message' => $copy . ' ' . $when,
                'url' => app_url('pages/calendar.php'),
                'icon_class' => 'far fa-calendar-check',
                'color_class' => 'text-primary',
                'actor_user_id' => (int) ($event['created_by'] ?? 0),
                'actor_name' => 'KODUS Calendar',
            ]);
            $createdAppNotification = true;
        }

        $mail = new PHPMailer(true);
        $status = 'sent';
        $statusMessage = 'Reminder sent';
        try {
            app_configure_mailer($mail);
            $mail->addAddress($email, (string) ($recipient['name'] ?? ''));
            $mail->isHTML(true);
            $mail->Subject = 'Upcoming event: ' . $title;
            $mail->Body = notification_render_email_shell(
                'Event Reminder',
                $title,
                $copy,
                '<p>' . htmlspecialchars($copy, ENT_QUOTES, 'UTF-8') . '</p>'
                . notification_render_detail_rows([
                    'Event' => $title,
                    'When' => $when,
                    'Location' => (string) ($event['location'] ?? ''),
                ]),
                '#2563eb',
                'KODUS Calendar'
            );
            $mail->AltBody = $copy . "\nEvent: {$title}\nWhen: {$when}";
            $mail->send();
            notification_log_mail($conn, $email, $mail->Subject, 'sent', $statusMessage);
            $sent++;
        } catch (Exception $e) {
            $status = 'failed';
            $statusMessage = $mail->ErrorInfo ?: $e->getMessage();
            notification_log_mail($conn, $email, 'Upcoming event: ' . $title, 'failed', $statusMessage);
        }

        $updateStmt = $conn->prepare("
            UPDATE event_reminder_logs
            SET status = ?, message = ?, sent_at = NOW()
            WHERE event_id = ? AND reminder_key = ? AND recipient_email = ?
        ");
        if ($updateStmt) {
            $updateStmt->bind_param('ssiss', $status, $statusMessage, $eventId, $reminderKey, $email);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }
}

if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
}

echo json_encode(['success' => true, 'sent' => $sent, 'skipped_duplicates' => $skipped]) . PHP_EOL;
