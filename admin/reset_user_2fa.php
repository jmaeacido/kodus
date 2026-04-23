<?php
require_once '../security.php';
security_bootstrap_session();
require_once '../config.php';
require_once '../notification_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    security_send_json(['success' => false, 'message' => 'Invalid request method.'], 405);
}

security_require_csrf_token();

$adminId = $_SESSION['user_id'] ?? null;
if (!$adminId) {
    security_send_json(['success' => false, 'message' => 'Unauthorized.'], 403);
}

$stmt = $conn->prepare("SELECT id, userType, username, email, first_name, last_name FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$admin = db_stmt_fetch_one_assoc($stmt);
$stmt->close();

if (($admin['userType'] ?? '') !== 'admin') {
    security_send_json(['success' => false, 'message' => 'Access denied.'], 403);
}

$userId = (int) ($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    security_send_json(['success' => false, 'message' => 'Invalid user.'], 422);
}

$stmt = $conn->prepare('SELECT id, username, email, first_name, last_name, deleted_at FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$targetUser = db_stmt_fetch_one_assoc($stmt);
$stmt->close();

if (!$targetUser || !empty($targetUser['deleted_at'])) {
    security_send_json(['success' => false, 'message' => 'User not found.'], 404);
}

$stmt = $conn->prepare(
    "UPDATE users
     SET two_fa_enabled = 1,
         two_fa_secret = NULL,
         two_fa_confirmed_at = NULL,
         two_fa_recovery_codes = NULL,
         two_fa_recovery_generated_at = NULL,
         two_fa_code = NULL,
         two_fa_code_expiry = NULL
     WHERE id = ?"
);
$stmt->bind_param('i', $userId);
$success = $stmt->execute();
$stmt->close();

if (!$success) {
    security_send_json(['success' => false, 'message' => 'Failed to reset authenticator setup.'], 500);
}

$fullName = trim((string) ($targetUser['first_name'] ?? '') . ' ' . (string) ($targetUser['last_name'] ?? ''));
$detailName = $fullName !== '' ? $fullName : (string) ($targetUser['username'] ?? 'Unknown User');
$ip = security_get_client_ip();
audit_log($conn, (int) $adminId, 'Reset User 2FA', 'Reset authenticator setup for ' . $detailName . ' (User ID ' . (int) $userId . ').', $ip);

if (trim((string) ($targetUser['email'] ?? '')) !== '') {
    $mailResult = notification_send_two_factor_reset_alert($targetUser, $admin, date('Y-m-d H:i:s'), $ip);
    notification_log_mail($conn, (string) $targetUser['email'], $mailResult['subject'], $mailResult['status'], $mailResult['message']);
}

security_send_json([
    'success' => true,
    'message' => 'Authenticator setup reset. The user will be prompted to enroll again on next login.',
]);
