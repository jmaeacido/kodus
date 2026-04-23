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

$adminId = (int) ($_SESSION['user_id'] ?? 0);
if ($adminId <= 0) {
    security_send_json(['success' => false, 'message' => 'Unauthorized.'], 403);
}

$stmt = $conn->prepare("SELECT id, userType, username, email, first_name, last_name FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->bind_param('i', $adminId);
$stmt->execute();
$admin = db_stmt_fetch_one_assoc($stmt);
$stmt->close();

if (($admin['userType'] ?? '') !== 'admin') {
    security_send_json(['success' => false, 'message' => 'Access denied.'], 403);
}

$userIds = $_POST['user_ids'] ?? [];
if (!is_array($userIds)) {
    $userIds = [$userIds];
}

$userIds = array_values(array_unique(array_filter(array_map(static function ($value): int {
    return (int) $value;
}, $userIds), static function (int $value): bool {
    return $value > 0;
})));

if ($userIds === []) {
    security_send_json(['success' => false, 'message' => 'Select at least one user to reset.'], 422);
}

$placeholders = implode(',', array_fill(0, count($userIds), '?'));
$types = str_repeat('i', count($userIds));

$stmt = $conn->prepare(
    "SELECT id, username, email, first_name, last_name
     FROM users
     WHERE deleted_at IS NULL
       AND id IN ($placeholders)"
);
$stmt->bind_param($types, ...$userIds);
$stmt->execute();
$result = $stmt->get_result();
$targets = [];
while ($row = $result->fetch_assoc()) {
    $targets[] = $row;
}
$stmt->close();

if ($targets === []) {
    security_send_json(['success' => false, 'message' => 'No active users were found for the selected records.'], 404);
}

$targetIds = array_map(static fn(array $row): int => (int) $row['id'], $targets);
$targetPlaceholders = implode(',', array_fill(0, count($targetIds), '?'));
$targetTypes = str_repeat('i', count($targetIds));

$stmt = $conn->prepare(
    "UPDATE users
     SET two_fa_enabled = 1,
         two_fa_secret = NULL,
         two_fa_confirmed_at = NULL,
         two_fa_recovery_codes = NULL,
         two_fa_recovery_generated_at = NULL,
         two_fa_code = NULL,
         two_fa_code_expiry = NULL
     WHERE id IN ($targetPlaceholders)"
);
$stmt->bind_param($targetTypes, ...$targetIds);
$success = $stmt->execute();
$affectedRows = $stmt->affected_rows;
$stmt->close();

if (!$success) {
    security_send_json(['success' => false, 'message' => 'Failed to reset authenticator setup for the selected users.'], 500);
}

$ip = security_get_client_ip();
$timestamp = date('Y-m-d H:i:s');
$targetNames = [];
$emailSuccessCount = 0;
$emailFailedCount = 0;
$emailSkippedCount = 0;

foreach ($targets as $targetUser) {
    $fullName = trim((string) ($targetUser['first_name'] ?? '') . ' ' . (string) ($targetUser['last_name'] ?? ''));
    $targetNames[] = $fullName !== '' ? $fullName : (string) ($targetUser['username'] ?? ('User #' . (int) $targetUser['id']));

    if (trim((string) ($targetUser['email'] ?? '')) !== '') {
        $mailResult = notification_send_two_factor_reset_alert($targetUser, $admin, $timestamp, $ip);
        notification_log_mail($conn, (string) $targetUser['email'], $mailResult['subject'], $mailResult['status'], $mailResult['message']);
        if (($mailResult['status'] ?? '') === 'success') {
            $emailSuccessCount++;
        } else {
            $emailFailedCount++;
        }
    } else {
        $emailSkippedCount++;
    }
}

audit_log(
    $conn,
    $adminId,
    'Bulk Reset User 2FA',
    'Reset authenticator setup for ' . implode(', ', $targetNames) . '.',
    $ip
);

security_send_json([
    'success' => true,
    'message' => count($targets) . ' user account(s) will be required to enroll their authenticator again on next login.',
    'affected_rows' => $affectedRows,
    'email_success_count' => $emailSuccessCount,
    'email_failed_count' => $emailFailedCount,
    'email_skipped_count' => $emailSkippedCount,
]);
