<?php

declare(strict_types=1);

ini_set('memory_limit', '1536M');
set_time_limit(0);

require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../env_helpers.php';
require_once __DIR__ . '/../app_location_helpers.php';
require_once __DIR__ . '/helpers/history.php';
require_once __DIR__ . '/helpers/template.php';
require_once __DIR__ . '/helpers/jobs.php';

security_configure_runtime_for_web();
security_bootstrap_session();
app_load_environment();
security_enforce_same_origin();
app_apply_current_timezone();
auth_enforce_admin_generator_access();

$conn = null;

try {
    $conn = new mysqli(
        app_env('DB_HOST', '127.0.0.1') ?? '127.0.0.1',
        app_env('DB_USERNAME', 'root') ?? 'root',
        app_env('DB_PASSWORD', '') ?? '',
        app_env('DB_NAME', '') ?? ''
    );
} catch (Throwable $dbException) {
    error_log('MEBIS LGU template database connection unavailable: ' . $dbException->getMessage());
}

security_require_method(['POST']);

function mebis_template_is_ajax_request(): bool
{
    $requestedWith = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

    return $requestedWith === 'xmlhttprequest' || strpos($accept, 'application/json') !== false;
}

function mebis_template_finish(bool $success, string $message, int $statusCode = 200): void
{
    if (mebis_template_is_ajax_request()) {
        $extraPayload = [];
        if (isset($GLOBALS['mebis_template_response_payload']) && is_array($GLOBALS['mebis_template_response_payload'])) {
            $extraPayload = $GLOBALS['mebis_template_response_payload'];
        }

        if (!$success) {
            error_log(sprintf(
                'MEBIS LGU template request failed: %s [status=%d, content_length=%s, post_max_size=%s, upload_max_filesize=%s]',
                $message,
                $statusCode,
                (string) ($_SERVER['CONTENT_LENGTH'] ?? ''),
                (string) ini_get('post_max_size'),
                (string) ini_get('upload_max_filesize')
            ));
        }

        if ($success && isset($extraPayload['job_token']) && function_exists('fastcgi_finish_request')) {
            $payload = array_merge([
                'success' => true,
                'message' => $message,
                'status_code' => $statusCode,
            ], $extraPayload);

            ignore_user_abort(true);
            http_response_code($statusCode);
            header('Content-Type: application/json');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            echo json_encode($payload);

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            fastcgi_finish_request();

            $backgroundConn = $GLOBALS['conn'] ?? null;
            if ($backgroundConn instanceof mysqli) {
                try {
                    mebis_template_run_job($backgroundConn, (string) $extraPayload['job_token']);
                } catch (Throwable $backgroundException) {
                    error_log('MEBIS LGU template background run failed after response: ' . $backgroundException->getMessage());
                }
            }

            exit;
        }

        security_send_json(array_merge([
            'success' => $success,
            'message' => $message,
            'status_code' => $statusCode,
        ], $extraPayload), $success ? $statusCode : 200);
    }

    if ($success) {
        $_SESSION['mebis_template_success'] = $message;
        header('Location: index.php');
        exit;
    }

    $_SESSION['mebis_template_error'] = $message;
    header('Location: index.php');
    exit;
}

function mebis_template_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $bytes = (float) $value;

    if ($unit === 'g') {
        $bytes *= 1024;
    }

    if (in_array($unit, ['g', 'm'], true)) {
        $bytes *= 1024;
    }

    if (in_array($unit, ['g', 'm', 'k'], true)) {
        $bytes *= 1024;
    }

    return (int) $bytes;
}

function mebis_template_assert_post_body_available(): void
{
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postMaxBytes = mebis_template_ini_bytes((string) ini_get('post_max_size'));

    if ($contentLength > 0 && $postMaxBytes > 0 && $contentLength > $postMaxBytes && $_POST === [] && $_FILES === []) {
        mebis_template_finish(false, sprintf(
            'The uploaded files are too large for the server limit. Current post_max_size is %s and upload_max_filesize is %s.',
            (string) ini_get('post_max_size'),
            (string) ini_get('upload_max_filesize')
        ), 400);
    }
}

function mebis_template_redirect_with_error(string $message): void
{
    mebis_template_finish(false, $message, 400);
}

mebis_template_assert_post_body_available();
security_require_csrf_token();

function mebis_template_assert_uploaded_file(array $file, array $allowedExtensions, string $label): array
{
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException(sprintf(
            '%s is larger than the server upload limit. Current upload_max_filesize is %s.',
            $label,
            (string) ini_get('upload_max_filesize')
        ));
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        throw new RuntimeException(sprintf('%s upload failed.', $label));
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException(sprintf('%s was not received as an uploaded file.', $label));
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException(sprintf('%s must be one of: %s', $label, implode(', ', $allowedExtensions)));
    }

    return [
        'tmp_name' => $tmpName,
        'name' => $originalName,
        'size' => (int) ($file['size'] ?? 0),
    ];
}

function mebis_template_collect_uploaded_files(array $files): array
{
    $names = $files['name'] ?? [];
    $tmpNames = $files['tmp_name'] ?? [];
    $errors = $files['error'] ?? [];
    $sizes = $files['size'] ?? [];

    if (!is_array($names) || count($names) === 0) {
        throw new RuntimeException('Please upload at least one MEBIS workbook.');
    }

    $flatten = static function ($value) use (&$flatten): array {
        if (!is_array($value)) {
            return [$value];
        }

        $items = [];
        foreach ($value as $nested) {
            $items = array_merge($items, $flatten($nested));
        }
        return $items;
    };

    $flatNames = $flatten($names);
    $flatTmpNames = $flatten($tmpNames);
    $flatErrors = $flatten($errors);
    $flatSizes = $flatten($sizes);

    $items = [];
    foreach ($flatNames as $index => $name) {
        if (($flatErrors[$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $items[] = mebis_template_assert_uploaded_file(
            [
                'name' => $name,
                'tmp_name' => $flatTmpNames[$index] ?? '',
                'error' => $flatErrors[$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $flatSizes[$index] ?? 0,
            ],
            ['xlsx', 'xlsm'],
            sprintf('MEBIS workbook #%d', $index + 1)
        );
    }

    if ($items === []) {
        throw new RuntimeException('Please upload at least one valid MEBIS workbook.');
    }

    return $items;
}

try {
    $files = mebis_template_collect_uploaded_files($_FILES['mebis_files'] ?? []);
    if (!$conn instanceof mysqli) {
        throw new RuntimeException('The database connection is unavailable. Please try again.');
    }

    $requestedBy = (int) ($_SESSION['user_id'] ?? 0);
    if ($requestedBy <= 0) {
        throw new RuntimeException('Your session expired. Please sign in again before generating templates.');
    }

    $jobToken = mebis_template_create_job($conn, $files, $requestedBy);
    $GLOBALS['mebis_template_response_payload'] = ['job_token' => $jobToken];

    if (!function_exists('fastcgi_finish_request') && !mebis_template_start_background_job($jobToken)) {
        mebis_template_update_job($conn, $jobToken, [
            'status' => 'failed',
            'message' => 'Unable to start the background generator.',
            'finished_at' => true,
        ]);
        throw new RuntimeException('Unable to start the background generator. Please try again.');
    }

    mebis_template_finish(true, sprintf(
        '%d workbook%s queued for background template generation. You can continue using KODUS; a notification will appear when the files are ready.',
        count($files),
        count($files) === 1 ? ' was' : 's were'
    ), 202);
} catch (Throwable $e) {
    mebis_template_redirect_with_error($e->getMessage());
}
