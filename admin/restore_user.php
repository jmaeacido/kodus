<?php
header('Content-Type: application/json');
require_once '../security.php';
security_bootstrap_session();
require_once '../config.php';
require_once '../base_url.php';
require_once '../vendor/autoload.php'; // PHPMailer
require_once '../mail_config.php';
require_once '../notification_helpers.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$adminId = $_SESSION['user_id'] ?? null;
$userIdToRestore = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
security_require_method(['POST']);
security_require_csrf_token();

if (!$adminId || !$userIdToRestore) {
    security_send_json(['success' => false, 'message' => 'Unauthorized or invalid request.'], 403);
}

// Check if admin
$stmt = $conn->prepare("SELECT userType FROM users WHERE id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$res = $stmt->get_result();
$currentUser = $res->fetch_assoc();

if (!$currentUser || $currentUser['userType'] !== 'admin') {
    security_send_json(['success' => false, 'message' => 'Access denied. Only admins can restore accounts.'], 403);
}

// Get user's info
$userStmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
$userStmt->bind_param("i", $userIdToRestore);
$userStmt->execute();
$userRes = $userStmt->get_result();
$user = $userRes->fetch_assoc();

if (!$user) {
    security_send_json(['success' => false, 'message' => 'User not found.'], 404);
}

// Restore user account
$restore = $conn->prepare("UPDATE users SET deleted_at = NULL WHERE id = ?");
$restore->bind_param("i", $userIdToRestore);

if ($restore->execute()) {
    // Audit log
    $action = 'Restore Account';
    $details = "Admin restored user ID $userIdToRestore";
    $ip = security_get_client_ip();
    $log = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
    $log->bind_param("isss", $adminId, $action, $details, $ip);
    $log->execute();

    $loginLink = rtrim($base_url, '/') . '/kodus/';

    // Send HTML email
    $mail = new PHPMailer(true);
    $emailError = false;

    try {
        app_configure_mailer($mail);
        $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['last_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Your Account Has Been Restored';
        $mail->Body = notification_render_email_shell(
            'Account Restored',
            'Your KODUS Access Has Been Re-enabled',
            'An administrator has restored your account successfully.',
            '<p>Hello <strong>' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
            . '<p>Your account has been restored and is ready to use again.</p>'
            . notification_render_detail_rows([
                'Account Email' => (string) ($user['email'] ?? ''),
                'Status' => 'Restored',
            ])
            . notification_render_action_button($loginLink, 'Go to KODUS', '#198754')
            . '<p>If you have any questions, please contact support.</p>',
            '#198754',
            'KODUS Account Support'
        );

        $mail->send();
    } catch (Exception $e) {
        $emailError = true;
    }

    security_send_json([
        'success' => true,
        'message' => 'User has been restored and notified.',
        'email_error' => $emailError
    ]);
} else {
    security_send_json(['success' => false, 'message' => 'Failed to restore the user.'], 500);
}
