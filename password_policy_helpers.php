<?php

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/notification_helpers.php';
require_once __DIR__ . '/env_helpers.php';
require_once __DIR__ . '/base_url.php';
require_once __DIR__ . '/socket_helpers.php';

function password_policy_current_version(): int
{
    return 2;
}

function password_policy_cutoff(): string
{
    return app_env('PASSWORD_POLICY_CUTOFF', '2026-03-26 00:00:00') ?? '2026-03-26 00:00:00';
}

function password_policy_reset_expiry(): string
{
    return date('Y-m-d H:i:s', strtotime('+24 hours'));
}

function password_policy_application_url(): string
{
    $urls = password_policy_application_urls();
    return $urls[0] ?? 'http://localhost';
}

function password_policy_application_urls(): array
{
    global $base_url;

    $urls = [];
    $appendUrl = static function (?string $value) use (&$urls): void {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return;
        }

        $normalized = rtrim($value, '/');
        if (!preg_match('#^https?://#i', $normalized)) {
            return;
        }

        if (!in_array($normalized, $urls, true)) {
            $urls[] = $normalized;
        }
    };

    $appendUrl(app_env('APP_URL'));
    if ($urls !== []) {
        return $urls;
    }

    $aliasList = app_env('APP_URL_ALIASES', '') ?? '';
    if ($aliasList !== '') {
        foreach (preg_split('/[\r\n,]+/', $aliasList) as $aliasUrl) {
            $appendUrl($aliasUrl);
        }
    }

    $baseUrl = trim((string) ($base_url ?? ''));
    if ($baseUrl !== '' && preg_match('#^https?://#i', $baseUrl)) {
        $appendUrl($baseUrl);
    }

    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== '') {
        $scheme = (!security_is_local_host(security_request_host()) || security_is_https()) ? 'https://' : 'http://';
        $appendUrl($scheme . $host . '/' . ltrim($baseUrl, '/'));
    }

    if ($urls === []) {
        $urls[] = 'http://localhost' . ($baseUrl !== '' ? '/' . trim($baseUrl, '/') : '');
    }

    return $urls;
}

function password_policy_link_label(string $appUrl): string
{
    $host = parse_url($appUrl, PHP_URL_HOST) ?: $appUrl;
    $primaryHost = parse_url(password_policy_application_url(), PHP_URL_HOST) ?: '';

    if ($primaryHost !== '' && strcasecmp($host, $primaryHost) !== 0) {
        return 'Update Password Now (Alternate Link)';
    }

    return 'Update Password Now';
}

function password_policy_build_reset_links(string $token, bool $enforced = true): array
{
    $suffix = $enforced ? '&enforced=1' : '';
    $links = [];

    foreach (password_policy_application_urls() as $appUrl) {
        $links[] = [
            'url' => rtrim($appUrl, '/') . '/reset-password.php?token=' . urlencode($token) . $suffix,
            'label' => password_policy_link_label($appUrl),
        ];
    }

    return $links;
}

function password_policy_ensure_schema(mysqli $conn): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    $columns = [
        'password_policy_version' => "ALTER TABLE users ADD COLUMN password_policy_version INT NOT NULL DEFAULT 0 AFTER password",
        'password_changed_at' => "ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL DEFAULT NULL AFTER password_policy_version",
        'must_change_password' => "ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_changed_at",
        'password_strength_notified_at' => "ALTER TABLE users ADD COLUMN password_strength_notified_at DATETIME NULL DEFAULT NULL AFTER must_change_password",
    ];

    foreach ($columns as $column => $sql) {
        $result = $conn->query("SHOW COLUMNS FROM users LIKE '{$column}'");
        if ($result && $result->num_rows > 0) {
            continue;
        }

        $conn->query($sql);
    }

    $cutoff = password_policy_cutoff();
    $version = password_policy_current_version();

    $stmt = $conn->prepare(
        'UPDATE users
         SET password_policy_version = ?, must_change_password = 0, password_changed_at = COALESCE(password_changed_at, date_registered)
         WHERE deleted_at IS NULL AND date_registered >= ? AND password_policy_version = 0'
    );
    if ($stmt) {
        $stmt->bind_param('is', $version, $cutoff);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare(
        'UPDATE users
         SET must_change_password = 1
         WHERE deleted_at IS NULL AND date_registered < ? AND password_policy_version < ?'
    );
    if ($stmt) {
        $stmt->bind_param('si', $cutoff, $version);
        $stmt->execute();
        $stmt->close();
    }
}

