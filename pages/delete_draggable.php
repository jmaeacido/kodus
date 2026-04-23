<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
header('Content-Type: application/json; charset=utf-8');
include('../config.php');

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if (!$userId) {
    http_response_code(403);
    echo json_encode(['error'=>'not_authenticated']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error'=>'invalid_id']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM draggable_events WHERE id=? AND created_by=?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error'=>'prepare_failed','msg'=>$conn->error]);
    exit;
}
$stmt->bind_param("ii", $id, $userId);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error'=>'execute_failed','msg'=>$stmt->error]);
    exit;
}
if ($stmt->affected_rows < 1) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}
echo json_encode(['status'=>'deleted','id'=>$id]);
