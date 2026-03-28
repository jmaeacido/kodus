<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require '../config.php';
require '../vendor/autoload.php';
require 'sendEventEmails.php';
require_once __DIR__ . '/event_schedule_helpers.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if (!$userId) {
    http_response_code(403);
    echo json_encode(['error'=>'not_authenticated']);
    exit;
}

calendarEventSchedulesEnsureSchema($conn);

$id    = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : $id;
$scheduleId = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
$title = trim($_POST['title'] ?? '');
$description = $_POST['description'] ?? '';
$all_day = isset($_POST['all_day']) ? (int)$_POST['all_day'] : 0;
$rawStart = $_POST['start'] ?? null;
$rawEnd   = $_POST['end'] ?? null;
$start = !empty($rawStart) ? date("Y-m-d H:i:s", strtotime($rawStart)) : null;
$end   = !empty($rawEnd) ? date("Y-m-d H:i:s", strtotime($rawEnd)) : null;
$guests = $_POST['guests'] ?? '';
$location = $_POST['location'] ?? '';
$is_private = isset($_POST['is_private']) ? (int)$_POST['is_private'] : 0;
$rawSchedules = $_POST['schedules'] ?? null;
$updated_by = $_SESSION['user_id'];

if ($scheduleId > 0 && !$all_day && $parentId > 0 && !empty($rawStart) && !empty($rawEnd)) {
    try {
        $updatedSchedule = calendarUpdateSingleSchedule($conn, $parentId, $scheduleId, $rawStart, $rawEnd);
        echo json_encode([
            'success' => true,
            'id' => $parentId,
            'schedule_id' => $scheduleId,
            'schedule' => $updatedSchedule
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($all_day) {
    $start = $start ? substr($start, 0, 10) : null;
    $end   = !empty($end) ? substr($end, 0, 10) : null;

    if (!empty($rawEnd) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawEnd) && $end) {
        $exclusiveEnd = date_create($end);
        if ($exclusiveEnd) {
            $exclusiveEnd->modify('+1 day');
            $end = $exclusiveEnd->format('Y-m-d');
        }
    }
} else {
    $start = !empty($start) ? date("Y-m-d H:i:s", strtotime($start)) : null;
    $end   = !empty($end) ? date("Y-m-d H:i:s", strtotime($end)) : null;

    try {
        $timedSchedules = calendarNormalizeTimedSchedules($rawSchedules, $start, $end);
        $aggregateRange = calendarGetScheduleAggregate($timedSchedules);
        $start = $aggregateRange['start'];
        $end = $aggregateRange['end'];
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

$color = trim($_POST['color'] ?? '#3788d8');

if ($id <= 0 || $title === '' || !$start) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$guestEmails = calendarParseGuestEmails((string) $guests);
$eventMailData = [
    'title' => $title,
    'description' => (string) $description,
    'start' => (string) $start,
    'end' => (string) ($end ?? ''),
    'allDay' => (bool) $all_day,
    'location' => (string) $location,
    'createdBy' => (string) ($_SESSION['username'] ?? 'KODUS User'),
];

$stmt = $conn->prepare("UPDATE events 
                        SET title=?, description=?, all_day=?, start=?, `end`=?, guests=?, location=?, color=?, is_private=?, updated_by=? 
                        WHERE id=?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => $conn->error]);
    exit;
}

$stmt->bind_param("ssissssssii", $title, $description, $all_day, $start, $end, $guests, $location, $color, $is_private, $updated_by, $id);

if ($stmt->execute()) {
    if ($all_day) {
        calendarReplaceEventSchedules($conn, $id, []);
    } else {
        calendarReplaceEventSchedules($conn, $id, $timedSchedules ?? []);
    }

    $response = json_encode(['success' => true, 'id' => $id]);
    notification_finish_response($response);

    if ($guestEmails) {
        sendEventEmails($guestEmails, $eventMailData, 'update');
    }
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}
