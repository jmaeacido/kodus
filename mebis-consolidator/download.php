<?php

declare(strict_types=1);

ini_set('memory_limit', '1536M');
set_time_limit(0);

require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../env_helpers.php';
require_once __DIR__ . '/../app_location_helpers.php';
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

try {
    $mebisFiles = mebis_collect_uploaded_files($_FILES['mebis_files'] ?? []);
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
            'created_by' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        ]);
    }

    mebis_finish(true, sprintf('Consolidated CSV saved with %d rows.', count($rows)));
} catch (Throwable $e) {
    mebis_redirect_with_error($e->getMessage());
}
