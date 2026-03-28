<?php
// deduplication/cancel_job.php
session_start();
require_once __DIR__ . '/../config.php';

// if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    // header("HTTP/1.1 403 Forbidden");
    // echo "Access denied. Admins only.";
    // exit;
// }

$jobId = intval($_GET['job'] ?? $_POST['job'] ?? 0);
if (!$jobId) {
    http_response_code(400);
    echo "Missing job id.";
    exit;
}

// Look up job
$stmt = $conn->prepare("SELECT id, status FROM deduplication_jobs WHERE id=?");
$stmt->bind_param("i", $jobId);
$stmt->execute();
$res = $stmt->get_result();
$job = $res->fetch_assoc();
$stmt->close();

if (!$job) {
    http_response_code(404);
    echo "Job not found.";
    exit;
}

if (!in_array($job['status'], ['pending','processing'])) {
    http_response_code(409);
    echo "Job already finished or cannot be cancelled.";
    exit;
}

// Mark as cancelled
$stmt = $conn->prepare("UPDATE deduplication_jobs SET status='cancelled', last_activity=NOW() WHERE id=?");
$stmt->bind_param("i", $jobId);
$stmt->execute();
$stmt->close();

echo "Job $jobId cancelled.";