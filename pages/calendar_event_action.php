<?php
include('../config.php');
session_start();

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Invalid action'];

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
        $stmt->bind_param("ssssi", $title, $start, $end, $description, $_SESSION['user_id']);
        $stmt->execute();
        $response = ['success' => true, 'message' => 'Event created'];
    } elseif ($action === 'update' && $id) {
        $stmt = $conn->prepare("UPDATE events SET title=?, start=?, end=?, description=? WHERE id=?");
        $stmt->bind_param("ssssi", $title, $start, $end, $description, $id);
        $stmt->execute();
        $response = ['success' => true, 'message' => 'Event updated'];
    } elseif ($action === 'delete' && $id) {
        $stmt = $conn->prepare("DELETE FROM events WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $response = ['success' => true, 'message' => 'Event deleted'];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);