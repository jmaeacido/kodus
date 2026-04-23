<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../notification_helpers.php';
header('Content-Type: application/json; charset=utf-8');
require '../config.php';
require '../vendor/autoload.php';
require 'sendEventEmails.php';
require_once __DIR__ . '/event_schedule_helpers.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$userType = (string) ($_SESSION['user_type'] ?? '');
if (!$userId) {
    http_response_code(403);
    echo json_encode(['error'=>'not_authenticated']);
    exit;
}

calendarEventSchedulesEnsureSchema($conn);
calendarEventGuestsEnsureSchema($conn);

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

$ownershipSql = "SELECT created_by, title, description, all_day, start, `end`, location, color, is_private, updated_by FROM events WHERE id = ? AND deleted_at IS NULL LIMIT 1";
$ownershipStmt = $conn->prepare($ownershipSql);
if (!$ownershipStmt) {
    echo json_encode(['success' => false, 'message' => 'Could not validate event ownership.']);
    exit;
}

$ownershipStmt->bind_param("i", $id);
$ownershipStmt->execute();
$eventOwner = db_stmt_fetch_one_assoc($ownershipStmt);
$ownershipStmt->close();

if (!$eventOwner) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Event not found.']);
    exit;
}

if ($userType !== 'admin' && (int) ($eventOwner['created_by'] ?? 0) !== $userId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not allowed to update this event.']);
    exit;
}

$guestEmails = calendarParseGuestEmails((string) $guests);
$guestAuditValue = calendarGuestAuditLabel($guestEmails);
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
                        SET title=?, description=?, all_day=?, start=?, `end`=?, location=?, color=?, is_private=?, updated_by=? 
                        WHERE id=?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => $conn->error]);
    exit;
}

$stmt->bind_param("ssisssssii", $title, $description, $all_day, $start, $end, $location, $color, $is_private, $updated_by, $id);

if ($stmt->execute()) {
    calendarSyncEventGuests($conn, $id, $guestEmails);
    if ($all_day) {
        calendarReplaceEventSchedules($conn, $id, []);
    } else {
        calendarReplaceEventSchedules($conn, $id, $timedSchedules ?? []);
    }

    $changes = audit_collect_field_changes(
        [
            'title' => $eventOwner['title'] ?? null,
            'description' => $eventOwner['description'] ?? null,
            'all_day' => (int) ($eventOwner['all_day'] ?? 0),
            'start' => $eventOwner['start'] ?? null,
            'end' => $eventOwner['end'] ?? null,
            'location' => $eventOwner['location'] ?? null,
            'color' => $eventOwner['color'] ?? null,
            'is_private' => (int) ($eventOwner['is_private'] ?? 0),
            'updated_by' => $eventOwner['updated_by'] ?? null,
            'guest_emails' => null,
        ],
        [
            'title' => $title,
            'description' => $description,
            'all_day' => $all_day,
            'start' => $start,
            'end' => $end,
            'location' => $location,
            'color' => $color,
            'is_private' => $is_private,
            'updated_by' => $updated_by,
            'guest_emails' => $guestAuditValue,
        ]
    );

    audit_log(
        $conn,
        $userId,
        'Update Calendar Event',
        'Updated event ID ' . $id . ' | Changes: ' . audit_format_field_changes($changes)
    );

    $response = json_encode(['success' => true, 'id' => $id]);
    notification_finish_response($response);

    if ($guestEmails) {
        sendEventEmails($guestEmails, $eventMailData, 'update');
    }
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}