function password_policy_needs_upgrade(array $user): bool
{
    $currentVersion = password_policy_current_version();
    $policyVersion = isset($user['password_policy_version']) ? (int) $user['password_policy_version'] : 0;
    $mustChangePassword = !empty($user['must_change_password']);

    return $mustChangePassword || $policyVersion < $currentVersion;
}

function password_policy_mark_compliant(mysqli $conn, int $userId): void
{
    password_policy_ensure_schema($conn);

    $now = date('Y-m-d H:i:s');
    $version = password_policy_current_version();

    $stmt = $conn->prepare(
        'UPDATE users
         SET password_policy_version = ?, password_changed_at = ?, must_change_password = 0
         WHERE id = ?'
    );
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('isi', $version, $now, $userId);
    $stmt->execute();
    $stmt->close();
}

function password_policy_prepare_reset(mysqli $conn, int $userId): ?string
{
    $token = bin2hex(random_bytes(32));
    $hashedToken = security_hash_token($token);
    $expiry = password_policy_reset_expiry();

    $stmt = $conn->prepare(
        'UPDATE users
         SET reset_token = ?, reset_token_expiry = ?, must_change_password = 1, remember_token = NULL
         WHERE id = ?'
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ssi', $hashedToken, $expiry, $userId);
    $stmt->execute();
    $stmt->close();

    return $token;
}

function password_policy_build_reset_link(string $token): string
{
    $links = password_policy_build_reset_links($token, true);
    return $links[0]['url'] ?? (rtrim(password_policy_application_url(), '/') . '/reset-password.php?token=' . urlencode($token) . '&enforced=1');
}

function password_policy_in_app_sender_email(): string
{
    return app_env('SMTP_FROM_ADDRESS', app_env('SMTP_USERNAME', 'no-reply@kodus.local') ?? 'no-reply@kodus.local') ?? 'no-reply@kodus.local';
}

function password_policy_in_app_sender_name(): string
{
    return app_env('SMTP_FROM_NAME', 'KODUS Security Center') ?? 'KODUS Security Center';
}

function password_policy_stmt_has_rows(mysqli_stmt $stmt): bool
{
    if (!$stmt->store_result()) {
        return false;
    }

    return $stmt->num_rows > 0;
}

function password_policy_fetch_sender_user_id(mysqli_stmt $stmt): ?int
{
    if (!$stmt->store_result() || $stmt->num_rows < 1) {
        return null;
    }

    $userId = null;
    $stmt->bind_result($userId);

    if (!$stmt->fetch() || !is_numeric($userId)) {
        return null;
    }

    return (int) $userId;
}

function password_policy_has_in_app_notice(mysqli $conn, int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }

    $subject = 'Action Recommended: Update Your KODUS Password';
    $senderEmail = password_policy_in_app_sender_email();

    $stmt = $conn->prepare(
        'SELECT 1
         FROM contact_messages cm
         JOIN users u ON u.email = cm.recipient
         WHERE u.id = ?
           AND cm.user_email = ?
           AND cm.subject = ?
         LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('iss', $userId, $senderEmail, $subject);
    $stmt->execute();
    $exists = password_policy_stmt_has_rows($stmt);
    $stmt->close();

    return $exists;
}

function password_policy_send_in_app_notice(mysqli $conn, array $user, array $resetLinks, bool $force = true): bool
{
    $recipientEmail = trim((string) ($user['email'] ?? ''));
    $recipientUserId = isset($user['id']) ? (int) $user['id'] : 0;
    if ($recipientEmail === '' || $recipientUserId <= 0) {
        return false;
    }

    if (!$force && password_policy_has_in_app_notice($conn, $recipientUserId)) {
        return false;
    }

    $senderEmail = password_policy_in_app_sender_email();
    $senderName = password_policy_in_app_sender_name();
    $subject = 'Action Recommended: Update Your KODUS Password';

    $lines = [
        'Hello ' . trim((string) ($user['first_name'] ?? 'User')) . ',',
        '',
        'Your KODUS account may still be using an older password that does not meet the current security policy.',
        'Please update your password as soon as possible.',
        '',
        'Requirements:',
        '- At least ' . security_password_min_length() . ' characters',
        '- Uppercase letter',
        '- Lowercase letter',
        '- Number',
        '- Symbol',
        '',
        'Update links:',
    ];

    foreach ($resetLinks as $link) {
        $label = trim((string) ($link['label'] ?? 'Update Password Now'));
        $url = trim((string) ($link['url'] ?? ''));
        if ($url === '') {
            continue;
        }

        $lines[] = '- ' . $label . ': ' . $url;
    }

    $lines[] = '';
    $lines[] = 'If one host is unavailable, try the alternate link.';

    $message = implode("\n", $lines);

    $stmt = $conn->prepare(
        'INSERT INTO contact_messages (user_email, user_name, subject, message, recipient, attachment, sent_at)
         VALUES (?, ?, ?, ?, ?, NULL, NOW())'
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('sssss', $senderEmail, $senderName, $subject, $message, $recipientEmail);
    $stmt->execute();
    $messageId = (int) $stmt->insert_id;
    $stmt->close();

    if ($messageId <= 0) {
        return false;
    }

    $senderStmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
    if ($senderStmt) {
        $senderStmt->bind_param('s', $senderEmail);
        $senderStmt->execute();
        $senderUserId = password_policy_fetch_sender_user_id($senderStmt);
        $senderStmt->close();

        if ($senderUserId !== null) {
            $readStmt = $conn->prepare(
                'INSERT INTO message_reads (message_id, user_id, is_read, read_at)
                 VALUES (?, ?, 1, NOW())
                 ON DUPLICATE KEY UPDATE is_read = 1, read_at = NOW()'
            );
            if ($readStmt) {
                $readStmt->bind_param('ii', $messageId, $senderUserId);
                $readStmt->execute();
                $readStmt->close();
            }
        }
    }

    $recipientReadStmt = $conn->prepare(
        'INSERT INTO message_reads (message_id, user_id, is_read, read_at)
         VALUES (?, ?, 0, NULL)
         ON DUPLICATE KEY UPDATE is_read = 0, read_at = NULL, is_trashed = 0, trashed_at = NULL'
    );
    if ($recipientReadStmt) {
        $recipientReadStmt->bind_param('ii', $messageId, $recipientUserId);
        $recipientReadStmt->execute();
        $recipientReadStmt->close();
    }

    kodus_socket_broadcast('kodus.mailbox', 'mail.changed', [
        'action' => 'message_created',
        'message_id' => $messageId,
        'actor_id' => $senderUserId ?? 0,
        'recipient_ids' => [$recipientUserId],
        'source' => 'password_policy',
    ]);

    return true;
}

function password_policy_backfill_in_app_notices(mysqli $conn): array
{
    $stmt = $conn->prepare(
        'SELECT * FROM users
         WHERE deleted_at IS NULL
           AND password_strength_notified_at IS NOT NULL
         ORDER BY password_strength_notified_at ASC, id ASC'
    );
    if (!$stmt) {
        return ['created' => 0, 'skipped' => 0];
    }

    $stmt->execute();
    $users = db_stmt_fetch_all_assoc($stmt);
    $created = 0;
    $skipped = 0;

    foreach ($users as $user) {
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            $skipped++;
            continue;
        }

        if (password_policy_has_in_app_notice($conn, $userId)) {
            $skipped++;
            continue;
        }

        $token = password_policy_prepare_reset($conn, $userId);
        if ($token === null) {
            $skipped++;
            continue;
        }

        $resetLinks = password_policy_build_reset_links($token, true);
        if (password_policy_send_in_app_notice($conn, $user, $resetLinks, true)) {
            $created++;
        } else {
            $skipped++;
        }
    }

    $stmt->close();

    return [
        'created' => $created,
        'skipped' => $skipped,
    ];
}

function password_policy_send_advisory(mysqli $conn, array $user, string $resetLink, array $resetLinks = []): array
{
    $subject = 'Action Recommended: Update Your KODUS Password';
    $status = 'failed';
    $message = 'Password advisory was not sent.';

    $fullName = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
    $safeFirstName = htmlspecialchars((string) ($user['first_name'] ?? 'User'), ENT_QUOTES, 'UTF-8');
    $linksForEmail = $resetLinks !== [] ? $resetLinks : [[
        'url' => $resetLink,
        'label' => 'Update Password Now',
    ]];

    try {
        password_policy_send_in_app_notice($conn, $user, $linksForEmail);

        $mail = notification_create_mailer();
        $mail->addAddress((string) $user['email'], $fullName);
        $mail->Subject = $subject;
        $actionButtons = '';

        foreach ($linksForEmail as $index => $link) {
            $accent = $index === 0 ? '#dc3545' : '#495057';
            $actionButtons .= notification_render_action_button($link['url'], $link['label'], $accent);
        }

        $mail->Body = notification_render_email_shell(
            'Password Security Notice',
            'Please update your KODUS password',
            'We identified your account as one that may still be using an older password that does not meet our current security standard.',
            '<p>Hello <strong>' . $safeFirstName . '</strong>,</p>'
            . '<p>Your KODUS account was created during an earlier password policy period. Older passwords may be easier to guess or reuse, so we recommend updating yours as soon as possible.</p>'
            . $actionButtons
            . notification_render_detail_rows([
                'Minimum length' => (string) security_password_min_length() . ' characters',
                'Required' => 'Uppercase, lowercase, number, and symbol',
                'Action' => 'Choose a new password to keep your account protected',
            ])
            . '<p>If one host is unavailable, try the alternate link for the other server.</p>'
            . '<p>If you already changed your password recently, you can still use the button above to confirm it meets the latest KODUS requirements.</p>',
            '#dc3545',
            'KODUS Security Center'
        );
        $mail->send();
        $status = 'success';
        $message = 'Password advisory sent successfully.';

        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare('UPDATE users SET password_strength_notified_at = ? WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('si', $now, $user['id']);
            $stmt->execute();
            $stmt->close();
        }

        $ip = PHP_SAPI === 'cli' ? 'CLI' : security_get_client_ip();
        notification_log_audit($conn, (int) $user['id'], 'Security', 'Legacy password advisory sent.', $ip);
    } catch (Throwable $e) {
        $message = $e->getMessage();
    }

    notification_log_mail($conn, (string) $user['email'], $subject, $status, $message);

    return [
        'subject' => $subject,
        'status' => $status,
        'message' => $message,
    ];
}

function password_policy_issue_reset_for_user(mysqli $conn, array $user, bool $sendAdvisory = true, bool $forceAdvisory = false): ?array
{
    password_policy_ensure_schema($conn);

    $userId = isset($user['id']) ? (int) $user['id'] : 0;
    if ($userId <= 0) {
        return null;
    }

    $token = password_policy_prepare_reset($conn, $userId);
    if ($token === null) {
        return null;
    }

    $resetLinks = password_policy_build_reset_links($token, true);
    $resetLink = $resetLinks[0]['url'] ?? password_policy_build_reset_link($token);
    $mailResult = null;

    if ($sendAdvisory && ($forceAdvisory || empty($user['password_strength_notified_at']))) {
        $mailResult = password_policy_send_advisory($conn, $user, $resetLink, $resetLinks);
    }

    return [
        'token' => $token,
        'reset_link' => $resetLink,
        'reset_links' => password_policy_build_reset_links($token, true),
        'mail' => $mailResult,
    ];
}
