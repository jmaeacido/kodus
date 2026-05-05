<?php

declare(strict_types=1);

ini_set('memory_limit', '1536M');
set_time_limit(0);

require_once __DIR__ . '/../security.php';
security_bootstrap_session();
security_configure_runtime_for_web();
security_require_method(['POST']);
security_require_csrf_token();

function mebis_template_import_generated_is_ajax_request(): bool
{
    $requestedWith = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

    return $requestedWith === 'xmlhttprequest' || strpos($accept, 'application/json') !== false;
}

$token = preg_replace('/[^a-f0-9]/i', '', (string) ($_POST['token'] ?? ''));
if ($token === '') {
    if (mebis_template_import_generated_is_ajax_request()) {
        security_send_json([
            'success' => false,
            'message' => 'No generated template was selected for import.',
        ], 400);
    }

    $_SESSION['meb_import_flash'] = [
        'type' => 'error',
        'message' => 'No generated template was selected for import.',
    ];
    header('Location: ../pages/data-tracking-meb');
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/helpers/history.php';
require_once __DIR__ . '/helpers/generated_import.php';

auth_enforce_admin_generator_access();

if (mebis_template_import_generated_is_ajax_request()) {
    try {
        security_send_json(mebis_generated_import_output($conn, $token));
    } catch (Throwable $e) {
        security_send_json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 400);
    }
}

$entry = mebis_template_find_output($conn, $token);
if (!$entry) {
    $_SESSION['meb_import_flash'] = [
        'type' => 'error',
        'message' => 'Generated template file not found.',
    ];
    header('Location: ../pages/data-tracking-meb');
    exit;
}

if (!empty($entry['is_imported'])) {
    $_SESSION['meb_import_flash'] = [
        'type' => 'error',
        'message' => 'This generated template was already imported' . (!empty($entry['imported_batch_id']) ? ' in batch ' . $entry['imported_batch_id'] : '') . '.',
    ];
    header('Location: ../pages/data-tracking-meb');
    exit;
}

$path = mebis_template_outputs_dir() . '/' . $entry['filename'];
if (!is_file($path)) {
    $_SESSION['meb_import_flash'] = [
        'type' => 'error',
        'message' => 'Generated template file is no longer available.',
    ];
    header('Location: ../pages/data-tracking-meb');
    exit;
}

$_FILES['excelFile'] = [
    'name' => (string) $entry['filename'],
    'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'tmp_name' => $path,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($path) ?: 0,
];
$_POST['import'] = '1';
$GLOBALS['mebis_generated_import_token'] = $token;

require __DIR__ . '/../pages/import.php';
