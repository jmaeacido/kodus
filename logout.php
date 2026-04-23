<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();

require_once __DIR__ . '/base_url.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/sso_helpers.php';

$reason = (string) ($_POST['reason'] ?? $_GET['reason'] ?? 'manual');
$themePreference = theme_current_preference();
$isDarkTheme = $themePreference === 'dark';

if (false && isset($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
    role_change_clear($conn, $userId);

    $stmt = $conn->prepare("UPDATE users SET is_online = 0, remember_token = NULL WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
    } else {
        $logoutSuccess = false;
        error_log('Logout prepare failed while clearing user session state.');
    }

    // ------------------------------
    // 🔹 Insert into audit_logs
    // ------------------------------
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $action = 'Logout';
    $details = ($reason === 'timeout') ? 'Auto-logout due to inactivity' : 'Manual logout by user';

    audit_log($conn, $userId, $action, $details, $ip);
}

$isPostRequest = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
if ($isPostRequest) {
    security_require_csrf_token();
} else {
    $allowedGetReasons = ['timeout', 'role_changed', 'deactivated', 'maintenance'];
    $token = $_GET['token'] ?? null;
    if (!in_array($reason, $allowedGetReasons, true) || !security_validate_csrf_token(is_string($token) ? $token : null)) {
        http_response_code(405);
        exit('Method not allowed.');
    }
}

$logoutAll = filter_var($_POST['logout_all'] ?? $_GET['logout_all'] ?? false, FILTER_VALIDATE_BOOLEAN);
$remoteLogoutResult = sso_logout_remote($_SESSION['idp_access_token'] ?? null, $logoutAll);
$feedback = auth_logout_user($conn, $reason);
if (!($remoteLogoutResult['success'] ?? true) && $reason === 'manual') {
    $feedback = [
        'icon' => 'error',
        'title' => 'Logout Completed With Issues',
        'text' => 'You were signed out locally, but the Caraga Connect session could not be revoked cleanly.',
    ];
    error_log('SSO remote logout failed: ' . (string) ($remoteLogoutResult['message'] ?? 'unknown error'));
}
$query = http_build_query([
    'logout' => $reason,
    'status' => $feedback['icon'],
]);

header('Location: ./' . ($query !== '' ? '?' . $query : ''));
exit;
