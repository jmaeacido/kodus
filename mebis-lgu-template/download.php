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
security_require_csrf_token();

function mebis_template_is_ajax_request(): bool
{
    $requestedWith = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

    return $requestedWith === 'xmlhttprequest' || strpos($accept, 'application/json') !== false;
}

function mebis_template_finish(bool $success, string $message, int $statusCode = 200): void
{
    if (mebis_template_is_ajax_request()) {
        security_send_json([
            'success' => $success,
            'message' => $message,
        ], $statusCode);
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

function mebis_template_redirect_with_error(string $message): void
{
    mebis_template_finish(false, $message, 400);
}

function mebis_template_assert_uploaded_file(array $file, array $allowedExtensions, string $label): array
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
    mebis_template_ensure_outputs_dir();

    $generatedCount = 0;
    $requestBatchState = [];

    foreach (array_values($files) as $index => $file) {
        $dataset = mebis_template_parse_workbook($file['tmp_name'], $file['name']);
        $municipality = (string) ($dataset['municipality_name'] ?? '');
        $batchNumber = mebis_template_next_batch_number($municipality, $requestBatchState);
        $filename = sprintf(
            '%03d_%s batch %02d.xlsx',
            $index + 1,
            mebis_template_filename_label($municipality),
            $batchNumber
        );

        $outputPath = mebis_template_outputs_dir() . '/' . $filename;
        mebis_template_write_workbook($dataset, $outputPath);

        if ($conn instanceof mysqli) {
            mebis_template_add_history_entry($conn, [
                'token' => bin2hex(random_bytes(8)),
                'filename' => $filename,
                'municipality_name' => $municipality,
                'row_count' => count($dataset['rows'] ?? []),
                'source_file' => (string) $file['name'],
                'created_by' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
            ]);
        }

        $generatedCount++;
    }

    mebis_template_finish(true, sprintf(
        '%d LGU template file%s generated successfully.',
        $generatedCount,
        $generatedCount === 1 ? '' : 's'
    ));
} catch (Throwable $e) {
    mebis_template_redirect_with_error($e->getMessage());
}
