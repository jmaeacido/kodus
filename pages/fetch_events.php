<?php
include('../config.php');
require_once __DIR__ . '/event_schedule_helpers.php';
require_once __DIR__ . '/sendEventEmails.php';
require_once __DIR__ . '/../implementation-status/activity_metadata.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    echo json_encode([]);
    exit;
}

calendarEventSchedulesEnsureSchema($conn);
calendarEventGuestsEnsureSchema($conn);
ensureProgramActivityMetadata($conn, isset($_SESSION['selected_year']) ? (int) $_SESSION['selected_year'] : null);

function calendarExclusiveAllDayEnd(?string $date): ?string
{
    $date = trim((string) $date);
    if ($date === '') {
        return null;
    }

    $end = date_create($date);
    if (!$end) {
        return $date;
    }

    $end->modify('+1 day');
    return $end->format('Y-m-d');
}

function calendarAddProgramActivityEvent(array &$events, string $title, ?string $start, ?string $end, array $row, string $activityType, string $description = ''): void
{
    $start = trim((string) $start);
    if ($start === '') {
        return;
    }

    $end = trim((string) ($end ?: $start));
    if ($end === '' || $end < $start) {
        $end = $start;
    }

    $locationParts = array_filter([
        trim((string) ($row['barangay'] ?? '')),
        trim((string) ($row['municipality'] ?? '')),
        trim((string) ($row['province'] ?? '')),
    ], static fn($value) => $value !== '');

    $events[] = [
        'id' => 'program-activity-' . ($row['id'] ?? md5($title . $start . $end)) . '-' . md5($activityType . $title . $start . $end),
        'title' => $title,
        'start' => $start,
        'end' => calendarExclusiveAllDayEnd($end),
        'allDay' => true,
        'editable' => false,
        'startEditable' => false,
        'durationEditable' => false,
        'backgroundColor' => '#0f766e',
        'borderColor' => '#0f766e',
        'textColor' => '#fff',
        'extendedProps' => [
            'description' => $description !== '' ? $description : 'Program activity from Implementation Status.',
            'guests' => '',
            'location' => implode(', ', $locationParts),
            'isPrivate' => false,
            'createdBy' => 'Implementation Status',
            'updatedBy' => '',
            'sourceType' => 'program_activity',
            'sourceName' => 'Program Activities',
            'activityType' => $activityType,
            'fiscalYear' => (int) ($row['fiscal_year'] ?? 0),
        ],
    ];
}

function calendarAddProgramActivityStructuredDates(array &$events, array $row, string $activityType, string $titlePrefix, ?string $rawDates): void
{
    $rawDates = trim((string) $rawDates);
    if ($rawDates === '') {
        return;
    }

    $entries = preg_split('/\|\|/', $rawDates) ?: [];
    foreach ($entries as $index => $entry) {
        $entry = trim($entry);
        if ($entry === '') {
            continue;
        }

        if (strpos($entry, '~') !== false) {
            [$start, $end] = array_pad(explode('~', $entry, 2), 2, '');
        } else {
            $start = $entry;
            $end = $entry;
        }

        calendarAddProgramActivityEvent(
            $events,
            $titlePrefix . (count($entries) > 1 ? ' #' . ($index + 1) : ''),
            trim($start),
            trim($end),
            $row,
            $activityType
        );
    }
}

$sql = "SELECT e.id, e.title, e.start, e.end, e.all_day, e.color, e.description, 
               e.is_private,
               e.location,
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
$result = db_stmt_fetch_all_assoc($stmt);

$eventRows = [];
$eventIds = [];
foreach ($result as $row) {
    $eventRows[] = $row;
    $eventIds[] = (int)$row['id'];
}

$scheduleMap = calendarFetchSchedulesByEventIds($conn, $eventIds);
$guestMap = calendarFetchGuestsByEventIds($conn, $eventIds);

