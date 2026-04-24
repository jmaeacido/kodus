<?php

declare(strict_types=1);

ini_set('memory_limit', '1536M');
set_time_limit(0);

require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers/template_generator.php';
require_once __DIR__ . '/helpers/validator.php';

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

function dedup_template_finish(bool $success, string $message, int $statusCode = 200, array $extraPayload = []): void
{
    if (dedup_template_is_ajax_request()) {
        security_send_json(array_merge([
            'success' => $success,
            'message' => $message,
        ], $extraPayload), $statusCode);
    }

    if ($success) {
        $_SESSION['dedup_template_success'] = $message;
    } else {
        $_SESSION['dedup_template_error'] = $message;
    }

    header('Location: index.php');
    exit;
}

function dedup_template_php_has_zip_support(string $phpBinary): bool
{
    $phpBinary = trim($phpBinary);
    if ($phpBinary === '' || !is_executable($phpBinary)) {
        return false;
    }

    $command = '"' . $phpBinary . '" -r "echo class_exists(\'ZipArchive\') ? \'1\' : \'0\';"';
    $output = @shell_exec($command);

    return trim((string) $output) === '1';
}

function dedup_template_php_cli_binary(): string
{
    $candidates = [
        defined('PHP_BINARY') ? PHP_BINARY : '',
        '/usr/bin/php',
        '/usr/local/bin/php',
        'C:\\xampp\\php\\php.exe',
    ];

    $candidates = array_merge(
        $candidates,
        glob('C:\\laragon\\bin\\php\\php-*\\php.exe') ?: [],
        glob('C:\\laragon\\bin\\php\\archive\\php-*\\php.exe') ?: []
    );

    foreach (array_values(array_unique(array_filter(array_map('strval', $candidates)))) as $candidate) {
        $binaryName = strtolower(basename(str_replace('\\', '/', $candidate)));
        if (!is_executable($candidate)) {
            continue;
        }

        if (!in_array($binaryName, ['php', 'php.exe', 'php8.4', 'php8.3', 'php8.2', 'php8.1', 'php8.0'], true)) {
            continue;
        }

        if (dedup_template_php_has_zip_support($candidate)) {
            return $candidate;
        }
    }

    return 'php';
}

function dedup_template_start_deduplication_job(mysqli $conn, string $templatePath, string $sourceFilename): int
{
    validateAndParseFile($templatePath);

    $uploadDir = __DIR__ . '/uploads';
    $logDir = __DIR__ . '/logs';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }
    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }

    $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $sourceFilename);
    $jobFilename = time() . '_' . ($safeName ?: 'generated_deduplication_template.xlsx');
    $targetPath = $uploadDir . '/' . $jobFilename;

    if (!copy($templatePath, $targetPath)) {
        throw new RuntimeException('Unable to queue the generated template for deduplication.');
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $rule = 'soft';
    $threshold = 85;

    $stmt = $conn->prepare("
        INSERT INTO deduplication_jobs (user_id, file_name, rule, threshold, status, progress, created_at)
        VALUES (?, ?, ?, ?, 'pending', 0, NOW())
    ");
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare deduplication job.');
    }

    $stmt->bind_param('issi', $userId, $jobFilename, $rule, $threshold);
    $stmt->execute();
    $jobId = (int) $stmt->insert_id;
    $stmt->close();

    $phpBinary = dedup_template_php_cli_binary();
    $workerPath = __DIR__ . '/worker_v2.php';

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $cmd = sprintf('cmd /c start "" /B "%s" "%s" %d', $phpBinary, $workerPath, $jobId);
        pclose(popen($cmd, 'r'));
    } else {
        $cmd = sprintf(
            'nohup %s %s %d > /dev/null 2>&1 &',
            escapeshellarg($phpBinary),
            escapeshellarg($workerPath),
            $jobId
        );
        exec($cmd);
    }

    file_put_contents(
        $logDir . '/launch_job_' . $jobId . '.log',
        date('c') . " - Job $jobId - LaunchCmd: $cmd\n",
        FILE_APPEND
    );

    return $jobId;
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

    $action = (string) ($_POST['template_action'] ?? 'generate');
    $generatedCount = 0;
    $generatedOutputs = [];
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
        $generatedOutputs[] = [
            'filename' => $filename,
            'path' => $outputPath,
        ];

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

    if ($action === 'generate_and_deduplicate') {
        if ($generatedOutputs === []) {
            throw new RuntimeException('No generated template was available to deduplicate.');
        }

        $jobId = dedup_template_start_deduplication_job(
            $conn,
            (string) $generatedOutputs[0]['path'],
            (string) $generatedOutputs[0]['filename']
        );

        dedup_template_finish(true, sprintf(
            '%d template file%s generated. Deduplication started using %s.',
            $generatedCount,
            $generatedCount === 1 ? '' : 's',
            (string) $generatedOutputs[0]['filename']
        ), 200, [
            'job_id' => $jobId,
            'redirect' => 'progress_status.php?job=' . $jobId,
        ]);
    }

    dedup_template_finish(true, sprintf(
        '%d deduplication template file%s generated successfully.',
        $generatedCount,
        $generatedCount === 1 ? '' : 's'
    ));
} catch (Throwable $e) {
    dedup_template_finish(false, $e->getMessage(), 400);
}
