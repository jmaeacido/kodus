<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/role_change_helpers.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
if (!is_numeric($userId)) {
    http_response_code(401);
    echo json_encode(['active' => false]);
    exit;
}

$state = role_change_get_state($conn, (int) $userId);
if (!$state) {
    echo json_encode(['active' => false]);
    exit;
}

echo json_encode(array_merge(['active' => true], $state));
