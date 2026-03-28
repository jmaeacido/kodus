<?php

function calendarEventSchedulesEnsureSchema(mysqli $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $sql = "
        CREATE TABLE IF NOT EXISTS event_schedule_days (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            schedule_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            start_datetime DATETIME NOT NULL,
            end_datetime DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_event_schedule_days_event_id (event_id),
            INDEX idx_event_schedule_days_start_datetime (start_datetime),
            INDEX idx_event_schedule_days_end_datetime (end_datetime)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    if (!$conn->query($sql)) {
        throw new RuntimeException('Unable to ensure event schedule schema: ' . $conn->error);
    }

    $columnCheck = $conn->query("SHOW COLUMNS FROM events LIKE 'is_private'");
    if (!$columnCheck) {
        throw new RuntimeException('Unable to inspect event privacy schema: ' . $conn->error);
    }

    if ($columnCheck->num_rows === 0) {
        if (!$conn->query("ALTER TABLE events ADD COLUMN is_private TINYINT(1) NOT NULL DEFAULT 0 AFTER color")) {
            throw new RuntimeException('Unable to add event privacy column: ' . $conn->error);
        }
    }
    $columnCheck->close();

    $ensured = true;
}

function calendarNormalizeTimeValue(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^\d{2}:\d{2}$/', $value)) {
        return $value . ':00';
    }

    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
        return $value;
    }

    return null;
}

function calendarBuildScheduleRowFromDatetimes(int $scheduleId, string $startDateTime, string $endDateTime): ?array
{
    $start = date_create($startDateTime);
    $end = date_create($endDateTime);

    if (!$start || !$end || $start >= $end) {
        return null;
    }

    return [
        'id' => $scheduleId,
        'date' => $start->format('Y-m-d'),
        'start_time' => $start->format('H:i:s'),
        'end_time' => $end->format('H:i:s'),
        'start_datetime' => $start->format('Y-m-d H:i:s'),
        'end_datetime' => $end->format('Y-m-d H:i:s')
    ];
}

function calendarNormalizeTimedSchedules($rawSchedules, ?string $fallbackStart = null, ?string $fallbackEnd = null): array
{
    $decodedSchedules = $rawSchedules;

    if (is_string($rawSchedules)) {
        $trimmed = trim($rawSchedules);
        if ($trimmed !== '') {
            $decodedSchedules = json_decode($trimmed, true);
            if (!is_array($decodedSchedules)) {
                throw new InvalidArgumentException('Invalid schedules payload.');
            }
        } else {
            $decodedSchedules = [];
        }
    }

    $schedules = [];

    if (is_array($decodedSchedules)) {
        foreach ($decodedSchedules as $row) {
            if (!is_array($row)) {
                continue;
            }

            $date = trim((string)($row['date'] ?? ''));
            $startTime = calendarNormalizeTimeValue((string)($row['start_time'] ?? $row['startTime'] ?? ''));
            $endTime = calendarNormalizeTimeValue((string)($row['end_time'] ?? $row['endTime'] ?? ''));
            $scheduleId = isset($row['id']) ? (int)$row['id'] : 0;

            if ($date === '' && !$startTime && !$endTime) {
                continue;
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !$startTime || !$endTime) {
                throw new InvalidArgumentException('Each schedule row needs a date, start time, and end time.');
            }

            $startDateTime = $date . ' ' . $startTime;
            $endDateTime = $date . ' ' . $endTime;
            $schedule = calendarBuildScheduleRowFromDatetimes($scheduleId, $startDateTime, $endDateTime);

            if (!$schedule) {
                throw new InvalidArgumentException('Each schedule row must end after it starts.');
            }

            $schedules[] = $schedule;
        }
    }

    if (empty($schedules) && $fallbackStart && $fallbackEnd) {
        $legacySchedule = calendarBuildScheduleRowFromDatetimes(0, $fallbackStart, $fallbackEnd);
        if ($legacySchedule) {
            $schedules[] = $legacySchedule;
        }
    }

    usort($schedules, static function (array $left, array $right): int {
        return strcmp($left['start_datetime'], $right['start_datetime']);
    });

    return $schedules;
}

function calendarGetScheduleAggregate(array $schedules): array
{
    if (empty($schedules)) {
        return ['start' => null, 'end' => null];
    }

    $start = $schedules[0]['start_datetime'];
    $end = $schedules[0]['end_datetime'];

    foreach ($schedules as $schedule) {
        if ($schedule['start_datetime'] < $start) {
            $start = $schedule['start_datetime'];
        }
        if ($schedule['end_datetime'] > $end) {
            $end = $schedule['end_datetime'];
        }
    }

    return ['start' => $start, 'end' => $end];
}

