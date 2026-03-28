<?php
// crossmatch/progress_status.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // loads $conn (mysqli)

$jobId = $_GET['job'] ?? null;
if (!$jobId) {
    echo json_encode([
        'percent' => 0,
        'done'    => false,
        'status'  => 'No job id'
    ]);
    exit;
}

$stmt = $conn->prepare("SELECT percent, done, status FROM crossmatch_jobs WHERE id=?");
$stmt->bind_param("i", $jobId);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    echo json_encode([
        'percent' => (int)$row['percent'],
        'done'    => (bool)$row['done'],
        'status'  => $row['status'] ?? ''
    ]);
} else {
    echo json_encode([
        'percent' => 0,
        'done'    => false,
        'status'  => 'Job not found'
    ]);
}
$stmt->close();