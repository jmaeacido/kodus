<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/mail_config.php';

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
    $stmt = $conn->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())');
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('isss', $userId, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
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

    if (!headers_sent()) {
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
                'We verified a sign-in to your account with a valid 2FA code.',
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
