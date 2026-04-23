<?php
// sendEventEmails.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/../mail_config.php';
require_once __DIR__ . '/../notification_helpers.php';

function calendarEventGuestsEnsureSchema(mysqli $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    if (!$conn->query("
        CREATE TABLE IF NOT EXISTS event_guests (
            id BIGINT NOT NULL AUTO_INCREMENT,
            event_id INT NOT NULL,
            user_id INT DEFAULT NULL,
            guest_email VARCHAR(255) NOT NULL,
            guest_name VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_event_guest (event_id, guest_email),
            KEY idx_event_guest_user (user_id),
            CONSTRAINT fk_event_guest_event
                FOREIGN KEY (event_id) REFERENCES events (id)
                ON DELETE CASCADE,
            CONSTRAINT fk_event_guest_user
                FOREIGN KEY (user_id) REFERENCES users (id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ")) {
        throw new RuntimeException('Unable to ensure event guests table: ' . $conn->error);
    }

    $ensured = true;
}

function calendarParseGuestEmails(string $rawGuests): array {
    $parts = preg_split('/[\s,;]+/', $rawGuests, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $emails = [];

    foreach ($parts as $part) {
        $email = filter_var(trim($part), FILTER_VALIDATE_EMAIL);
        if ($email) {
            $emails[] = strtolower($email);
        }
    }

    return array_values(array_unique($emails));
}

function calendarResolveGuestRows(mysqli $conn, array $emails): array
{
    $emails = array_values(array_unique(array_filter(array_map(static fn($value): string => strtolower(trim((string) $value)), $emails))));
    if ($emails === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($emails), '?'));
    $types = str_repeat('s', count($emails));
    $stmt = $conn->prepare("SELECT id, email, username FROM users WHERE LOWER(email) IN ($placeholders)");
    $indexedUsers = [];

    if ($stmt) {
        $stmt->bind_param($types, ...$emails);
        $stmt->execute();
        foreach (db_stmt_fetch_all_assoc($stmt) as $row) {
            $indexedUsers[strtolower((string) ($row['email'] ?? ''))] = $row;
        }
        $stmt->close();
    }

    $rows = [];
    foreach ($emails as $email) {
        $matchedUser = $indexedUsers[$email] ?? null;
        $rows[] = [
            'email' => $email,
            'username' => (string) ($matchedUser['username'] ?? $email),
            'user_id' => isset($matchedUser['id']) ? (int) $matchedUser['id'] : null,
        ];
    }

    return $rows;
}

function calendarSyncEventGuests(mysqli $conn, int $eventId, array $guestEmails): void
{
    calendarEventGuestsEnsureSchema($conn);

    $deleteStmt = $conn->prepare('DELETE FROM event_guests WHERE event_id = ?');
    if ($deleteStmt) {
        $deleteStmt->bind_param('i', $eventId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }

    $guestRows = calendarResolveGuestRows($conn, $guestEmails);
    if ($guestRows === []) {
        return;
    }

    $insertStmt = $conn->prepare("
        INSERT INTO event_guests (event_id, user_id, guest_email, guest_name)
        VALUES (?, ?, ?, ?)
    ");

    if (!$insertStmt) {
        throw new RuntimeException('Unable to sync event guests: ' . $conn->error);
    }

    foreach ($guestRows as $guestRow) {
        $eventIdParam = $eventId;
        $userId = $guestRow['user_id'];
        $guestEmail = (string) ($guestRow['email'] ?? '');
        $guestName = (string) ($guestRow['username'] ?? '');
        $insertStmt->bind_param('iiss', $eventIdParam, $userId, $guestEmail, $guestName);
        $insertStmt->execute();
    }

    $insertStmt->close();
}

function calendarFetchGuestsByEventIds(mysqli $conn, array $eventIds): array
{
    calendarEventGuestsEnsureSchema($conn);

    $eventIds = array_values(array_filter(array_map('intval', $eventIds), static fn(int $value): bool => $value > 0));
    if ($eventIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    $types = str_repeat('i', count($eventIds));
    $stmt = $conn->prepare("
        SELECT event_id, guest_email
        FROM event_guests
        WHERE event_id IN ($placeholders)
        ORDER BY event_id ASC, guest_email ASC
    ");

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$eventIds);
    $stmt->execute();
    $rows = db_stmt_fetch_all_assoc($stmt);
    $stmt->close();

    $grouped = [];
    foreach ($rows as $row) {
        $grouped[(int) ($row['event_id'] ?? 0)][] = (string) ($row['guest_email'] ?? '');
    }

    return array_map(static fn(array $emails): string => implode(', ', array_filter($emails)), $grouped);
}

function calendarGuestAuditLabel(array $guestEmails): string
{
    $guestEmails = array_values(array_unique(array_filter(array_map(static fn($value): string => strtolower(trim((string) $value)), $guestEmails))));
    return implode(', ', $guestEmails);
}

function calendarFormatEventScheduleForEmail(array $eventData): string {
    $startRaw = (string) ($eventData['start'] ?? '');
    $endRaw = (string) ($eventData['end'] ?? '');
    $isAllDay = !empty($eventData['allDay']);

    if ($startRaw === '') {
        return '';
    }

    if ($isAllDay) {
        $start = substr($startRaw, 0, 10);
        $end = $endRaw !== '' ? substr($endRaw, 0, 10) : $start;

        if ($end !== '') {
            $inclusiveEnd = date_create($end);
            if ($inclusiveEnd) {
                $inclusiveEnd->modify('-1 day');
                $end = $inclusiveEnd->format('Y-m-d');
            }
        }

        $displayEnd = $end !== '' ? $end : $start;
        if ($displayEnd === $start) {
            return $start;
        }

        return $start . ' - ' . $displayEnd;
    }

    return $startRaw . ' - ' . $endRaw;
}

function sendEventEmails($emails, $eventData, $mode = 'new') {
    $mail = new PHPMailer(true);
    $guestList = implode(', ', array_values(array_unique(array_filter(array_map('trim', $emails)))));
    $scheduleDisplay = calendarFormatEventScheduleForEmail((array) $eventData);

    try {
        app_configure_mailer($mail);

        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mail->addAddress($email);
            }
        }

        $mail->isHTML(true);

        // ============================
        // Email subject + body
        // ============================
        if ($mode === 'new') {
            $mail->Subject = "You're invited: {$eventData['title']}";
            $mail->Body = notification_render_email_shell(
                'Calendar Invitation',
                (string) $eventData['title'],
                'You have been invited to a KODUS calendar event.',
                '<p>You have been invited to the event below.</p>'
                . notification_render_detail_rows([
                    'Description' => (string) $eventData['description'],
                    'When' => $scheduleDisplay,
                    'Location' => (string) $eventData['location'],
                    'Guests' => $guestList !== '' ? $guestList : 'None',
                    'Created By' => (string) $eventData['createdBy'],
                ]),
                '#2563eb',
                'KODUS Calendar'
            );
        } else {
            $mail->Subject = "Event updated: {$eventData['title']}";
            $mail->Body = notification_render_email_shell(
                'Calendar Update',
                'Updated Event: ' . (string) $eventData['title'],
                'An event in your KODUS calendar has been updated.',
                '<p>Please review the latest event details below.</p>'
                . notification_render_detail_rows([
                    'Description' => (string) $eventData['description'],
                    'New Schedule' => $scheduleDisplay,
                    'Location' => (string) $eventData['location'],
                    'Guests' => $guestList !== '' ? $guestList : 'None',
                    'Updated By' => (string) $eventData['createdBy'],
                ]),
                '#198754',
                'KODUS Calendar'
            );
        }

        // ============================
        // ICS Attachment
        // ============================

        // Convert to UTC + correct ICS format
        $dtStart = (new DateTime($eventData['start']))->format('Ymd\THis\Z');
        $dtEnd   = (new DateTime($eventData['end']))->format('Ymd\THis\Z');
        $uid     = uniqid();

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//YourApp//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:REQUEST\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:$uid\r\n";
        $ics .= "SUMMARY:{$eventData['title']}\r\n";
        $ics .= "DESCRIPTION:" . preg_replace("/\r\n|\r|\n/", "\\n", $eventData['description']) . "\r\n";
        $ics .= "DTSTART:$dtStart\r\n";
        $ics .= "DTEND:$dtEnd\r\n";
        $ics .= "LOCATION:{$eventData['location']}\r\n";
        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "SEQUENCE:0\r\n";
        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        // Attach as .ics
        $mail->addStringAttachment($ics, "event.ics", "base64", "text/calendar");

        $mail->send();

    } catch (Exception $e) {
        error_log("Email error: {$mail->ErrorInfo}");
    }
}
