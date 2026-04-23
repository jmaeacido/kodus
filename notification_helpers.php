<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/audit_helpers.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function notification_create_mailer(): PHPMailer
{
    $mail = new PHPMailer(true);
    app_configure_mailer($mail);
    return $mail;
}

function notification_log_mail(mysqli $conn, string $recipient, string $subject, string $status, string $message): void
{
    $stmt = $conn->prepare('INSERT INTO mail_logs (recipient, subject, status, message, created_at) VALUES (?, ?, ?, ?, NOW())');
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('ssss', $recipient, $subject, $status, $message);
    $stmt->execute();
    $stmt->close();
}

function notification_log_audit(mysqli $conn, int $userId, string $action, string $details, string $ip): void
{
    audit_log($conn, $userId, $action, $details, $ip);
}

function notification_finish_response(?string $content = null): void
{
    $payload = $content ?? '';

    while (ob_get_level() > 0) {
        $buffer = ob_get_contents();
        if ($buffer !== false && $buffer !== '') {
            $payload = $buffer . $payload;
        }
        @ob_end_clean();
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    ignore_user_abort(true);
    @set_time_limit(0);
    @ini_set('zlib.output_compression', '0');

    $payload = notification_prepare_response_payload($payload);

    if (!headers_sent()) {
        notification_apply_default_content_type($payload);
        header('Connection: close');
        header('X-Accel-Buffering: no');
        header('Content-Length: ' . strlen($payload));
    }

    echo $payload;

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        return;
    }

    @flush();
}

function notification_prepare_response_payload(string $payload): string
{
    $trimmedPayload = ltrim($payload);

    if ($trimmedPayload === '') {
        return $payload;
    }

    if (notification_response_declares_non_html_content()) {
        return $payload;
    }

    if (preg_match('/<!doctype|<html\b/i', $trimmedPayload) === 1) {
        return $payload;
    }

    return <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS</title>
  <script src="/plugins/sweetalert2/sweetalert2.min.js"></script>
  <style>
    body {
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f8fafc;
      color: #1f2937;
      font-family: "Source Sans Pro", Arial, sans-serif;
    }
  </style>
</head>
<body>
  <noscript>This page requires JavaScript to continue.</noscript>
  {$payload}
</body>
</html>
HTML;
}

function notification_response_declares_non_html_content(): bool
{
    foreach (headers_list() as $header) {
        if (stripos($header, 'Content-Type:') !== 0) {
            continue;
        }

        $contentType = trim(substr($header, strlen('Content-Type:')));
        if ($contentType === '') {
            return false;
        }

        return stripos($contentType, 'text/html') === false;
    }

    return false;
}

function notification_apply_default_content_type(string $payload): void
{
    foreach (headers_list() as $header) {
        if (stripos($header, 'Content-Type:') === 0) {
            return;
        }
    }

    $trimmedPayload = ltrim($payload);
    if ($trimmedPayload !== '' && preg_match('/<!doctype|<html\b|<script\b/i', $trimmedPayload) === 1) {
        header('Content-Type: text/html; charset=UTF-8');
    }
}

