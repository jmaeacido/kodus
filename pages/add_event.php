<?php
session_start();
header('Content-Type: application/json');
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

try {
    calendarEventSchedulesEnsureSchema($conn);

    $id             = $_POST['id'] ?? '';
    $title          = trim($_POST['title'] ?? '');
    $description    = $_POST['description'] ?? '';
    $all_day        = isset($_POST['all_day']) ? (int)$_POST['all_day'] : 0;
    $start          = $_POST['start'] ?? null;
    $end            = $_POST['end'] ?? null;
    $guests         = $_POST['guests'] ?? null;
    $location       = $_POST['location'] ?? null;
    $color          = $_POST['color'] ?? '#3788d8';
    $is_private     = isset($_POST['is_private']) ? (int)$_POST['is_private'] : 0;
    $rawSchedules   = $_POST['schedules'] ?? null;
    $created_by     = $userId;
    $updated_by     = $userId;

    function normalizeDateTime($dateStr, $isAllDay = false, $exclusiveEnd = false) {
        if (!$dateStr) return null;

        // Handle all-day (YYYY-MM-DD)
        if ($isAllDay) {
            $dateOnly = substr($dateStr, 0, 10);

            // Modal submissions send inclusive dates, while FullCalendar
            // drag/resize updates already send an exclusive end value.
            if ($exclusiveEnd && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                $dt = date_create($dateOnly);
                if ($dt) {
                    $dt->modify('+1 day');
                    return $dt->format('Y-m-d');
                }
            }

            return $dateOnly;
        }

        // Strip milliseconds + timezone if present
        $dt = date_create($dateStr);
        if ($dt) {
            return $dt->format('Y-m-d H:i:s');
        }
        return null;
    }

    // Format dates if all_day
    $start = normalizeDateTime($start, $all_day, false);
    $end   = normalizeDateTime($end, $all_day, true);

    if ($all_day) {
        $timedSchedules = [];
    } else {
        $timedSchedules = calendarNormalizeTimedSchedules($rawSchedules, $start, $end);
        $aggregateRange = calendarGetScheduleAggregate($timedSchedules);
        $start = $aggregateRange['start'];
        $end = $aggregateRange['end'];
    }

    if ($title === '' || !$start) {
        echo json_encode(["success" => false, "message" => "Missing required fields"]);
        exit;
    }

    $guestEmails = calendarParseGuestEmails((string) ($guests ?? ''));
    $eventMailData = [
        'title' => $title,
        'description' => (string) $description,
        'start' => (string) $start,
        'end' => (string) ($end ?? ''),
        'allDay' => (bool) $all_day,
        'location' => (string) ($location ?? ''),
        'createdBy' => (string) ($_SESSION['username'] ?? 'KODUS User'),
    ];

    if ($id === '') {
        // --- INSERT NEW EVENT ---
        $stmt = $conn->prepare("
            INSERT INTO events 
            (title, description, all_day, start, end, guests, location, color, is_private, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ssisssssii", 
            $title, 
            $description, 
            $all_day, 
            $start, 
            $end, 
            $guests, 
            $location, 
            $color, 
            $is_private,
            $created_by
        );
        if ($stmt->execute()) {
            $newEventId = (int)$stmt->insert_id;

            if (!$all_day) {
                calendarReplaceEventSchedules($conn, $newEventId, $timedSchedules);
            }

            $response = json_encode([
                "success" => true,
                "id"      => $newEventId
            ]);

            notification_finish_response($response);

            if ($guestEmails) {
                sendEventEmails($guestEmails, $eventMailData, 'new');
            }
        } else {
            echo json_encode(["success" => false, "message" => "Insert failed: ".$stmt->error]);
        }
        $stmt->close();
    } else {
        // --- UPDATE EXISTING EVENT ---
        $stmt = $conn->prepare("
            UPDATE events 
            SET title=?, description=?, all_day=?, start=?, end=?, guests=?, location=?, color=?, is_private=?, updated_by=? 
            WHERE id=?
        ");
        $stmt->bind_param(
            "ssissssssii", 
            $title, 
            $description, 
            $all_day, 
            $start, 
            $end, 
            $guests, 
            $location, 
            $color, 
            $is_private,
            $updated_by, 
            $id
        );
        if ($stmt->execute()) {
            $response = json_encode([
                "success" => true,
                "id"      => $id
            ]);

            notification_finish_response($response);

            if ($guestEmails) {
                sendEventEmails($guestEmails, $eventMailData, 'update');
            }
        } else {
            echo json_encode(["success" => false, "message" => "Update failed: ".$stmt->error]);
        }
        $stmt->close();
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
