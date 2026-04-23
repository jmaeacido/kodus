<?php
require_once '../security.php';
security_bootstrap_session();
security_require_method(['GET']);

require_once '../config.php';
require_once __DIR__ . '/../fund_monitoring_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['selected_year'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$year = (int) $_SESSION['selected_year'];
echo json_encode([
    'token' => fund_monitoring_change_token($conn, $year),
]);
