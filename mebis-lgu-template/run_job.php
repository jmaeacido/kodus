<?php

declare(strict_types=1);

require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/helpers/jobs.php';

function mebis_template_runner_respond(array $payload, int $statusCode = 200): void
{
    $json = json_encode($payload);
    if (!is_string($json)) {
        $json = '{"success":false,"message":"Unable to start the background generator."}';
        $statusCode = 500;
    }

    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Connection: close');
    header('Content-Length: ' . strlen($json));
    echo $json;

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        return;
    }

    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    flush();
}

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

$job = mebis_template_get_job_for_user($conn, $jobToken, $userId);
if (!$job) {
    security_send_json([
        'success' => false,
        'message' => 'Background generation job was not found.',
    ], 404);
}

$status = (string) ($job['status'] ?? '');
if ($status !== 'queued') {
    security_send_json([
        'success' => true,
        'message' => 'Background generation is already running or finished.',
        'job' => mebis_template_job_status_payload($job, $conn),
    ]);
}

mebis_template_start_background_job($jobToken);

ignore_user_abort(true);
mebis_template_runner_respond([
    'success' => true,
    'message' => 'Background generation started.',
    'job' => mebis_template_job_status_payload($job, $conn),
], 202);

try {
    mebis_template_run_job($conn, $jobToken);
} catch (Throwable $jobException) {
    error_log('MEBIS LGU template fallback runner failed: ' . $jobException->getMessage());
}
