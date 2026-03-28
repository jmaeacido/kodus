<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
include('config.php');
require 'vendor/autoload.php';
require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/notification_helpers.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');
security_require_method(['POST']);
security_require_csrf_token();

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    security_send_json(['success' => false, 'message' => 'Unauthorized'], 403);
}

// Fetch user email
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    security_send_json(['success' => false, 'message' => 'User not found.'], 404);
}

// Update 2FA status
$stmt = $conn->prepare("UPDATE users SET two_fa_enabled = 0, two_fa_code = '', two_fa_code_expiry = NULL WHERE id = ?");
$stmt->bind_param("i", $userId);
$success = $stmt->execute();
$stmt->close();

if (!$success) {
    security_send_json(['success' => false, 'message' => 'Failed to disable 2FA.'], 500);
}

// Send confirmation email
$mail = new PHPMailer(true);
try {
    app_configure_mailer($mail);
    $mail->addAddress($user['email']);
    $mail->isHTML(true);
    $mail->Subject = '2FA Disabled on Your Account';
    $mail->Body = notification_render_email_shell(
        '2FA Disabled',
        'Two-Factor Authentication Was Turned Off',
        'This message confirms that your KODUS account no longer requires 2FA verification.',
        '<p>Hello,</p>'
        . '<p>Two-Factor Authentication <strong>has been disabled</strong> on your account.</p>'
        . notification_render_detail_rows([
            'Status' => 'Disabled',
            'Time' => date('Y-m-d H:i:s'),
        ])
        . '<p>If you did not perform this action, please re-enable 2FA and contact support immediately.</p>',
        '#dc3545'
    );

    $mail->send();
} catch (Throwable $e) {
    error_log('Disable 2FA mail failed: ' . $e->getMessage());
}

security_send_json(['success' => true]);
