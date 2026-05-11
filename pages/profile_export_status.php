<?php

declare(strict_types=1);

require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/profile_export_job_helpers.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

auth_handle_page_access($conn);
if (!auth_can_view_operations()) {
    security_send_json([
        'success' => false,
        'message' => 'Your current role does not include access to the MEB profile generator.',
    ], 403);
}
meb_profile_export_ensure_schema($conn);

$userId = (int) ($_SESSION['user_id'] ?? 0);
$jobToken = preg_replace('/[^a-f0-9]/i', '', (string) ($_GET['job'] ?? ''));
$job = $jobToken !== ''
    ? meb_profile_export_get_job_for_user($conn, $jobToken, $userId)
    : meb_profile_export_latest_job_for_user($conn, $userId);

security_send_json([
    'success' => true,
    'job' => $job ? meb_profile_export_job_payload($job) : null,
]);