function calendarReplaceEventSchedules(mysqli $conn, int $eventId, array $schedules): void
{
    $deleteStmt = $conn->prepare("DELETE FROM event_schedule_days WHERE event_id = ?");
    if (!$deleteStmt) {
        throw new RuntimeException('Unable to prepare schedule delete statement: ' . $conn->error);
    }
    $deleteStmt->bind_param('i', $eventId);
    if (!$deleteStmt->execute()) {
        throw new RuntimeException('Unable to clear existing schedules: ' . $deleteStmt->error);
    }
    $deleteStmt->close();

    if (empty($schedules)) {
        return;
    }

    $insertStmt = $conn->prepare("
        INSERT INTO event_schedule_days (
            event_id,
            schedule_date,
            start_time,
            end_time,
            start_datetime,
            end_datetime
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");

    if (!$insertStmt) {
        throw new RuntimeException('Unable to prepare schedule insert statement: ' . $conn->error);
    }

    foreach ($schedules as $schedule) {
        $insertStmt->bind_param(
            'isssss',
            $eventId,
            $schedule['date'],
            $schedule['start_time'],
            $schedule['end_time'],
            $schedule['start_datetime'],
            $schedule['end_datetime']
        );

        if (!$insertStmt->execute()) {
            $insertStmt->close();
            throw new RuntimeException('Unable to save event schedules: ' . $insertStmt->error);
        }
    }

    $insertStmt->close();
}

function calendarFetchSchedulesByEventIds(mysqli $conn, array $eventIds): array
{
    if (empty($eventIds)) {
        return [];
    }

    $eventIds = array_values(array_unique(array_map('intval', $eventIds)));
    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    $types = str_repeat('i', count($eventIds));

    $sql = "
        SELECT id, event_id, schedule_date, start_time, end_time, start_datetime, end_datetime
        FROM event_schedule_days
        WHERE event_id IN ($placeholders)
        ORDER BY start_datetime ASC, end_datetime ASC, id ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare schedule fetch statement: ' . $conn->error);
    }

    $stmt->bind_param($types, ...$eventIds);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to fetch event schedules: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $grouped = [];

    while ($row = $result->fetch_assoc()) {
        $grouped[(int)$row['event_id']][] = [
            'id' => (int)$row['id'],
            'date' => $row['schedule_date'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'start_datetime' => $row['start_datetime'],
            'end_datetime' => $row['end_datetime']
        ];
    }

    $stmt->close();

    return $grouped;
}

function calendarLegacySchedulesFromEventRow(array $eventRow): array
{
    if (!empty($eventRow['all_day']) || empty($eventRow['start']) || empty($eventRow['end'])) {
        return [];
    }

    $schedule = calendarBuildScheduleRowFromDatetimes(0, $eventRow['start'], $eventRow['end']);
    return $schedule ? [$schedule] : [];
}

function calendarUpdateSingleSchedule(mysqli $conn, int $eventId, int $scheduleId, string $startRaw, string $endRaw): array
{
    $schedule = calendarBuildScheduleRowFromDatetimes($scheduleId, $startRaw, $endRaw);
    if (!$schedule) {
        throw new InvalidArgumentException('Updated schedule must end after it starts.');
    }

    $stmt = $conn->prepare("
        UPDATE event_schedule_days
        SET schedule_date = ?, start_time = ?, end_time = ?, start_datetime = ?, end_datetime = ?
        WHERE id = ? AND event_id = ?
    ");

    if (!$stmt) {
        throw new RuntimeException('Unable to prepare single schedule update: ' . $conn->error);
    }

    $stmt->bind_param(
        'sssssii',
        $schedule['date'],
        $schedule['start_time'],
        $schedule['end_time'],
        $schedule['start_datetime'],
        $schedule['end_datetime'],
        $scheduleId,
        $eventId
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to update schedule row: ' . $stmt->error);
    }

    if ($stmt->affected_rows < 1) {
        $stmt->close();
        throw new RuntimeException('Schedule row not found.');
    }

    $stmt->close();

    $scheduleMap = calendarFetchSchedulesByEventIds($conn, [$eventId]);
    $eventSchedules = $scheduleMap[$eventId] ?? [];
    $aggregate = calendarGetScheduleAggregate($eventSchedules);

    $updateParentStmt = $conn->prepare("UPDATE events SET start = ?, end = ? WHERE id = ?");
    if (!$updateParentStmt) {
        throw new RuntimeException('Unable to prepare parent event update: ' . $conn->error);
    }

    $updateParentStmt->bind_param('ssi', $aggregate['start'], $aggregate['end'], $eventId);
    if (!$updateParentStmt->execute()) {
        $updateParentStmt->close();
        throw new RuntimeException('Unable to refresh parent event range: ' . $updateParentStmt->error);
    }
    $updateParentStmt->close();

    return $schedule;
}
