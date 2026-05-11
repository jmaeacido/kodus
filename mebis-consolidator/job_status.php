<?php

declare(strict_types=1);

require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_helpers.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

auth_enforce_admin_generator_access($conn);

$jobToken = preg_replace('/[^a-f0-9]/i', '', (string) ($_GET['job'] ?? ''));
if ($jobToken === '') {
    security_send_json([
        'success' => false,
        'message' => 'No background generation job was selected.',
    ], 400);
}

$jobsDir = __DIR__ . '/jobs';
$statusPath = $jobsDir . '/status_' . $jobToken . '.json';

if (!is_file($statusPath)) {
    $fallbackPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'kodus-mebis-consolidator-jobs' . DIRECTORY_SEPARATOR . 'status_' . $jobToken . '.json';
    $statusPath = is_file($fallbackPath) ? $fallbackPath : $statusPath;
}

if (!is_file($statusPath)) {
    security_send_json([
        'success' => false,
        'message' => 'Background generation job was not found.',
    ], 404);
}

$payload = json_decode((string) file_get_contents($statusPath), true);
if (!is_array($payload)) {
    security_send_json([
        'success' => false,
        'message' => 'Background generation status is unavailable.',
    ], 500);
}

security_send_json([
    'success' => true,
    'job' => [
        'job_token' => (string) ($payload['job_token'] ?? $jobToken),
        'status' => (string) ($payload['status'] ?? 'queued'),
        'progress' => max(0, min(100, (int) ($payload['progress'] ?? 0))),
        'current_step' => (string) ($payload['current_step'] ?? 'Queued'),
        'message' => (string) ($payload['message'] ?? ''),
        'rows' => (int) ($payload['rows'] ?? 0),
        'token' => (string) ($payload['token'] ?? ''),
        'updated_at' => (string) ($payload['updated_at'] ?? ''),
    ],
]);
