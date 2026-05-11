<?php

declare(strict_types=1);

ini_set('memory_limit', '512M');
set_time_limit(120);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/helpers/request_letters.php';

security_bootstrap_session();
security_require_method(['POST']);
security_require_csrf_token();
auth_enforce_admin_generator_access($conn);

$token = trim((string) ($_POST['output_id'] ?? ''));
$templateKey = trim((string) ($_POST['template_key'] ?? ''));

function mebis_request_letter_json_error(string $message, int $statusCode = 400): void
{
    security_send_json([
        'success' => false,
        'message' => $message,
    ], $statusCode);
}

if ($token === '' || !preg_match('/^[a-f0-9]{16}$/i', $token)) {
    mebis_request_letter_json_error('Saved name-matching file is required.');
}

if (mebis_request_letter_template($templateKey) === null) {
    mebis_request_letter_json_error('Request-letter type is required.');
}

try {
    $entry = mebis_find_output($conn, $token);
    if ($entry === null || empty($entry['file_exists'])) {
        throw new RuntimeException('Saved name-matching file was not found.');
    }

    $manualValues = mebis_request_letter_validate_manual_fields($_POST, $templateKey);
    $result = mebis_request_letter_build_docx($entry, $templateKey, $manualValues);

    audit_log(
        $conn,
        isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        'Generate MEBIS Request Letter',
        sprintf(
            'Generated %s request letter for saved output %s (%s), batch %s, %d rows across %d municipality row(s).',
            strtoupper($templateKey),
            (string) ($entry['token'] ?? ''),
            (string) ($entry['filename'] ?? ''),
            (string) $manualValues['batch_number'],
            (int) $result['rows'],
            (int) $result['municipalities']
        ),
        security_get_client_ip()
    );

    $downloadName = $result['filename'];
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . basename((string) $downloadName) . '"');
    header('Content-Length: ' . (string) filesize((string) $result['path']));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    readfile((string) $result['path']);
    @unlink((string) $result['path']);
    exit;
} catch (Throwable $exception) {
    mebis_request_letter_json_error($exception->getMessage(), 400);
}
