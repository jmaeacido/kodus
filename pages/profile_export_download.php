<?php

declare(strict_types=1);

require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/profile_export_job_helpers.php';

auth_handle_page_access($conn);
if (!auth_can_view_operations()) {
    http_response_code(403);
    exit('Your current role does not include access to the MEB profile generator.');
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$jobToken = preg_replace('/[^a-f0-9]/i', '', (string) ($_GET['job'] ?? ''));
$job = $jobToken !== '' ? meb_profile_export_get_job_for_user($conn, $jobToken, $userId) : null;

if (!$job || (string) ($job['status'] ?? '') !== 'completed') {
    http_response_code(404);
    exit('Profile export is not available.');
}

$path = (string) ($job['output_path'] ?? '');
if ($path === '' || !is_file($path)) {
    http_response_code(404);
    exit('Profile export file was not found.');
}

$filename = (string) ($job['output_filename'] ?? '');
if ($filename === '') {
    $filename = 'Partner-Beneficiaries Profile.xlsx';
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($path);
exit;
