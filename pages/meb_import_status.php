<?php

declare(strict_types=1);

require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/meb_import_helpers.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

auth_handle_page_access($conn);
if (($_SESSION['user_type'] ?? '') !== 'admin') {
    security_send_json([
        'success' => false,
        'message' => 'Access denied. Admins only.',
    ], 403);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$jobToken = preg_replace('/[^a-f0-9]/i', '', (string) ($_GET['job'] ?? ''));
$job = $jobToken !== ''
    ? meb_import_get_job_for_user($conn, $jobToken, $userId)
    : meb_import_latest_job_for_user($conn, $userId);

security_send_json([
    'success' => true,
    'job' => $job ? meb_import_job_payload($job) : null,
]);
