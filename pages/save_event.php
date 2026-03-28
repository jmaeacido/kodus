<?php
include('../config.php');
session_start();

$title = $_POST['title'] ?? '';
$start = $_POST['start'] ?? '';
$end   = $_POST['end'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

if ($title && $start) {
    $stmt = $conn->prepare("INSERT INTO events (title, start, end, created_by) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $title, $start, $end, $userId);
    $stmt->execute();
    echo "success";
} else {
    echo "error";
}