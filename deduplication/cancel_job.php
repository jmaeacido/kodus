<?php
// deduplication/cancel_job.php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers/jobs.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();

$jobId = intval($_GET['job'] ?? $_POST['job'] ?? 0);
if (!$jobId) {
    http_response_code(400);
    echo "Missing job id.";
    exit;
}

// Look up job
$job = deduplication_fetch_accessible_job(
    $conn,
    $jobId,
    (int) ($_SESSION['user_id'] ?? 0),
    (string) ($_SESSION['user_type'] ?? ''),
    'id, user_id, status'
);

if (!$job) {
    http_response_code(403);
    echo "Job not found or access denied.";
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
