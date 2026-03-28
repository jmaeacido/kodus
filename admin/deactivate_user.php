<?php
header('Content-Type: application/json');
require_once '../security.php';
security_bootstrap_session();
require_once '../config.php';
require_once '../base_url.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/../mail_config.php';
require_once __DIR__ . '/../notification_helpers.php';
require_once __DIR__ . '/../role_change_helpers.php';

$adminId = $_SESSION['user_id'] ?? null;
$userIdToDeactivate = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

security_require_method(['POST']);
security_require_csrf_token();

if (!$adminId || !$userIdToDeactivate) {
    security_send_json(['success' => false, 'message' => 'Unauthorized or invalid request.'], 403);
}

$stmt = $conn->prepare("SELECT id, userType, first_name, last_name FROM users WHERE id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin || $admin['userType'] !== 'admin') {
    security_send_json(['success' => false, 'message' => 'Access denied. Only admins can deactivate accounts.'], 403);
}

if ((int) $adminId === $userIdToDeactivate) {
    security_send_json(['success' => false, 'message' => 'You cannot deactivate your own account from this section.'], 422);
}

$userStmt = $conn->prepare("
    SELECT id, username, email, first_name, last_name, userType, deleted_at
    FROM users
    WHERE id = ?
");
$userStmt->bind_param("i", $userIdToDeactivate);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$user) {
    security_send_json(['success' => false, 'message' => 'User not found.'], 404);
}

if ($user['deleted_at'] !== null) {
    security_send_json(['success' => false, 'message' => 'User is already deactivated.'], 422);
}

if ($user['userType'] === 'admin') {
    security_send_json(['success' => false, 'message' => 'Administrator accounts cannot be deactivated here.'], 422);
}

$deactivate = $conn->prepare("
    UPDATE users
    SET deleted_at = NOW(), remember_token = NULL, is_online = 0, two_fa_code = NULL, two_fa_code_expiry = NULL
    WHERE id = ?
");
$deactivate->bind_param("i", $userIdToDeactivate);

if (!$deactivate->execute()) {
    $deactivate->close();
    security_send_json(['success' => false, 'message' => 'Failed to deactivate the user.'], 500);
}
$deactivate->close();

role_change_schedule(
    $conn,
    $userIdToDeactivate,
    (string) ($user['userType'] ?? ''),
    'deactivated',
    20,
    'deactivated',
    'Your account has been deactivated by an administrator. You will be signed out so this change can take effect.'
);

$action = 'Admin Deactivated Account';
$details = "Admin deactivated user ID {$userIdToDeactivate} ({$user['email']})";
$ip = security_get_client_ip();
$log = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
$log->bind_param("isss", $adminId, $action, $details, $ip);
$log->execute();
$log->close();

$emailError = false;
$mailBody = '';
$mailSubject = 'Your KODUS account has been deactivated';

try {
    $mail = new PHPMailer(true);
    app_configure_mailer($mail);
    $mail->addAddress($user['email'], trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
    $mail->isHTML(true);
    $mail->Subject = $mailSubject;

    $mailBody = notification_render_email_shell(
        'Account Deactivated',
        'Your Access Has Been Disabled',
        'An administrator has deactivated your KODUS account.',
        '<p>Hello <strong>' . htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
        . '<p>Your KODUS account has been deactivated by an administrator.</p>'
        . notification_render_detail_rows([
            'Account Email' => (string) ($user['email'] ?? ''),
            'Status' => 'Deactivated',
        ])
        . '<p>If you believe this was done in error, please contact your administrator or support team for assistance.</p>',
        '#dc3545',
        'KODUS Account Support'
    );
    $mail->Body = $mailBody;
    $mail->AltBody = "Your KODUS account has been deactivated by an administrator. Please contact support if you believe this was done in error.";
    $mail->send();
} catch (Exception $e) {
    $emailError = true;
}

try {
    $mailStatus = $emailError ? 'Failed' : 'Sent';
    $mailLog = $conn->prepare("INSERT INTO mail_logs (recipient, subject, status, message, created_at) VALUES (?, ?, ?, ?, NOW())");
    $mailLog->bind_param("ssss", $user['email'], $mailSubject, $mailStatus, $mailBody);
    $mailLog->execute();
    $mailLog->close();
} catch (Exception $e) {
    $emailError = true;
}

security_send_json([
    'success' => true,
    'message' => $emailError
        ? 'User was deactivated, but the notification email could not be sent.'
        : 'User was deactivated successfully.',
]);
