<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();

require_once __DIR__ . '/base_url.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helpers.php';

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

    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param("isss", $userId, $action, $details, $ip);
        $stmt->execute();
        $stmt->close();
    } else {
        $logoutSuccess = false;
        error_log('Logout prepare failed while writing audit log.');
    }
}

$isPostRequest = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
if ($isPostRequest) {
    security_require_csrf_token();
} else {
    $allowedGetReasons = ['timeout', 'role_changed', 'deactivated'];
    $token = $_GET['token'] ?? null;
    if (!in_array($reason, $allowedGetReasons, true) || !security_validate_csrf_token(is_string($token) ? $token : null)) {
        http_response_code(405);
        exit('Method not allowed.');
    }
}

$feedback = auth_logout_user($conn, $reason);
$query = http_build_query([
    'logout' => $reason,
    'status' => $feedback['icon'],
]);

header('Location: ./' . ($query !== '' ? '?' . $query : ''));
exit;
