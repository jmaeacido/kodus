<?php

declare(strict_types=1);

require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/helpers/jobs.php';

header('Content-Type: application/json');

auth_enforce_admin_generator_access();
security_require_method(['POST']);
security_require_csrf_token();
mebis_template_jobs_ensure_schema($conn);

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    security_send_json([
        'success' => false,
        'message' => 'Your session expired. Please sign in again.',
    ], 403);
}

$jobToken = preg_replace('/[^a-f0-9]/i', '', (string) ($_POST['job'] ?? ''));
if ($jobToken === '') {
    security_send_json([
        'success' => false,
        'message' => 'No background generation job was selected.',
    ], 400);
}

$canceled = mebis_template_cancel_job_for_user($conn, $jobToken, $userId);
$job = mebis_template_get_job_for_user($conn, $jobToken, $userId);

security_send_json([
    'success' => $canceled,
    'message' => $canceled ? 'Background generation canceled.' : 'This job can no longer be canceled.',
    'job' => $job ? mebis_template_job_status_payload($job, $conn) : null,
]);
