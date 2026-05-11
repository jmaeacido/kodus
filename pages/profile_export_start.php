<?php

declare(strict_types=1);

require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/profile_export_job_helpers.php';

header('Content-Type: application/json');

auth_handle_page_access($conn);
if (!auth_can_view_operations()) {
    security_send_json([
        'success' => false,
        'message' => 'Your current role does not include access to the MEB profile generator.',
    ], 403);
}
security_require_method(['POST']);
security_require_csrf_token();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$year = (int) ($_SESSION['selected_year'] ?? 0);
if ($year <= 0) {
    security_send_json([
        'success' => false,
        'message' => 'Fiscal year not selected. Please select a fiscal year and try again.',
    ], 400);
}

try {
    $jobToken = meb_profile_export_create_job($conn, $year, $userId);
    if (!meb_profile_export_start_background_job($jobToken)) {
        throw new RuntimeException('Unable to start the background profile generator.');
    }
    $job = meb_profile_export_get_job_for_user($conn, $jobToken, $userId);

    security_send_json([
        'success' => true,
        'message' => 'Profile file generation started in the background.',
        'job' => $job ? meb_profile_export_job_payload($job) : null,
    ], 202);
} catch (Throwable $e) {
    security_send_json([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}