function notification_render_email_shell(string $eyebrow, string $title, string $intro, string $bodyHtml, string $accent = '#0d6efd', string $brandLabel = 'KODUS Notification Center'): string
{
    $safeEyebrow = htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8');
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeIntro = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');
    $safeAccent = htmlspecialchars($accent, ENT_QUOTES, 'UTF-8');
    $safeBrandLabel = htmlspecialchars($brandLabel, ENT_QUOTES, 'UTF-8');
    $currentYear = date('Y');

    return <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:24px;background:#eef2f7;font-family:Arial,sans-serif;color:#1f2937;">
  <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border-collapse:collapse;">
    <tr>
      <td align="center">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width:640px;border-collapse:collapse;">
          <tr>
            <td style="padding:0 0 16px 0;text-align:center;color:#6b7280;font-size:13px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;">
              {$safeBrandLabel}
            </td>
          </tr>
          <tr>
            <td style="background:#ffffff;border-radius:22px;overflow:hidden;box-shadow:0 16px 40px rgba(15,23,42,0.12);">
              <div style="background:linear-gradient(135deg, {$safeAccent} 0%, #111827 100%);padding:28px 32px;color:#ffffff;">
                <div style="font-size:12px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;opacity:0.82;margin-bottom:10px;">{$safeEyebrow}</div>
                <div style="font-size:28px;font-weight:700;line-height:1.25;margin:0 0 10px 0;">{$safeTitle}</div>
                <div style="font-size:15px;line-height:1.65;max-width:520px;opacity:0.92;">{$safeIntro}</div>
              </div>
              <div style="padding:28px 32px 18px 32px;font-size:15px;line-height:1.7;color:#374151;">
                {$bodyHtml}
              </div>
              <div style="padding:0 32px 28px 32px;">
                <div style="border-top:1px solid #e5e7eb;padding-top:16px;color:#6b7280;font-size:12px;line-height:1.7;">
                  This is an automated message from KODUS. If anything here looks unexpected, please contact support immediately.
                </div>
              </div>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 10px 0 10px;text-align:center;color:#94a3b8;font-size:12px;">
              &copy; {$currentYear} KODUS. This message was sent automatically for your records and account awareness.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

function notification_render_action_button(string $url, string $label, string $accent = '#0d6efd'): string
{
    $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $safeAccent = htmlspecialchars($accent, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<div style="margin:22px 0 10px 0;">
  <a href="{$safeUrl}" style="display:inline-block;padding:12px 18px;background:{$safeAccent};color:#ffffff;text-decoration:none;border-radius:10px;font-weight:700;">{$safeLabel}</a>
</div>
HTML;
}

function notification_render_detail_rows(array $rows): string
{
    $html = '';
    foreach ($rows as $label => $value) {
        $safeLabel = htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');
        $safeValue = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $html .= <<<HTML
<tr>
  <td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;font-size:12px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#6b7280;width:38%;">{$safeLabel}</td>
  <td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;font-size:14px;color:#111827;">{$safeValue}</td>
</tr>
HTML;
    }

    return <<<HTML
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border-collapse:collapse;margin:18px 0;background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">
  {$html}
</table>
HTML;
}

function notification_send_login_alert(array $user, string $ip, string $time, string $location = 'Unavailable'): array
{
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $subject = 'Login Notification';
    $status = 'failed';
    $message = "New login detected on {$ip} at {$time}.";

    $safeFirstName = htmlspecialchars((string) ($user['first_name'] ?? ''), ENT_QUOTES, 'UTF-8');

    try {
        $mail = notification_create_mailer();
        $mail->addAddress($user['email'], $user['first_name']);
        $mail->Subject = $subject;
        $bodyHtml =
            "<p>Hello <strong>{$safeFirstName}</strong>,</p>" .
            "<p>A new login to your KODUS account was detected.</p>" .
            notification_render_detail_rows([
                'Date & Time' => $time,
                'IP Address' => $ip,
                'Location' => $location,
                'Browser / Device' => $userAgent,
            ]) .
            "<p>If this was not you, reset your password immediately and contact support.</p>";
        $mail->Body = notification_render_email_shell(
            'Login Alert',
            'A New Login Was Detected',
            'We are notifying you about recent access to your KODUS account.',
            $bodyHtml,
            '#2563eb',
            'KODUS Security Center'
        );
        $mail->send();
        $status = 'success';
    } catch (Exception $e) {
        $message = $e->getMessage();
    }

    return [
        'subject' => $subject,
        'status' => $status,
        'message' => $message,
        'user_agent' => $userAgent,
    ];
}

function notification_send_two_factor_alert(array $user, string $ip, string $time, bool $alreadyEnabled): array
{
    $subject = $alreadyEnabled ? 'Login Notification' : 'Two-Factor Authentication Enabled';
    $status = 'success';
    $message = "2FA verification completed from {$ip} at {$time}.";

    $fullName = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
    $safeFullName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
    $safeIp = htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');
    $safeTime = htmlspecialchars($time, ENT_QUOTES, 'UTF-8');

    try {
        $mail = notification_create_mailer();
        $mail->addAddress($user['email'], $fullName);
        $mail->Subject = $subject;

        if ($alreadyEnabled) {
            $bodyHtml =
                "<p>Hello <strong>{$safeFullName}</strong>,</p>" .
                "<p>Your account was successfully accessed using <strong>Two-Factor Authentication (2FA)</strong>.</p>" .
                notification_render_detail_rows([
                    'Date & Time' => $time,
                    'IP Address' => $ip,
                ]) .
                "<p>If this login was not yours, please reset your password and contact support right away.</p>";
            $mail->Body = notification_render_email_shell(
                '2FA Login Verified',
                'New Secure Login Detected',
                'We verified a sign-in to your account with a valid authenticator or recovery code.',
                $bodyHtml,
                '#0d6efd'
            );
        } else {
            $bodyHtml =
                "<p>Hello <strong>{$safeFullName}</strong>,</p>" .
                "<p>This confirms that <strong>Two-Factor Authentication</strong> has been enabled on your account.</p>" .
                notification_render_detail_rows([
                    'Enabled At' => $time,
                    'IP Address' => $ip,
                ]) .
                "<p>Your account now requires an additional verification code for secure access and sensitive actions.</p>";
            $mail->Body = notification_render_email_shell(
                '2FA Enabled',
                'Your Account Is Better Protected',
                'Two-Factor Authentication has been turned on successfully.',
                $bodyHtml,
                '#198754'
            );
        }

        $mail->send();
    } catch (Exception $e) {
        $status = 'failed';
        $message = 'Notification delivery failed.';
        error_log('2FA verification email failed: ' . $e->getMessage());
    }

    return [
        'subject' => $subject,
        'status' => $status,
        'message' => $message,
    ];
}

function notification_send_two_factor_reset_alert(array $user, array $adminUser, string $time, string $ip): array
{
    $subject = 'Authenticator Setup Reset';
    $status = 'success';
    $message = "Authenticator setup reset for {$user['email']} at {$time}.";

    $userFullName = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
    $adminFullName = trim((string) ($adminUser['first_name'] ?? '') . ' ' . (string) ($adminUser['last_name'] ?? ''));
    $userFullName = $userFullName !== '' ? $userFullName : (string) ($user['username'] ?? 'KODUS User');
    $adminFullName = $adminFullName !== '' ? $adminFullName : (string) ($adminUser['username'] ?? 'KODUS Administrator');

    try {
        $mail = notification_create_mailer();
        $mail->addAddress((string) ($user['email'] ?? ''), $userFullName);
        $mail->Subject = $subject;

        $bodyHtml =
            '<p>Hello <strong>' . htmlspecialchars($userFullName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>' .
            '<p>An administrator reset your authenticator setup for KODUS. Your account will require a fresh authenticator enrollment the next time you sign in.</p>' .
            notification_render_detail_rows([
                'Reset At' => $time,
                'Reset By' => $adminFullName,
                'Admin Email' => (string) ($adminUser['email'] ?? 'Unavailable'),
                'IP Address' => $ip,
            ]) .
            '<p>If you were expecting this, simply sign in again and complete the authenticator setup flow. If this looks unexpected, contact your administrator right away.</p>';

        $mail->Body = notification_render_email_shell(
            'Authenticator Reset',
            'Your 2FA Setup Was Reset',
            'We are letting you know that your authenticator enrollment was cleared by an administrator.',
            $bodyHtml,
            '#dc3545',
            'KODUS Security Center'
        );
        $mail->send();
    } catch (Exception $e) {
        $status = 'failed';
        $message = 'Notification delivery failed.';
        error_log('2FA reset email failed: ' . $e->getMessage());
    }

    return [
        'subject' => $subject,
        'status' => $status,
        'message' => $message,
    ];
}
