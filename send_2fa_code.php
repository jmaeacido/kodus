<?php
require 'vendor/autoload.php';
require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/notification_helpers.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
security_bootstrap_session();
include('config.php');

security_require_method(['POST']);
security_require_csrf_token();

$userId = $_SESSION['user_id'] ?? $_SESSION['2fa_user_id'] ?? null;
if (!$userId) {
    security_send_json(['success' => false, 'message' => 'Not logged in.'], 403);
}

$rateLimitKey = '2fa-send:' . $userId;
if (!security_rate_limit_check($rateLimitKey, 3, 300)) {
    security_send_json(['success' => false, 'message' => 'Too many requests. Please wait before requesting another code.'], 429);
}

// Fetch user's email
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    security_send_json(['success' => false, 'message' => 'User not found.'], 404);
}

// Generate 2FA code
$code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expiry = date('Y-m-d H:i:s', time() + 300); // 5 minutes

// Save to database
$stmt = $conn->prepare("UPDATE users SET two_fa_code = ?, two_fa_code_expiry = ? WHERE id = ?");
$stmt->bind_param("ssi", $code, $expiry, $userId);
$stmt->execute();
if ($stmt->affected_rows <= 0) {
    security_send_json(['success' => false, 'message' => 'Unable to issue a verification code right now.'], 500);
}
$stmt->close();

// Store in session (optional)
$_SESSION['2fa_code'] = $code;
$_SESSION['2fa_expiry'] = time() + 300;

// Send email
$mail = new PHPMailer(true);

try {
    app_configure_mailer($mail);
    $mail->addAddress($user['email']);
    $mail->isHTML(true);

    $mail->Subject = 'Your 2FA Verification Code';
    $mail->Body = notification_render_email_shell(
        'Verification Required',
        'Your 2FA Code Is Ready',
        'Use this one-time code to continue your secure KODUS sign-in or security action.',
        '<p>Enter the verification code below to continue:</p>'
        . '<div style="margin:20px 0;padding:18px 22px;border-radius:16px;background:#111827;color:#ffffff;text-align:center;">'
        . '<div style="font-size:12px;letter-spacing:0.18em;text-transform:uppercase;opacity:0.72;margin-bottom:8px;">One-Time Code</div>'
        . '<div style="font-size:34px;font-weight:700;letter-spacing:0.38em;margin-left:0.38em;">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</div>'
        . '</div>'
        . notification_render_detail_rows([
            'Expires In' => '5 minutes',
            'Requested For' => $user['email'],
        ])
        . '<p>If you did not request this code, you can safely ignore this message.</p>',
        '#7c3aed'
    );

    $mail->send();
    security_send_json(['success' => true]);
} catch (RuntimeException $e) {
    error_log('2FA mail configuration failed: ' . $e->getMessage());
    security_send_json([
        'success' => false,
        'message' => 'Unable to send the verification code right now.'
    ], 500);
} catch (Exception $e) {
    error_log('2FA mail delivery failed: ' . $mail->ErrorInfo);
    security_send_json([
        'success' => false,
        'message' => 'Unable to send the verification code right now.'
    ], 500);
}
