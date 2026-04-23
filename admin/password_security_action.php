<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'Unknown error.',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

security_require_csrf_token();

$adminId = (int) ($_SESSION['user_id'] ?? 0);
if ($adminId <= 0) {
    $response['message'] = 'Unauthorized.';
    echo json_encode($response);
    exit;
}

$stmt = $conn->prepare('SELECT userType FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->bind_param('i', $adminId);
$stmt->execute();
$admin = db_stmt_fetch_one_assoc($stmt);
$stmt->close();

if (!$admin || ($admin['userType'] ?? '') !== 'admin') {
    $response['message'] = 'Access denied.';
    echo json_encode($response);
    exit;
}

$action = trim((string) ($_POST['action'] ?? ''));
$targetUserId = (int) ($_POST['user_id'] ?? 0);
$currentVersion = password_policy_current_version();
$ip = security_get_client_ip();

if ($action === 'bulk_send_pending') {
    $stmt = $conn->prepare(
        'SELECT * FROM users
         WHERE deleted_at IS NULL
           AND (must_change_password = 1 OR password_policy_version < ?)'
    );
    $stmt->bind_param('i', $currentVersion);
    $stmt->execute();
    $users = db_stmt_fetch_all_assoc($stmt);

    $sent = 0;
    $failed = 0;

    foreach ($users as $user) {
        $resetState = password_policy_issue_reset_for_user($conn, $user, true, true);
        if ($resetState && isset($resetState['mail']['status']) && $resetState['mail']['status'] === 'success') {
            $sent++;
        } else {
            $failed++;
        }
    }
    $stmt->close();

    notification_log_audit($conn, $adminId, 'Security', "Bulk password reminders triggered. Sent: {$sent}, Failed: {$failed}", $ip);

    $response['success'] = true;
    $response['message'] = "Bulk reminder job finished. Sent: {$sent}. Failed: {$failed}.";
    echo json_encode($response);
    exit;
}

if ($action === 'backfill_in_app_notices') {
    $result = password_policy_backfill_in_app_notices($conn);
    notification_log_audit(
        $conn,
        $adminId,
        'Security',
        'Backfilled password reminder in-app notices. Created: ' . (int) ($result['created'] ?? 0) . ', Skipped: ' . (int) ($result['skipped'] ?? 0),
        $ip
    );

    $response['success'] = true;
    $response['message'] = 'Backfill complete. Created: ' . (int) ($result['created'] ?? 0) . '. Skipped: ' . (int) ($result['skipped'] ?? 0) . '.';
    echo json_encode($response);
    exit;
}

if ($targetUserId <= 0) {
    $response['message'] = 'Invalid user.';
    echo json_encode($response);
    exit;
}

$stmt = $conn->prepare('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->bind_param('i', $targetUserId);
$stmt->execute();
$user = db_stmt_fetch_one_assoc($stmt);
$stmt->close();

if (!$user) {
    $response['message'] = 'User not found.';
    echo json_encode($response);
    exit;
}

if ($action === 'force_reset') {
    $resetState = password_policy_issue_reset_for_user($conn, $user, true, true);
    if (!$resetState) {
        $response['message'] = 'Unable to prepare the password reset.';
        echo json_encode($response);
        exit;
    }

    notification_log_audit($conn, $adminId, 'Security', 'Admin required a password reset for user ID ' . $targetUserId, $ip);

    $response['success'] = true;
    $response['message'] = 'The user has been marked for a required password reset and the email reminder was prepared.';
    echo json_encode($response);
    exit;
}

if ($action === 'send_reminder') {
    $resetState = password_policy_issue_reset_for_user($conn, $user, true, true);
    if (!$resetState) {
        $response['message'] = 'Unable to prepare the reminder email.';
        echo json_encode($response);
        exit;
    }

    notification_log_audit($conn, $adminId, 'Security', 'Admin triggered a password security reminder for user ID ' . $targetUserId, $ip);

    $response['success'] = true;
    $response['message'] = 'The password security reminder was sent.';
    echo json_encode($response);
    exit;
}

$response['message'] = 'Unsupported action.';
echo json_encode($response);
