<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers/jobs.php';

auth_handle_page_access($conn);
auth_apply_security_headers();

$jobId = intval($_GET['job'] ?? 0);
$res = deduplication_fetch_accessible_job(
    $conn,
    $jobId,
    (int) ($_SESSION['user_id'] ?? 0),
    (string) ($_SESSION['user_type'] ?? ''),
    'id, user_id, status, progress'
);

header('Content-Type: application/json');
if (!$res) {
    http_response_code(404);
    echo json_encode(['status' => 'unknown', 'progress' => 0]);
    exit;
}

unset($res['user_id']);
echo json_encode($res);
