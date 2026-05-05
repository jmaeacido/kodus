<?php

declare(strict_types=1);

require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/helpers/jobs.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

auth_enforce_admin_generator_access();
mebis_template_jobs_ensure_schema($conn);

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    security_send_json([
        'success' => false,
        'message' => 'Your session expired. Please sign in again.',
    ], 403);
}

$jobToken = preg_replace('/[^a-f0-9]/i', '', (string) ($_GET['job'] ?? ''));
$job = $jobToken !== ''
    ? mebis_template_get_job_for_user($conn, $jobToken, $userId)
    : mebis_template_get_latest_job_for_user($conn, $userId);

security_send_json([
    'success' => true,
    'job' => $job ? mebis_template_job_status_payload($job, $conn) : null,
]);
