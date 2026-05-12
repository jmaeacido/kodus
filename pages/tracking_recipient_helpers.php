<?php

require_once __DIR__ . '/../notification_helpers.php';
require_once __DIR__ . '/../app_notification_helpers.php';
require_once __DIR__ . '/../socket_helpers.php';
require_once __DIR__ . '/../inbox/mailbox_helpers.php';

function tracking_recipient_label(array $row): string
{
    $fullName = trim(
        (string) ($row['first_name'] ?? '') . ' ' .
        (string) ($row['last_name'] ?? '')
    );
    $username = trim((string) ($row['username'] ?? ''));
    $email = trim((string) ($row['email'] ?? ''));
    $name = $fullName !== '' ? $fullName : ($username !== '' ? $username : $email);

    return $email !== '' && strcasecmp($name, $email) !== 0
        ? "{$name} <{$email}>"
        : $name;
}

function tracking_fetch_recipient_options(mysqli $conn): array
{
    $options = [];
    $result = $conn->query("
        SELECT id, username, first_name, last_name, email
        FROM users
        WHERE email IS NOT NULL
          AND TRIM(email) <> ''
          AND deleted_at IS NULL
        ORDER BY COALESCE(NULLIF(first_name, ''), username, email), last_name, email
    ");

    if (!$result) {
        return $options;
    }

    while ($row = $result->fetch_assoc()) {
        $label = tracking_recipient_label($row);
        if ($label === '') {
            continue;
        }

        $options[] = [
            'id' => (int) ($row['id'] ?? 0),
            'label' => $label,
            'name' => trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')) ?: (string) ($row['username'] ?? ''),
            'email' => trim((string) ($row['email'] ?? '')),
        ];
    }

    $result->close();
    return $options;
}

function tracking_normalize_recipient_inputs(array $post): array
{
    $rawRecipients = $post['receiving_office_recipients'] ?? [];
    if (!is_array($rawRecipients)) {
        $rawRecipients = [$rawRecipients];
    }

    if ($rawRecipients === []) {
        $fallback = trim((string) ($post['receiving_office'] ?? ''));
        if ($fallback !== '') {
            $rawRecipients = preg_split('/[;,]+/', $fallback) ?: [$fallback];
        }
    }

    $labels = [];
    $emails = [];
    $seenLabels = [];
    $seenEmails = [];

    foreach ($rawRecipients as $rawRecipient) {
        $value = trim(preg_replace('/\s+/', ' ', (string) $rawRecipient));
        if ($value === '') {
            continue;
        }

        $labelKey = strtolower($value);
        if (!isset($seenLabels[$labelKey])) {
            $seenLabels[$labelKey] = true;
            $labels[] = $value;
        }

        if (preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches)) {
            foreach ($matches[0] as $candidate) {
                $email = strtolower(trim($candidate));
                if (filter_var($email, FILTER_VALIDATE_EMAIL) && !isset($seenEmails[$email])) {
                    $seenEmails[$email] = true;
                    $emails[] = $email;
                }
            }
        }
    }

    return [
        'labels' => $labels,
        'display' => implode(', ', $labels),
        'emails' => $emails,
    ];
}

