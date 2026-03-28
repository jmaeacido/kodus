<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/notification_helpers.php';
include('config.php');

security_require_method(['POST']);
security_require_csrf_token();

$login2faUserId = $_SESSION['2fa_user_id'] ?? null;
$sessionUserId = $_SESSION['user_id'] ?? null;
$userId = $login2faUserId ?? $sessionUserId;
if (!$userId) {
    security_send_json(['success' => false, 'message' => 'Unauthorized.'], 403);
}

$enteredCode = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
if ($enteredCode === '') {
    security_send_json(['success' => false, 'message' => 'No code entered.'], 422);
}

$rateLimitKey = '2fa-verify:' . $userId;
if (!security_rate_limit_check($rateLimitKey, 5, 300)) {
    security_send_json(['success' => false, 'message' => 'Too many invalid attempts. Please request a new code.'], 429);
}

$stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || empty($user['two_fa_code'])) {
    security_send_json(['success' => false, 'message' => 'No 2FA code found.'], 404);
}

$currentTimestamp = time();
$expiryTimestamp = strtotime((string) $user['two_fa_code_expiry']);

if (!hash_equals((string) $user['two_fa_code'], $enteredCode)) {
    security_send_json(['success' => false, 'message' => 'Invalid code.'], 422);
}

if ($expiryTimestamp === false || $currentTimestamp > $expiryTimestamp) {
    security_send_json(['success' => false, 'message' => 'Code has expired.'], 422);
}

$stmt = $conn->prepare("UPDATE users SET two_fa_code = '', two_fa_code_expiry = NULL WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->close();

$alreadyEnabled = !empty($user['two_fa_enabled']);
if (!$alreadyEnabled) {
    $stmt = $conn->prepare('UPDATE users SET two_fa_enabled = 1 WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
    $user['two_fa_enabled'] = 1;
}

if ($login2faUserId !== null) {
    auth_complete_login($conn, $user);
}

unset($_SESSION['2fa_code'], $_SESSION['2fa_expiry']);
if ($login2faUserId !== null) {
    unset($_SESSION['2fa_user_id']);
}
security_rate_limit_reset($rateLimitKey);

if ($login2faUserId !== null && !empty($_SESSION['remember_me'])) {
    auth_issue_remember_me_token($conn, (int) $user['id']);
    unset($_SESSION['remember_me']);
}

$ip = security_get_client_ip();
$time = date('Y-m-d H:i:s');
$mailResult = notification_send_two_factor_alert($user, $ip, $time, $alreadyEnabled);
notification_log_mail($conn, $user['email'], $mailResult['subject'], $mailResult['status'], $mailResult['message']);

if ($login2faUserId !== null) {
    notification_log_audit($conn, (int) $user['id'], 'Login', '2FA verified login', $ip);
    security_send_json(['success' => true, 'message' => '2FA verified.']);
}

notification_log_audit($conn, (int) $user['id'], 'Security', '2FA enabled from settings', $ip);
security_send_json(['success' => true, 'message' => 'Two-factor authentication enabled.']);
