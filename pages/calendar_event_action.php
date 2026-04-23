<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
include('../config.php');

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Invalid action'];

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userType = (string) ($_SESSION['user_type'] ?? '');
if ($userId <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? null;
$title = $_POST['title'] ?? '';
$start = $_POST['start'] ?? '';
$end = $_POST['end'] ?? '';
$description = $_POST['description'] ?? '';

try {
    if (!$action && !$id) {
        // Save new event
        $stmt = $conn->prepare("INSERT INTO events (title, start, end, description, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $title, $start, $end, $description, $userId);
        $stmt->execute();
        $response = ['success' => true, 'message' => 'Event created'];
    } elseif ($action === 'update' && $id) {
        $sql = "UPDATE events SET title=?, start=?, end=?, description=? WHERE id=?";
        if ($userType !== 'admin') {
            $sql .= " AND created_by=?";
        }
        $stmt = $conn->prepare($sql);
        if ($userType === 'admin') {
            $stmt->bind_param("ssssi", $title, $start, $end, $description, $id);
        } else {
            $stmt->bind_param("sssiii", $title, $start, $end, $description, $id, $userId);
        }
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $response = ['success' => true, 'message' => 'Event updated'];
        } else {
            http_response_code(403);
            $response = ['success' => false, 'message' => 'You are not allowed to update this event.'];
        }
    } elseif ($action === 'delete' && $id) {
        $sql = "DELETE FROM events WHERE id=?";
        if ($userType !== 'admin') {
            $sql .= " AND created_by=?";
        }
        $stmt = $conn->prepare($sql);
        if ($userType === 'admin') {
            $stmt->bind_param("i", $id);
        } else {
            $stmt->bind_param("ii", $id, $userId);
        }
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $response = ['success' => true, 'message' => 'Event deleted'];
        } else {
            http_response_code(403);
            $response = ['success' => false, 'message' => 'You are not allowed to delete this event.'];
        }
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
