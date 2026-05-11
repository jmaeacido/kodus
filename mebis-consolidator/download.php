<?php

declare(strict_types=1);

ini_set('memory_limit', '1536M');
set_time_limit(0);

require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../env_helpers.php';
require_once __DIR__ . '/../app_location_helpers.php';
require_once __DIR__ . '/../base_url.php';
require_once __DIR__ . '/../app_notification_helpers.php';
require_once __DIR__ . '/helpers/parser.php';
require_once __DIR__ . '/helpers/history.php';

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
    error_log('MEBIS consolidator database connection unavailable: ' . $dbException->getMessage());
}

security_require_method(['POST']);
security_require_csrf_token();

function mebis_is_ajax_request(): bool
{
    $requestedWith = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

    return $requestedWith === 'xmlhttprequest' || strpos($accept, 'application/json') !== false;
}

function mebis_finish(bool $success, string $message, int $statusCode = 200): void
{
    if (mebis_is_ajax_request()) {
        security_send_json([
            'success' => $success,
            'message' => $message,
        ], $statusCode);
    }

    if ($success) {
        $_SESSION['mebis_consolidator_success'] = $message;
        header('Location: index.php');
        exit;
    }

    $_SESSION['mebis_consolidator_error'] = $message;
    header('Location: index.php');
    exit;
}

function mebis_redirect_with_error(string $message): void
{
    mebis_finish(false, $message, 400);
}

function mebis_uppercase_csv_value($value): string
{
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }

    return function_exists('mb_strtoupper')
        ? mb_strtoupper($text, 'UTF-8')
        : strtoupper($text);
}

function mebis_sort_key($value): string
{
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }

    return function_exists('mb_strtoupper')
        ? mb_strtoupper($text, 'UTF-8')
        : strtoupper($text);
}

function mebis_assert_uploaded_file(array $file, array $allowedExtensions, string $label): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
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

function mebis_collect_uploaded_files(array $files): array
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

        $items[] = mebis_assert_uploaded_file(
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

function mebis_consolidator_jobs_dir(): string
{
    return mebis_consolidator_resolve_writable_jobs_dir(__DIR__ . '/jobs');
}

function mebis_consolidator_resolve_writable_jobs_dir(string $preferredDir): string
{
    if (mebis_consolidator_prepare_jobs_dir($preferredDir)) {
        return $preferredDir;
    }

    $fallbackDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'kodus-mebis-consolidator-jobs';
    if (mebis_consolidator_prepare_jobs_dir($fallbackDir)) {
        error_log(sprintf('MEBIS consolidator jobs directory is not writable; using fallback %s', $fallbackDir));
        return $fallbackDir;
    }

    throw new RuntimeException('The MEBIS consolidator job folder is not writable by the web server.');
}

function mebis_consolidator_prepare_jobs_dir(string $dir): bool
{
    if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
        return false;
    }

    @chmod($dir, 02775);

    return is_writable($dir);
}

function mebis_consolidator_store_job_files(array $files): array
{
    $token = bin2hex(random_bytes(16));
    $dir = mebis_consolidator_jobs_dir() . '/' . $token;
    if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
        throw new RuntimeException('Unable to create the background job folder.');
    }
    @chmod($dir, 02775);

    $stored = [];
    foreach (array_values($files) as $index => $file) {
        $name = (string) ($file['name'] ?? ('MEBIS workbook ' . ($index + 1)));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: 'xlsx';
        $path = $dir . '/' . sprintf('%03d_%s.%s', $index + 1, bin2hex(random_bytes(6)), $extension);
        if (!move_uploaded_file((string) $file['tmp_name'], $path)) {
            throw new RuntimeException(sprintf('Unable to queue %s for background processing.', $name));
        }
        $stored[] = [
            'tmp_name' => $path,
            'name' => $name,
            'size' => (int) ($file['size'] ?? 0),
        ];
    }

    return [$token, $stored];
}

function mebis_consolidator_status_path(string $token): string
{
    return mebis_consolidator_jobs_dir() . '/status_' . preg_replace('/[^a-f0-9]/i', '', $token) . '.json';
}

function mebis_consolidator_write_job_status(string $token, array $payload): void
{
    $status = array_merge([
        'job_token' => preg_replace('/[^a-f0-9]/i', '', $token),
        'status' => 'queued',
        'progress' => 5,
        'current_step' => 'Queued',
        'message' => 'Waiting for the background generator to start.',
        'updated_at' => date(DATE_ATOM),
    ], $payload);

    $json = json_encode($status);
    if (is_string($json)) {
        @file_put_contents(mebis_consolidator_status_path($token), $json, LOCK_EX);
    }
}

