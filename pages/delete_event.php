<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include('../config.php');

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if (!$userId) {
    http_response_code(403);
    echo json_encode(['error' => 'not_authenticated']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}

// Mark as deleted instead of removing from DB
$stmt = $conn->prepare("UPDATE events SET deleted_by = ?, deleted_at = NOW() WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'prepare_failed', 'msg' => $conn->error]);
    exit;
}

$stmt->bind_param("ii", $userId, $id);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'execute_failed', 'msg' => $stmt->error]);
    exit;
}

if ($stmt->affected_rows > 0) {
    echo json_encode(['status' => 'deleted', 'id' => $id]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
}