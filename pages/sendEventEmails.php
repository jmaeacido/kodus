<?php
// sendEventEmails.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/../mail_config.php';
require_once __DIR__ . '/../notification_helpers.php';

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
