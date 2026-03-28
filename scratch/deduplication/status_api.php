<?php
require_once __DIR__ . '/../config.php';

$jobId = intval($_GET['job'] ?? 0);
$stmt = $conn->prepare("SELECT status, progress FROM deduplication_jobs WHERE id=?");
$stmt->bind_param("i", $jobId);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

header('Content-Type: application/json');
echo json_encode($res ?: ['status' => 'unknown', 'progress' => 0]);