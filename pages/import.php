<?php

declare(strict_types=1);

require_once __DIR__ . '/../security.php';
security_bootstrap_session();
security_configure_runtime_for_web();
security_require_method(['POST']);
security_require_csrf_token();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../base_url.php';
require_once __DIR__ . '/meb_import_helpers.php';

function meb_import_is_ajax_request(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
}

function meb_import_redirect_with_flash(string $type, string $message): void
{
    $_SESSION['meb_import_flash'] = [
        'type' => $type,
        'message' => $message,
    ];

    if (meb_import_is_ajax_request()) {
        security_send_json([
            'success' => $type === 'success',
            'type' => $type,
            'message' => $message,
            'redirect' => app_url('pages/data-tracking-meb'),
        ], $type === 'success' ? 200 : 400);
    }

    header('Location: ' . app_url('pages/data-tracking-meb'));
    exit;
}

auth_handle_page_access($conn);
auth_apply_security_headers();

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    meb_import_redirect_with_flash('error', 'Access denied. Admins only.');
}

if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
    meb_import_redirect_with_flash('error', 'No file selected. Please choose an Excel file to import.');
}

try {
    $generatedImportToken = preg_replace('/[^a-f0-9]/i', '', (string) ($GLOBALS['mebis_generated_import_token'] ?? ''));
    $actorName = app_notification_actor_name_from_session();
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    if ($generatedImportToken !== '' && !meb_import_is_ajax_request()) {
        $result = meb_import_process_file(
            $conn,
            (string) $_FILES['excelFile']['tmp_name'],
            (string) $_FILES['excelFile']['name'],
            null,
            $userId,
            $actorName,
            $generatedImportToken
        );
        meb_import_redirect_with_flash('success', 'Data imported successfully! Batch ID: ' . $result['batch_id']);
    }

    $jobToken = meb_import_create_job($conn, $_FILES['excelFile'], $userId, $actorName, $generatedImportToken ?: null);
    if (!meb_import_start_background_job($jobToken)) {
        throw new RuntimeException('Unable to start the background importer.');
    }
    $job = meb_import_get_job_for_user($conn, $jobToken, $userId);

    security_send_json([
        'success' => true,
        'message' => 'MEB import started in the background.',
        'job' => $job ? meb_import_job_payload($job) : null,
    ], 202);
} catch (Throwable $e) {
    meb_import_redirect_with_flash('error', $e->getMessage());
}