function mebis_consolidator_cleanup_job_files(string $token): void
{
    $dir = mebis_consolidator_jobs_dir() . '/' . preg_replace('/[^a-f0-9]/i', '', $token);
    foreach (glob($dir . '/*') ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    if (is_dir($dir)) {
        @rmdir($dir);
    }
}

function mebis_generate_consolidated_template(mysqli $conn = null, array $mebisFiles = [], ?int $createdBy = null): array
{
    $psgcPath = __DIR__ . '/helpers/CARAGA-PSGC-4Q-2025-Publication-Datafile.xlsx';

    if (!is_file($psgcPath)) {
        throw new RuntimeException('The bundled CARAGA PSGC helper workbook was not found.');
    }

    $psgcLookup = mebis_parse_psgc_workbook($psgcPath);

    $rows = [];
    foreach ($mebisFiles as $file) {
        $fileRows = mebis_parse_uploaded_workbook($file['tmp_name'], $file['name'], $psgcLookup);
        if ($fileRows === []) {
            throw new RuntimeException(sprintf(
                'The workbook "%s" did not produce any beneficiary rows. Please check that the MEB sheet still contains numbered beneficiary entries below the header row.',
                (string) $file['name']
            ));
        }

        $rows = array_merge($rows, $fileRows);
    }

    if ($rows === []) {
        throw new RuntimeException('No beneficiary rows were found in the uploaded MEBIS files.');
    }

    usort($rows, static function (array $a, array $b): int {
        $fields = [
            'province_name',
            'city_name',
            'barangay_name',
            'last_name',
            'first_name',
            'middle_name',
            'extName',
        ];

        foreach ($fields as $field) {
            $comparison = strcmp(
                mebis_sort_key($a[$field] ?? ''),
                mebis_sort_key($b[$field] ?? '')
            );

            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    });

    mebis_ensure_outputs_dir();

    $id = bin2hex(random_bytes(8));
    $timestamp = date('Ymd_His');
    $filename = 'mebis_consolidated_' . $timestamp . '_' . $id . '.csv';
    $outputPath = mebis_outputs_dir() . '/' . $filename;
    $headers = mebis_expected_headers();

    $output = fopen($outputPath, 'wb');
    if ($output === false) {
        throw new RuntimeException('Unable to open the CSV output stream.');
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers);

    foreach ($rows as $index => $row) {
        $row['File_number'] = $index + 1;
        $line = [];
        foreach ($headers as $header) {
            $line[] = mebis_uppercase_csv_value($row[$header] ?? '');
        }
        fputcsv($output, $line);
    }

    fclose($output);

    if ($conn instanceof mysqli) {
        mebis_add_history_entry($conn, [
            'token' => $id,
            'filename' => $filename,
            'row_count' => count($rows),
            'source_files' => array_map(static fn(array $file): string => (string) $file['name'], $mebisFiles),
            'created_by' => $createdBy,
        ]);
    }

    return [
        'token' => $id,
        'filename' => $filename,
        'rows' => count($rows),
    ];
}

try {
    $mebisFiles = mebis_collect_uploaded_files($_FILES['mebis_files'] ?? []);
    $createdBy = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

    if (mebis_is_ajax_request() && function_exists('fastcgi_finish_request')) {
        [$jobToken, $storedFiles] = mebis_consolidator_store_job_files($mebisFiles);
        mebis_consolidator_write_job_status($jobToken, [
            'status' => 'queued',
            'progress' => 5,
            'current_step' => 'Queued',
            'message' => 'Waiting for the background generator to start.',
        ]);
        ignore_user_abort(true);
        http_response_code(202);
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo json_encode([
            'success' => true,
            'job_token' => $jobToken,
            'message' => sprintf(
                '%d workbook%s queued for background name-matching template generation. A notification will appear when the CSV is ready.',
                count($storedFiles),
                count($storedFiles) === 1 ? ' was' : 's were'
            ),
        ]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        fastcgi_finish_request();

        try {
            mebis_consolidator_write_job_status($jobToken, [
                'status' => 'processing',
                'progress' => 35,
                'current_step' => 'Generating CSV',
                'message' => 'Matching records and preparing the CSV file...',
            ]);
            $result = mebis_generate_consolidated_template($conn instanceof mysqli ? $conn : null, $storedFiles, $createdBy);
            mebis_consolidator_write_job_status($jobToken, [
                'status' => 'completed',
                'progress' => 100,
                'current_step' => 'Completed',
                'message' => sprintf('MEBIS name-matching CSV saved with %d rows.', (int) $result['rows']),
                'rows' => (int) $result['rows'],
                'token' => (string) $result['token'],
            ]);
            if ($conn instanceof mysqli) {
                app_notification_create($conn, [
                    'category' => 'mebis_name_matching',
                    'title' => 'Name-matching CSV ready',
                    'message' => sprintf('MEBIS name-matching CSV saved with %d rows.', (int) $result['rows']),
                    'url' => app_url('mebis-consolidator/'),
                    'icon_class' => 'fas fa-file-csv',
                    'color_class' => 'text-success',
                    'actor_user_id' => null,
                    'target_user_id' => $createdBy,
                    'actor_name' => 'KODUS',
                ]);
            }
        } catch (Throwable $backgroundException) {
            mebis_consolidator_write_job_status($jobToken, [
                'status' => 'failed',
                'progress' => 100,
                'current_step' => 'Failed',
                'message' => 'Template generation failed: ' . $backgroundException->getMessage(),
            ]);
            if ($conn instanceof mysqli) {
                app_notification_create($conn, [
                    'category' => 'mebis_name_matching',
                    'title' => 'Name-matching CSV failed',
                    'message' => 'Template generation failed: ' . $backgroundException->getMessage(),
                    'url' => app_url('mebis-consolidator/'),
                    'icon_class' => 'fas fa-exclamation-triangle',
                    'color_class' => 'text-danger',
                    'actor_user_id' => null,
                    'target_user_id' => $createdBy,
                    'actor_name' => 'KODUS',
                ]);
            }
            error_log('MEBIS consolidator background generation failed: ' . $backgroundException->getMessage());
        } finally {
            mebis_consolidator_cleanup_job_files($jobToken);
        }

        exit;
    }

    $result = mebis_generate_consolidated_template($conn instanceof mysqli ? $conn : null, $mebisFiles, $createdBy);
    mebis_finish(true, sprintf('Consolidated CSV saved with %d rows.', (int) $result['rows']));
} catch (Throwable $e) {
    mebis_redirect_with_error($e->getMessage());
}
