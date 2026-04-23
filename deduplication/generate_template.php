<?php

declare(strict_types=1);

ini_set('memory_limit', '1536M');
set_time_limit(0);

require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers/template_generator.php';

security_configure_runtime_for_web();
security_bootstrap_session();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();
auth_handle_page_access($conn);
auth_apply_security_headers();

function dedup_template_is_ajax_request(): bool
{
    $requestedWith = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

    return $requestedWith === 'xmlhttprequest' || strpos($accept, 'application/json') !== false;
}

function dedup_template_finish(bool $success, string $message, int $statusCode = 200): void
{
    if (dedup_template_is_ajax_request()) {
        security_send_json([
            'success' => $success,
            'message' => $message,
        ], $statusCode);
    }

    if ($success) {
        $_SESSION['dedup_template_success'] = $message;
    } else {
        $_SESSION['dedup_template_error'] = $message;
    }

    header('Location: index.php');
    exit;
}

function dedup_template_assert_uploaded_file(array $file, array $allowedExtensions, string $label): array
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

function dedup_template_collect_uploaded_files(array $files): array
{
    $names = $files['name'] ?? [];
    $tmpNames = $files['tmp_name'] ?? [];
    $errors = $files['error'] ?? [];
    $sizes = $files['size'] ?? [];

    if (!is_array($names) || count($names) === 0) {
        throw new RuntimeException('Please upload at least one MEB workbook.');
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

        $items[] = dedup_template_assert_uploaded_file(
            [
                'name' => $name,
                'tmp_name' => $flatTmpNames[$index] ?? '',
                'error' => $flatErrors[$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $flatSizes[$index] ?? 0,
            ],
            ['xlsx', 'xlsm'],
            sprintf('MEB workbook #%d', $index + 1)
        );
    }

    if ($items === []) {
        throw new RuntimeException('Please upload at least one valid MEB workbook.');
    }

    return $items;
}

try {
    $files = dedup_template_collect_uploaded_files($_FILES['template_files'] ?? []);
    dedup_template_ensure_outputs_dir();

    $generatedCount = 0;
    $requestBatchState = [];

    foreach (array_values($files) as $index => $file) {
        $dataset = dedup_template_parse_workbook($file['tmp_name'], $file['name']);
        $municipality = (string) ($dataset['municipality_name'] ?? '');
        $batchNumber = dedup_template_next_batch_number($municipality, $requestBatchState);
        $filename = sprintf(
            '%03d_%s_dedup batch %02d.xlsx',
            $index + 1,
            dedup_template_filename_label($municipality),
            $batchNumber
        );

        $outputPath = dedup_template_outputs_dir() . '/' . $filename;
        dedup_template_write_workbook($dataset, $outputPath);

        dedup_template_add_history_entry($conn, [
            'token' => bin2hex(random_bytes(8)),
            'filename' => $filename,
            'municipality_name' => $municipality,
            'row_count' => count($dataset['rows'] ?? []),
            'source_file' => (string) $file['name'],
            'created_by' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        ]);

        $generatedCount++;
    }

    dedup_template_finish(true, sprintf(
        '%d deduplication template file%s generated successfully.',
        $generatedCount,
        $generatedCount === 1 ? '' : 's'
    ));
} catch (Throwable $e) {
    dedup_template_finish(false, $e->getMessage(), 400);
}
