<?php
ob_start();
header('Content-Type: application/json');

require_once 'security.php';
security_bootstrap_session();
require_once 'config.php';
require_once 'mail_config.php';
require_once 'notification_helpers.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    security_send_json(['success' => false, 'message' => 'Invalid request'], 405);
}
security_require_csrf_token();

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    security_send_json(['success' => false, 'message' => 'Not authenticated'], 403);
}

$password = $_POST['password'] ?? '';
$code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    security_send_json(['success' => false, 'message' => 'User not found'], 404);
}

// Prevent deletion of admin accounts
if ($user['userType'] === 'admin') {
    security_send_json(['success' => false, 'message' => 'Cannot delete an administrator account'], 403);
}

// Validate password
if (!password_verify($password, $user['password'])) {
    security_send_json(['success' => false, 'message' => 'Incorrect password'], 422);
}

if (!empty($user['two_fa_enabled'])) {
    if (!$code) {
        security_send_json(['success' => false, 'message' => '2FA code is required'], 422);
    }

    if (empty($user['two_fa_code']) || !hash_equals((string) $user['two_fa_code'], (string) $code)) {
        security_send_json(['success' => false, 'message' => 'Invalid 2FA code'], 422);
    }

    if (strtotime((string) $user['two_fa_code_expiry']) < time()) {
        security_send_json(['success' => false, 'message' => '2FA code has expired'], 422);
    }
}

// Soft delete
$deleteStmt = $conn->prepare("UPDATE users SET deleted_at = NOW(), remember_token = NULL WHERE id = ?");
$deleteStmt->bind_param("i", $userId);
$deleteStmt->execute();

// Clear 2FA code and expiry
$clearCodeStmt = $conn->prepare("UPDATE users SET two_fa_code = NULL, two_fa_code_expiry = NULL WHERE id = ?");
$clearCodeStmt->bind_param("i", $userId);
$clearCodeStmt->execute();

// Log in audit_logs
$action = 'Account Deletion';
$details = 'User deleted their own account';
$ip = security_get_client_ip();
$logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
$logStmt->bind_param("isss", $userId, $action, $details, $ip);
$logStmt->execute();

// Send HTML confirmation email
$mail = new PHPMailer(true);
$recipient = $user['email'];
$subject = 'Account Deletion Confirmation';
$status = 'sent';

$body = notification_render_email_shell(
    'Account Deleted',
    'Your KODUS Account Was Deleted',
    'This confirms that your account deletion request has been completed.',
    '<p>Hello <strong>' . htmlspecialchars((string) $user['first_name'], ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
    . '<p>Your account has been <strong>successfully deleted</strong>.</p>'
    . notification_render_detail_rows([
        'Account Email' => $recipient,
        'Status' => 'Deleted',
    ])
    . '<p>If you did not initiate this action, contact support immediately.</p>',
    '#dc3545',
    'KODUS Account Support'
);

try {
    app_configure_mailer($mail);
    $mail->addAddress($recipient, $user['first_name'] . ' ' . $user['last_name']);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $body;

    $mail->send();
} catch (Exception $e) {
    $status = 'failed';
    error_log('Email error: ' . $mail->ErrorInfo);
}

// Log to mail_logs
$mailLogStmt = $conn->prepare("INSERT INTO mail_logs (recipient, subject, status, message, created_at) VALUES (?, ?, ?, ?, NOW())");
$mailLogStmt->bind_param("ssss", $recipient, $subject, $status, $body);
$mailLogStmt->execute();

session_destroy(); // log out the user
security_clear_cookie('remember_token');
security_clear_cookie(session_name());

ob_end_clean();
security_send_json(['success' => true]);
exit;