function tracking_send_document_recipient_emails(mysqli $conn, array $emails, array $document): array
{
    $emails = array_values(array_unique(array_filter($emails, static function ($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    })));

    if ($emails === []) {
        return ['sent' => 0, 'failed' => 0, 'errors' => []];
    }

    $trackingNumber = trim((string) ($document['tracking_number'] ?? ''));
    $description = trim((string) ($document['description'] ?? ''));
    $remarks = trim((string) ($document['remarks'] ?? ''));
    $receivingOffice = trim((string) ($document['receiving_office'] ?? ''));
    $dateForwarded = trim((string) ($document['date_forwarded'] ?? ''));
    $context = trim((string) ($document['context'] ?? 'Outgoing document'));
    $url = trim((string) ($document['url'] ?? app_notification_build_url('pages/data-tracking-out')));
    $actor = app_notification_actor_name_from_session();
    $subjectTracking = $trackingNumber !== '' ? " {$trackingNumber}" : '';
    $subject = "KODUS {$context}{$subjectTracking}";

    $rows = [
        'Tracking Number' => $trackingNumber !== '' ? $trackingNumber : 'Pending',
        'Description' => $description !== '' ? $description : 'No description provided',
        'Receiving Office / Personnel' => $receivingOffice !== '' ? $receivingOffice : 'Not specified',
        'Forwarded / Outgoing Date' => $dateForwarded !== '' ? $dateForwarded : 'Not specified',
        'Recorded By' => $actor,
    ];

    if ($remarks !== '') {
        $rows['Remarks'] = $remarks;
    }

    $body = '<p>Hello,</p>'
        . '<p>A document has been routed to you or your office in KODUS.</p>'
        . notification_render_detail_rows($rows)
        . notification_render_action_button($url, 'Open KODUS');

    $sent = 0;
    $failed = 0;
    $errors = [];

    foreach ($emails as $email) {
        try {
            $mail = notification_create_mailer();
            $mail->addAddress($email);
            $mail->Subject = $subject;
            $mail->Body = notification_render_email_shell(
                'Document Routing Notice',
                $context,
                'A KODUS document was routed with you listed as a recipient.',
                $body,
                '#0d6efd',
                'KODUS Document Tracking'
            );
            $mail->AltBody = "A KODUS document was routed to you.\n\n"
                . "Tracking Number: " . ($trackingNumber !== '' ? $trackingNumber : 'Pending') . "\n"
                . "Description: " . ($description !== '' ? $description : 'No description provided') . "\n"
                . "Receiving Office / Personnel: " . ($receivingOffice !== '' ? $receivingOffice : 'Not specified') . "\n"
                . "Open KODUS: " . notification_email_url($url);
            $mail->send();
            $sent++;
            notification_log_mail($conn, $email, $subject, 'sent', 'Document routing notice sent.');
        } catch (Throwable $e) {
            $failed++;
            $errors[] = $email . ': ' . $e->getMessage();
            notification_log_mail($conn, $email, $subject, 'failed', $e->getMessage());
        }
    }

    return ['sent' => $sent, 'failed' => $failed, 'errors' => $errors];
}

function tracking_fetch_kodus_recipients_by_email(mysqli $conn, array $emails): array
{
    $emails = array_values(array_unique(array_filter(array_map(static function ($email) {
        $email = strtolower(trim((string) $email));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }, $emails))));

    if ($emails === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($emails), '?'));
    $stmt = $conn->prepare("
        SELECT id, username, first_name, last_name, email
        FROM users
        WHERE LOWER(email) IN ({$placeholders})
          AND deleted_at IS NULL
    ");

    if (!$stmt) {
        return [];
    }

    $types = str_repeat('s', count($emails));
    $stmt->bind_param($types, ...$emails);
    $stmt->execute();
    $rows = db_stmt_fetch_all_assoc($stmt);
    $stmt->close();

    $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
    $recipients = [];
    $seen = [];

    foreach ($rows as $row) {
        $userId = (int) ($row['id'] ?? 0);
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        if ($userId <= 0 || $email === '' || $userId === $currentUserId || isset($seen[$email])) {
            continue;
        }

        $seen[$email] = true;
        $displayName = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
        $recipients[] = [
            'user_id' => $userId,
            'email' => $email,
            'username' => $displayName !== '' ? $displayName : (trim((string) ($row['username'] ?? '')) ?: $email),
        ];
    }

    return $recipients;
}

