<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers/jobs.php';

header('Content-Type: application/json');

auth_handle_page_access($conn);
auth_apply_security_headers();

$jobId = $_GET['job'] ?? null;
if (!$jobId) {
    echo json_encode([
        'percent' => 0,
        'done' => false,
        'status' => 'No job id',
    ]);
    exit;
}

$row = crossmatch_fetch_accessible_job(
    $conn,
    (int) $jobId,
    (int) ($_SESSION['user_id'] ?? 0),
    (string) ($_SESSION['user_type'] ?? ''),
    'id, user_id, percent, done, status'
);

if ($row) {
    echo json_encode([
        'percent' => (int) $row['percent'],
        'done' => (bool) $row['done'],
        'status' => $row['status'] ?? '',
    ]);
    exit;
}

echo json_encode([
    'percent' => 0,
    'done' => false,
    'status' => 'Job not found',
]);
