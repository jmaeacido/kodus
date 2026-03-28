<?php
include('../config.php');
require_once __DIR__ . '/event_schedule_helpers.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    echo json_encode([]);
    exit;
}

calendarEventSchedulesEnsureSchema($conn);

$sql = "SELECT e.id, e.title, e.start, e.end, e.all_day, e.color, e.description, 
               e.is_private,
               e.guests, e.location,
               c.username AS created_by_name,
               u.username AS updated_by_name
        FROM events e
        LEFT JOIN users c ON e.created_by = c.id
        LEFT JOIN users u ON e.updated_by = u.id
        WHERE e.deleted_at IS NULL
          AND (e.is_private = 0 OR e.created_by = ?)";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$eventRows = [];
$eventIds = [];
while ($row = $result->fetch_assoc()) {
    $eventRows[] = $row;
    $eventIds[] = (int)$row['id'];
}

$scheduleMap = calendarFetchSchedulesByEventIds($conn, $eventIds);

$events = [];
foreach ($eventRows as $row) {
    $dailySchedules = $scheduleMap[(int)$row['id']] ?? [];
    if (empty($dailySchedules)) {
        $dailySchedules = calendarLegacySchedulesFromEventRow($row);
    }

    $baseExtendedProps = [
        'description' => $row['description'] ?? '',
        'guests'      => $row['guests'] ?? '',
        'location'    => $row['location'] ?? '',
        'isPrivate'   => !empty($row['is_private']),
        'createdBy'   => $row['created_by_name'] ?? 'Unknown',
        'updatedBy'   => $row['updated_by_name'],
        'parentEventId' => (int)$row['id'],
        'dailySchedules' => $dailySchedules
    ];

    if ((bool)$row['all_day'] || empty($dailySchedules)) {
        $event = [
            'id'    => $row['id'],
            'title' => $row['title'],
            'start' => $row['start'],
            'allDay'=> (bool)$row['all_day'],
            'backgroundColor' => $row['color'],
            'borderColor'     => $row['color'],
            'textColor'       => '#fff',
            'extendedProps'   => $baseExtendedProps
        ];
        if (!empty($row['end'])) {
            $event['end'] = $row['end'];
        }
        $events[] = $event;
        continue;
    }

    foreach ($dailySchedules as $schedule) {
        $events[] = [
            'id' => $row['id'] . ':schedule:' . ($schedule['id'] ?: md5($schedule['start_datetime'] . $schedule['end_datetime'])),
            'groupId' => 'event-' . $row['id'],
            'title' => $row['title'],
            'start' => $schedule['start_datetime'],
            'end' => $schedule['end_datetime'],
            'allDay' => false,
            'backgroundColor' => $row['color'],
            'borderColor' => $row['color'],
            'textColor' => '#fff',
            'extendedProps' => array_merge($baseExtendedProps, [
                'eventScheduleId' => $schedule['id'],
                'scheduleDate' => $schedule['date'],
                'scheduleStartTime' => $schedule['start_time'],
                'scheduleEndTime' => $schedule['end_time']
            ])
        ];
    }
}

// Fetch holidays dynamically
$year = date('Y');
$holidayData = @file_get_contents("https://date.nager.at/api/v3/PublicHolidays/$year/PH");
$holidays = $holidayData ? json_decode($holidayData, true) : [];

foreach ($holidays as $holiday) {
    $events[] = [
        'title' => $holiday['localName'],
        'start' => $holiday['date'],
        'allDay' => true,
        'color' => '#ff9800',
        'description' => $holiday['name'],
    ];
}

echo json_encode($events);
