<?php
include('../config.php');
session_start();

$events = [];
$result = $conn->query("SELECT id, title, description, start, end, all_day, color FROM events");

while ($row = $result->fetch_assoc()) {
    $events[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'start' => $row['start'],
        'end'   => $row['end'],
        'allDay'=> (bool)$row['all_day'],
        'color' => $row['color'],
        'description' => $row['description']
    ];
}
echo json_encode($events);