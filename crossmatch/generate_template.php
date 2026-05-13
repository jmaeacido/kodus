<?php

declare(strict_types=1);

ini_set('memory_limit', '1024M');
set_time_limit(0);

require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/helpers/template_generator.php';

security_require_method(['POST']);
security_require_csrf_token();
security_enforce_same_origin();
auth_handle_page_access($conn);

function crossmatch_template_ajax(): bool
{
    return strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))) === 'xmlhttprequest'
        || strpos(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json') !== false;
}

function crossmatch_template_uploaded_files(array $files): array
{
    $names = $files['name'] ?? [];
    $tmpNames = $files['tmp_name'] ?? [];
    $errors = $files['error'] ?? [];
    $sizes = $files['size'] ?? [];
    if (!is_array($names)) {
        return [];
    }

    $items = [];
    foreach ($names as $index => $name) {
        if (($errors[$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if (($errors[$index] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Workbook upload failed.');
        }
        $tmpName = (string) ($tmpNames[$index] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Workbook was not received as an uploaded file.');
        }
        $extension = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xlsm'], true)) {
            throw new RuntimeException('Accepted files: .xlsx and .xlsm.');
        }
        $items[] = ['tmp_name' => $tmpName, 'name' => (string) $name, 'size' => (int) ($sizes[$index] ?? 0)];
    }
    if ($items === []) {
        throw new RuntimeException('Please upload at least one MEB workbook.');
    }
    return $items;
}

try {
    $files = crossmatch_template_uploaded_files($_FILES['template_files'] ?? []);
    $outputs = [];
    $batchState = [];
    foreach ($files as $index => $file) {
        $dataset = dedup_template_parse_workbook($file['tmp_name'], $file['name']);
        $municipality = (string) ($dataset['municipality_name'] ?? '');
        $batch = dedup_template_next_batch_number($municipality, $batchState);
        $filename = sprintf('%03d_%s_crossmatch batch %02d.xlsx', $index + 1, dedup_template_filename_label($municipality), $batch);
        $path = crossmatch_template_outputs_dir() . '/' . $filename;
        crossmatch_template_write_workbook($dataset, $path);
        $outputs[] = [
            'filename' => $filename,
            'rows' => count($dataset['rows'] ?? []),
            'url' => 'template_generated_file.php?file=' . rawurlencode($filename),
        ];
    }

    security_send_json([
        'success' => true,
        'message' => count($outputs) . ' crossmatching template file(s) generated.',
        'outputs' => $outputs,
    ]);
} catch (Throwable $e) {
    if (crossmatch_template_ajax()) {
        security_send_json(['success' => false, 'message' => $e->getMessage()], 400);
    }
    http_response_code(400);
    echo $e->getMessage();
}
