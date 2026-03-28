<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include('../config.php');

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if (!$userId) {
    http_response_code(403);
    echo json_encode(['error'=>'not_authenticated']);
    exit;
}

$title = trim($_POST['title'] ?? '');
$color = trim($_POST['color'] ?? '#3788d8');

if ($title === '') {
    http_response_code(400);
    echo json_encode(['error'=>'title_required']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO draggable_events (title, color, created_by) VALUES (?, ?, ?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error'=>'prepare_failed','msg'=>$conn->error]);
    exit;
}
$stmt->bind_param("ssi", $title, $color, $userId);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error'=>'execute_failed','msg'=>$stmt->error]);
    exit;
}

echo json_encode([
    "id" => $stmt->insert_id,
    "title" => $title,
    "color" => $color
]);
