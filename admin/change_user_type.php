<?php
// change_user_type.php
session_start();
include('../config.php');
include('../base_url.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../vendor/autoload.php'; // make sure PHPMailer is autoloaded
require_once '../mail_config.php';
require_once '../notification_helpers.php';
require_once '../role_change_helpers.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'Unknown error.',
    'email_error' => false,
    'mail_error_message' => ''
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit;
}

$adminId = $_SESSION['user_id'] ?? null;
if (!$adminId) {
    $response['message'] = 'Unauthorized.';
    echo json_encode($response);
    exit;
}

$userId = intval($_POST['user_id'] ?? 0);
$newType = trim($_POST['user_type'] ?? '');

if ($userId <= 0 || $newType === '') {
    $response['message'] = 'Invalid input.';
    echo json_encode($response);
    exit;
}

// Optional: verify that the current user is an admin (defense-in-depth)
$stmt = $conn->prepare("SELECT userType, first_name, last_name FROM users WHERE id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$stmt->bind_result($adminUserType, $adminFirst, $adminLast);
$stmt->fetch();
$stmt->close();

if ($adminUserType !== 'admin') {
    $response['message'] = 'Access denied.';
    echo json_encode($response);
    exit;
}

// Get target user info and old userType
$stmt = $conn->prepare("SELECT userType, email, first_name, middle_name, last_name, ext FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    $response['message'] = 'User not found.';
    echo json_encode($response);
    exit;
}
$stmt->bind_result($oldType, $targetEmail, $tFirst, $tMiddle, $tLast, $tExt);
$stmt->fetch();
$stmt->close();

// Normalize ext and build display name (ext appended without changing case)
$nameParts = array_filter([$tFirst, $tMiddle, $tLast]);
$displayName = ucwords(strtolower(implode(' ', $nameParts)));
if (!empty($tExt)) {
    $displayName .= ' ' . $tExt;
}

// Update userType
$update = $conn->prepare("UPDATE users SET userType = ? WHERE id = ?");
$update->bind_param("si", $newType, $userId);

if (!$update->execute()) {
    $update->close();
    $response['message'] = 'Failed to update user type.';
    echo json_encode($response);
    exit;
}
$update->close();

if ($oldType !== $newType) {
    role_change_schedule($conn, $userId, $oldType, $newType, 20);
}

// Audit log
$action  = "Change User Type";
$details = "Changed user ID {$userId} type from '{$oldType}' to '{$newType}'";
$ip      = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

$log = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
$log->bind_param("isss", $adminId, $action, $details, $ip);
$log->execute();
$log->close();

// Send HTML email to the affected user
$mailStatus = 'Sent';
$mailBody = '';
$mailSubject = "Your KODUS account type was changed to " . htmlspecialchars($newType);

try {
    $mail = new PHPMailer(true);
    app_configure_mailer($mail);
    $mail->addAddress($targetEmail, $displayName);

    $mail->isHTML(true);
    $mail->Subject = $mailSubject;

    $timestamp = date("F d, Y h:ia"); // server time (you can format timezone earlier if needed)

    $mailBody = notification_render_email_shell(
        'Account Update',
        'Your User Role Has Changed',
        'An administrator updated your KODUS account privileges.',
        '<p>Hi <strong>' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
        . '<p>Your account <strong>(' . htmlspecialchars($targetEmail, ENT_QUOTES, 'UTF-8') . ')</strong> has been updated.</p>'
        . notification_render_detail_rows([
            'Previous User Type' => $oldType,
            'New User Type' => $newType,
            'Changed By' => ucwords(strtolower($adminFirst . ' ' . $adminLast)) . ' (Admin)',
            'When' => $timestamp,
        ])
        . notification_render_action_button($base_url . 'kodus/', 'Open KODUS', '#0d6efd')
        . '<p>If you believe this was done in error, please contact your administrator immediately.</p>',
        '#0d6efd',
        'KODUS Account Support'
    );

    $mail->Body = $mailBody;

    // Optional: a plain-text fallback
    $mail->AltBody = "Hi {$displayName},\n\nYour KODUS account ({$targetEmail}) user type has been changed from {$oldType} to {$newType} by an administrator on {$timestamp}.\n\nIf you think this is an error, contact your admin.";

    $mail->send();
} catch (Exception $e) {
    $mailStatus = 'Failed';
    $response['email_error'] = true;
    $response['mail_error_message'] = $mail->ErrorInfo ?: $e->getMessage();
}

// Log mail into mail_logs table (recipient, subject, status, message)
try {
    $stmtMail = $conn->prepare("INSERT INTO mail_logs (recipient, subject, status, message, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmtMail->bind_param("ssss", $targetEmail, $mailSubject, $mailStatus, $mailBody);
    $stmtMail->execute();
    $stmtMail->close();
} catch (Exception $e) {
    // Fail silently but attach note to response
    if ($response['mail_error_message'] === '') {
        $response['mail_error_message'] = 'Mail logging failed: ' . $e->getMessage();
        $response['email_error'] = true;
    }
}

// Everything succeeded (update + audit). If mail failed, email_error will be true.
$response['success'] = true;
$response['message'] = 'User type updated successfully.';
if ($response['email_error']) {
    $response['message'] .= ' However, sending notification email failed.';
}

echo json_encode($response);
exit;
