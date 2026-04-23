<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
include('../config.php');

auth_handle_page_access($conn);
auth_apply_security_headers();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userType = (string) ($_SESSION['user_type'] ?? '');

$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch events
    $stmt = $conn->prepare("SELECT id, title, start, end, allDay FROM events WHERE created_by = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = db_stmt_fetch_all_assoc($stmt);
    $events = [];
    foreach ($result as $row) {
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
    security_enforce_same_origin();
    security_require_csrf_token();
    $title = $_POST['title'];
    $start = $_POST['start'];
    $stmt = $conn->prepare("INSERT INTO events (title, start, created_by) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $title, $start, $userId);
    $stmt->execute();
    echo json_encode(['id' => $stmt->insert_id]);
    exit;
}

if ($action === 'update') {
    security_enforce_same_origin();
    security_require_csrf_token();
    $id = intval($_POST['id']);
    $title = $_POST['title'];
    $start = $_POST['start'];
    $end = $_POST['end'];
    $allDay = intval($_POST['allDay']);
    $sql = "UPDATE events SET title=?, start=?, end=?, allDay=? WHERE id=?";
    if ($userType !== 'admin') {
        $sql .= " AND created_by=?";
    }
    $stmt = $conn->prepare($sql);
    if ($userType === 'admin') {
        $stmt->bind_param("sssii", $title, $start, $end, $allDay, $id);
    } else {
        $stmt->bind_param("sssiii", $title, $start, $end, $allDay, $id, $userId);
    }
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'updated']);
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
    }
    exit;
}

if ($action === 'delete') {
    security_enforce_same_origin();
    security_require_csrf_token();
    $id = intval($_POST['id']);
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
        echo json_encode(['status' => 'deleted']);
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
    }
    exit;
}
