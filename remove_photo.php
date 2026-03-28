<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
include('config.php');

security_require_method(['POST']);
security_require_csrf_token();

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    security_send_json(['success' => false, 'message' => 'Unauthorized.'], 403);
}

$stmt = $conn->prepare("UPDATE users SET picture = 'default.webp' WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->close();

$_SESSION['picture'] = 'default.webp';
security_send_json(['success' => true]);
