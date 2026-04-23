<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
include('../config.php');

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();

$title = $_POST['title'] ?? '';
$start = $_POST['start'] ?? '';
$end   = $_POST['end'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

if ($userId > 0 && $title && $start) {
    $stmt = $conn->prepare("INSERT INTO events (title, start, end, created_by) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $title, $start, $end, $userId);
    $stmt->execute();
    echo "success";
} else {
    http_response_code(400);
    echo "error";
}