$events = [];
foreach ($eventRows as $row) {
    $dailySchedules = $scheduleMap[(int)$row['id']] ?? [];
    if (empty($dailySchedules)) {
        $dailySchedules = calendarLegacySchedulesFromEventRow($row);
    }

    $baseExtendedProps = [
        'description' => $row['description'] ?? '',
        'guests'      => $guestMap[(int) $row['id']] ?? '',
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

$programActivitySql = "
    SELECT
        id,
        fiscal_year,
        province,
        municipality,
        barangay,
        plgu_forum_from,
        plgu_forum_to,
        mlgu_forum_from,
        mlgu_forum_to,
        blgu_forum_from,
        blgu_forum_to,
        site_validation,
        stage1_start_date,
        stage1_end_date,
        stage2_start_date,
        stage2_end_date,
        stage3_start_date,
        stage3_end_date,
        drmd_monitoring_from,
        drmd_monitoring_to,
        drmd_monitoring_participants,
        joint_post_monitoring_from,
        joint_post_monitoring_to,
        joint_post_monitoring_participants,
        payout_schedule_from,
        payout_schedule_to,
        liquidation_date,
        last_day_project_implementation,
        check_issuance_date
    FROM program_activity_metadata
";

$programActivityParams = [];
$programActivityTypes = '';
if (isset($_SESSION['selected_year']) && (int) $_SESSION['selected_year'] > 0) {
    $programActivitySql .= " WHERE fiscal_year = ?";
    $programActivityParams[] = (int) $_SESSION['selected_year'];
    $programActivityTypes .= 'i';
}
$programActivitySql .= " ORDER BY fiscal_year, province, municipality, barangay";

$programActivityStmt = $conn->prepare($programActivitySql);
if ($programActivityStmt) {
    if ($programActivityParams !== []) {
        $programActivityStmt->bind_param($programActivityTypes, ...$programActivityParams);
    }
    $programActivityStmt->execute();
    $programActivityRows = db_stmt_fetch_all_assoc($programActivityStmt);
    $programActivityStmt->close();

    $seenSharedActivities = [];
    foreach ($programActivityRows as $row) {
        $province = trim((string) ($row['province'] ?? ''));
        $municipality = trim((string) ($row['municipality'] ?? ''));
        $barangay = trim((string) ($row['barangay'] ?? ''));
        $fiscalYear = (int) ($row['fiscal_year'] ?? 0);
        $locationLabel = implode(' - ', array_filter([$province, $municipality, $barangay], static fn($value) => $value !== ''));

        $provinceKey = implode('|', [$fiscalYear, $province, $row['plgu_forum_from'] ?? '', $row['plgu_forum_to'] ?? '']);
        if (!isset($seenSharedActivities['plgu'][$provinceKey]) && !empty($row['plgu_forum_from'])) {
            $seenSharedActivities['plgu'][$provinceKey] = true;
            calendarAddProgramActivityEvent($events, 'PLGU Forum - ' . $province, $row['plgu_forum_from'], $row['plgu_forum_to'], $row, 'PLGU Forum');
        }

        $municipalityKey = implode('|', [$fiscalYear, $province, $municipality, $row['mlgu_forum_from'] ?? '', $row['mlgu_forum_to'] ?? '']);
        if (!isset($seenSharedActivities['mlgu'][$municipalityKey]) && !empty($row['mlgu_forum_from'])) {
            $seenSharedActivities['mlgu'][$municipalityKey] = true;
            calendarAddProgramActivityEvent($events, 'MLGU Forum - ' . implode(', ', array_filter([$municipality, $province])), $row['mlgu_forum_from'], $row['mlgu_forum_to'], $row, 'MLGU Forum');
        }

        calendarAddProgramActivityEvent($events, 'BLGU Forum - ' . $locationLabel, $row['blgu_forum_from'], $row['blgu_forum_to'], $row, 'BLGU Forum');
        calendarAddProgramActivityStructuredDates($events, $row, 'Site Validation', 'Site Validation - ' . $locationLabel, $row['site_validation'] ?? '');
        calendarAddProgramActivityEvent($events, 'Stage 1 Cash-for-Training - ' . $locationLabel, $row['stage1_start_date'], $row['stage1_end_date'], $row, 'Stage 1 Cash-for-Training');
        calendarAddProgramActivityEvent($events, 'Stage 2 Cash-for-Work - ' . $locationLabel, $row['stage2_start_date'], $row['stage2_end_date'], $row, 'Stage 2 Cash-for-Work');
        calendarAddProgramActivityEvent($events, 'Stage 3 Sustainability Training - ' . $locationLabel, $row['stage3_start_date'], $row['stage3_end_date'], $row, 'Stage 3 Sustainability Training');
        calendarAddProgramActivityEvent($events, 'DRMD Monitoring - ' . $locationLabel, $row['drmd_monitoring_from'], $row['drmd_monitoring_to'], $row, 'DRMD Monitoring', trim((string) ($row['drmd_monitoring_participants'] ?? '')));
        calendarAddProgramActivityEvent($events, 'Joint DRMB-DRMD Post-Monitoring - ' . $locationLabel, $row['joint_post_monitoring_from'], $row['joint_post_monitoring_to'], $row, 'Joint Post-Monitoring', trim((string) ($row['joint_post_monitoring_participants'] ?? '')));
        calendarAddProgramActivityEvent($events, 'Payout Schedule - ' . $locationLabel, $row['payout_schedule_from'], $row['payout_schedule_to'], $row, 'Payout Schedule');
        calendarAddProgramActivityEvent($events, 'Liquidation Date - ' . $locationLabel, $row['liquidation_date'], $row['liquidation_date'], $row, 'Liquidation Date');
        calendarAddProgramActivityEvent($events, 'Last Day of Project Implementation - ' . $locationLabel, $row['last_day_project_implementation'], $row['last_day_project_implementation'], $row, 'Last Day of Project Implementation');
        calendarAddProgramActivityEvent($events, 'Check Issuance Date - ' . $locationLabel, $row['check_issuance_date'], $row['check_issuance_date'], $row, 'Check Issuance Date');
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