function tracking_send_document_recipient_kodus_alerts(mysqli $conn, array $emails, array $document): array
{
    $notificationCount = 0;
    $messageId = 0;

    try {
        $recipients = tracking_fetch_kodus_recipients_by_email($conn, $emails);

        if ($recipients === []) {
            return ['notifications' => 0, 'messages' => 0, 'message_id' => 0];
        }

        $trackingNumber = trim((string) ($document['tracking_number'] ?? ''));
        $description = trim((string) ($document['description'] ?? ''));
        $remarks = trim((string) ($document['remarks'] ?? ''));
        $receivingOffice = trim((string) ($document['receiving_office'] ?? ''));
        $dateForwarded = trim((string) ($document['date_forwarded'] ?? ''));
        $context = trim((string) ($document['context'] ?? 'Outgoing document'));
        $url = trim((string) ($document['url'] ?? app_notification_build_url('pages/data-tracking-out')));
        $actor = app_notification_actor_name_from_session();
        $actorUserId = (int) ($_SESSION['user_id'] ?? 0);
        $senderEmail = trim((string) ($_SESSION['email'] ?? ''));
        $senderName = trim((string) ($_SESSION['username'] ?? $actor)) ?: $actor;
        $subjectTracking = $trackingNumber !== '' ? " {$trackingNumber}" : '';
        $subject = "KODUS {$context}{$subjectTracking}";

        $messageLines = [
            "A document has been routed to you or your office in KODUS.",
            "",
            "Tracking Number: " . ($trackingNumber !== '' ? $trackingNumber : 'Pending'),
            "Description: " . ($description !== '' ? $description : 'No description provided'),
            "Receiving Office / Personnel: " . ($receivingOffice !== '' ? $receivingOffice : 'Not specified'),
            "Forwarded / Outgoing Date: " . ($dateForwarded !== '' ? $dateForwarded : 'Not specified'),
            "Recorded By: {$actor}",
        ];

        if ($remarks !== '') {
            $messageLines[] = "Remarks: {$remarks}";
        }

        $messageLines[] = "";
        $messageLines[] = "Open KODUS: {$url}";
        $message = implode("\n", $messageLines);

        foreach ($recipients as $recipient) {
            $notificationId = app_notification_create($conn, [
                'category' => 'document_tracking',
                'title' => $subject,
                'message' => "{$actor} routed a document to you or your office.",
                'url' => $url,
                'icon_class' => 'fas fa-share',
                'color_class' => 'text-primary',
                'actor_user_id' => $actorUserId,
                'target_user_id' => (int) $recipient['user_id'],
                'actor_name' => $actor,
            ]);
            if ($notificationId > 0) {
                $notificationCount++;
            }
        }

        mailboxEnsureSchema($conn);

        $stmt = $conn->prepare("
            INSERT INTO contact_messages (user_email, user_name, subject, message, attachment, sent_at)
            VALUES (?, ?, ?, ?, NULL, NOW())
        ");

        if ($stmt) {
            $stmt->bind_param('ssss', $senderEmail, $senderName, $subject, $message);
            if ($stmt->execute()) {
                $messageId = (int) $stmt->insert_id;
            }
            $stmt->close();
        }

        if ($messageId > 0) {
            mailboxSyncMessageRecipients($conn, $messageId, $recipients);

            if ($actorUserId > 0) {
                $readStmt = $conn->prepare("
                    INSERT INTO message_reads (message_id, user_id, is_read, read_at, last_read_reply_id)
                    VALUES (?, ?, 1, NOW(), 0)
                    ON DUPLICATE KEY UPDATE is_read = 1, read_at = NOW(), last_read_reply_id = 0
                ");
                if ($readStmt) {
                    $readStmt->bind_param('ii', $messageId, $actorUserId);
                    $readStmt->execute();
                    $readStmt->close();
                }
            }

            kodus_socket_broadcast('kodus.mailbox', 'mail.changed', [
                'action' => 'message_created',
                'message_id' => $messageId,
                'actor_id' => $actorUserId,
                'recipient_count' => count($recipients),
            ]);
        }

        return [
            'notifications' => $notificationCount,
            'messages' => $messageId > 0 ? count($recipients) : 0,
            'message_id' => $messageId,
        ];
    } catch (Throwable $e) {
        return [
            'notifications' => $notificationCount,
            'messages' => 0,
            'message_id' => $messageId,
            'error' => $e->getMessage(),
        ];
    }
}

function tracking_finish_json_response_then_send_document_recipient_notices(mysqli $conn, array $response, array $emails, array $document): void
{
    $response['mail_queued'] = count(array_unique(array_filter($emails))) > 0;
    $payload = json_encode($response);

    if (!headers_sent()) {
        header('Content-Length: ' . strlen($payload));
        header('Connection: close');
    }

    echo $payload;

    if (function_exists('session_write_close')) {
        session_write_close();
    }

    ignore_user_abort(true);

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @flush();
    }

    tracking_send_document_recipient_emails($conn, $emails, $document);
    tracking_send_document_recipient_kodus_alerts($conn, $emails, $document);
}
