<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/config.php';

security_require_method(['POST']);
security_require_csrf_token();

$userId = $_SESSION['user_id'] ?? null;
if (!is_numeric($userId)) {
    security_send_json(['success' => false, 'message' => 'Unauthorized.'], 403);
}

$theme = theme_normalize_preference($_POST['theme_preference'] ?? 'light');
$stmt = $conn->prepare("UPDATE users SET theme_preference = ? WHERE id = ?");
if (!$stmt) {
    security_send_json(['success' => false, 'message' => 'Unable to update theme preference.'], 500);
}

$userId = (int) $userId;
$stmt->bind_param("si", $theme, $userId);
$stmt->execute();
$stmt->close();

theme_store_session_preference($theme);
theme_store_client_preference($theme);

security_send_json([
    'success' => true,
    'theme_preference' => $theme,
]);
