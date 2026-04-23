<?php
header('Content-Type: application/json');

// Set the year dynamically
$year = date('Y');

// Fetch from public holiday API
$url = "https://date.nager.at/api/v3/PublicHolidays/$year/PH";
$response = @file_get_contents($url);

if ($response === FALSE) {
    echo json_encode([]);
    exit;
}

$holidays = json_decode($response, true);
$events = [];

foreach ($holidays as $holiday) {
    $events[] = [
        'title' => $holiday['localName'],
        'start' => $holiday['date'],
        'allDay' => true,
        'color' => '#ff9800', // orange color for holidays
        'description' => $holiday['name'],
    ];
}

echo json_encode($events);