<?php
include('../config.php');
session_start();

$userId = $_SESSION['user_id'] ?? 0;

$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch events
    $stmt = $conn->prepare("SELECT id, title, start, end, allDay FROM events WHERE created_by = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'start' => $row['start'],
            'end' => $row['end'],
            'allDay' => (bool)$row['allDay']
        ];
    }
    echo json_encode($events);
    exit;
}

if ($action === 'add') {
    $title = $_POST['title'];
    $start = $_POST['start'];
    $stmt = $conn->prepare("INSERT INTO events (title, start, created_by) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $title, $start, $userId);
    $stmt->execute();
    echo json_encode(['id' => $stmt->insert_id]);
    exit;
}

if ($action === 'update') {
    $id = intval($_POST['id']);
    $title = $_POST['title'];
    $start = $_POST['start'];
    $end = $_POST['end'];
    $allDay = intval($_POST['allDay']);
    $stmt = $conn->prepare("UPDATE events SET title=?, start=?, end=?, allDay=? WHERE id=? AND created_by=?");
    $stmt->bind_param("sssiii", $title, $start, $end, $allDay, $id, $userId);
    $stmt->execute();
    echo json_encode(['status' => 'updated']);
    exit;
}

if ($action === 'delete') {
    $id = intval($_POST['id']);
    $stmt = $conn->prepare("DELETE FROM events WHERE id=? AND created_by=?");
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
    echo json_encode(['status' => 'deleted']);
    exit;
}