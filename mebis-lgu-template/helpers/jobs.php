<?php

declare(strict_types=1);

require_once __DIR__ . '/history.php';
require_once __DIR__ . '/template.php';
require_once dirname(__DIR__, 2) . '/app_notification_helpers.php';
require_once dirname(__DIR__, 2) . '/base_url.php';

function mebis_template_jobs_dir(): string
{
    return mebis_template_resolve_writable_jobs_dir(dirname(__DIR__) . '/jobs');
}

function mebis_template_resolve_writable_jobs_dir(string $preferredDir): string
{
    if (mebis_template_prepare_jobs_dir($preferredDir)) {
        return $preferredDir;
    }

    $fallbackDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'kodus-mebis-lgu-template-jobs';
    if (mebis_template_prepare_jobs_dir($fallbackDir)) {
        error_log(sprintf('MEBIS template jobs directory is not writable; using fallback %s', $fallbackDir));
        return $fallbackDir;
    }

    throw new RuntimeException('The MEBIS template job folder is not writable by the web server.');
}

function mebis_template_prepare_jobs_dir(string $dir): bool
{
    if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
        return false;
    }

    @chmod($dir, 02775);

    return is_writable($dir);
}

function mebis_template_ensure_jobs_dir(): void
{
    $dir = mebis_template_jobs_dir();
    if (!is_writable($dir)) {
        throw new RuntimeException('The MEBIS template job folder is not writable by the web server.');
    }
}

function mebis_template_job_dir(string $token): string
{
    return mebis_template_jobs_dir() . '/' . preg_replace('/[^a-f0-9]/i', '', $token);
}

function mebis_template_cleanup_job_files(string $jobToken): void
{
    $jobDir = mebis_template_job_dir($jobToken);
    foreach (glob($jobDir . '/*') ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    if (is_dir($jobDir)) {
        @rmdir($jobDir);
    }
}

function mebis_template_jobs_ensure_schema(mysqli $conn): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS mebis_lgu_template_jobs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_token VARCHAR(32) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'queued',
            progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
            current_step VARCHAR(191) NOT NULL DEFAULT 'Queued',
            files_manifest LONGTEXT NOT NULL,
            output_manifest LONGTEXT NULL,
            file_count INT UNSIGNED NOT NULL DEFAULT 0,
            generated_count INT UNSIGNED NOT NULL DEFAULT 0,
            message TEXT NULL,
            requested_by INT NULL,
            started_at DATETIME NULL,
            finished_at DATETIME NULL,
            failed_at DATETIME NULL,
            canceled_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_mebis_lgu_template_job_token (job_token),
            INDEX idx_mebis_lgu_template_jobs_status (status),
            INDEX idx_mebis_lgu_template_jobs_requested_by (requested_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $columns = [
        'progress' => "ALTER TABLE mebis_lgu_template_jobs ADD COLUMN progress TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status",
        'current_step' => "ALTER TABLE mebis_lgu_template_jobs ADD COLUMN current_step VARCHAR(191) NOT NULL DEFAULT 'Queued' AFTER progress",
        'output_manifest' => "ALTER TABLE mebis_lgu_template_jobs ADD COLUMN output_manifest LONGTEXT NULL AFTER files_manifest",
        'failed_at' => "ALTER TABLE mebis_lgu_template_jobs ADD COLUMN failed_at DATETIME NULL AFTER finished_at",
        'canceled_at' => "ALTER TABLE mebis_lgu_template_jobs ADD COLUMN canceled_at DATETIME NULL AFTER failed_at",
    ];

    foreach ($columns as $column => $sql) {
        $result = $conn->query("SHOW COLUMNS FROM mebis_lgu_template_jobs LIKE '" . $conn->real_escape_string($column) . "'");
        if ($result && $result->num_rows === 0) {
            $conn->query($sql);
        }
        if ($result) {
            $result->close();
        }
    }

    $initialized = true;
}

function mebis_template_create_job(mysqli $conn, array $files, ?int $requestedBy): string
{
    mebis_template_jobs_ensure_schema($conn);
    mebis_template_ensure_jobs_dir();

    $token = bin2hex(random_bytes(16));
    $jobDir = mebis_template_job_dir($token);
    if (!is_dir($jobDir)) {
        mkdir($jobDir, 0777, true);
    }
    @chmod($jobDir, 02775);

    $manifest = [];
    foreach (array_values($files) as $index => $file) {
        $originalName = (string) ($file['name'] ?? ('workbook-' . ($index + 1) . '.xlsx'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'xlsx';
        $storedName = sprintf('%03d_%s.%s', $index + 1, bin2hex(random_bytes(6)), $extension);
        $targetPath = $jobDir . '/' . $storedName;
        $tmpName = (string) ($file['tmp_name'] ?? '');

        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new RuntimeException(sprintf('Unable to store %s for background processing.', $originalName));
        }

        $manifest[] = [
            'name' => $originalName,
            'path' => $targetPath,
            'size' => (int) ($file['size'] ?? 0),
        ];
    }

    $manifestJson = json_encode($manifest);
    if (!is_string($manifestJson) || $manifestJson === '') {
        throw new RuntimeException('Unable to prepare the MEBIS template job manifest.');
    }

    $fileCount = count($manifest);
    $stmt = $conn->prepare("
        INSERT INTO mebis_lgu_template_jobs
            (job_token, status, progress, current_step, files_manifest, file_count, requested_by, message)
        VALUES (?, 'queued', 5, 'Queued', ?, ?, ?, 'Waiting for the background generator to start.')
    ");
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare the MEBIS template job.');
    }

    $stmt->bind_param('ssii', $token, $manifestJson, $fileCount, $requestedBy);
    $stmt->execute();
    $stmt->close();

    return $token;
}

function mebis_template_find_php_binary(): string
{
    $candidates = [
        PHP_BINDIR . DIRECTORY_SEPARATOR . (stripos(PHP_OS_FAMILY, 'Windows') === 0 ? 'php.exe' : 'php'),
        PHP_BINARY,
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
            return $candidate;
        }
    }

    return 'php';
}

function mebis_template_start_background_job(string $jobToken): bool
{
    $php = mebis_template_find_php_binary();
    $worker = dirname(__DIR__) . '/worker.php';

    if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
        $quote = static function (string $value): string {
            return "'" . str_replace("'", "''", $value) . "'";
        };
        $script = 'Start-Process -FilePath '
            . $quote($php)
            . ' -ArgumentList @('
            . $quote($worker)
            . ', '
            . $quote($jobToken)
            . ') -WindowStyle Hidden';
        $command = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -Command '
            . escapeshellarg($script);
        $handle = @popen($command, 'r');
        if (!is_resource($handle)) {
            return false;
        }
        pclose($handle);
        return true;
    }

    $command = escapeshellarg($php) . ' ' . escapeshellarg($worker) . ' ' . escapeshellarg($jobToken) . ' > /dev/null 2>&1 &';
    $handle = @popen($command, 'r');
    if (!is_resource($handle)) {
        return false;
    }
    pclose($handle);
    return true;
}

function mebis_template_update_job(mysqli $conn, string $jobToken, array $fields): void
{
    mebis_template_jobs_ensure_schema($conn);

    $allowed = [
        'status' => 's',
        'progress' => 'i',
        'current_step' => 's',
        'generated_count' => 'i',
        'message' => 's',
        'output_manifest' => 's',
        'started_at' => 'raw',
        'finished_at' => 'raw',
        'failed_at' => 'raw',
        'canceled_at' => 'raw',
    ];

    $sets = [];
    $types = '';
    $values = [];

    foreach ($fields as $field => $value) {
        if (!isset($allowed[$field])) {
            continue;
        }

        if ($allowed[$field] === 'raw') {
            $sets[] = $field . ' = NOW()';
            continue;
        }

        $sets[] = $field . ' = ?';
        $types .= $allowed[$field];
        $values[] = $allowed[$field] === 'i' ? (int) $value : (string) $value;
    }

    if ($sets === []) {
        return;
    }

    $types .= 's';
    $values[] = $jobToken;
    $sql = 'UPDATE mebis_lgu_template_jobs SET ' . implode(', ', $sets) . ' WHERE job_token = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return;
    }

    $bindValues = [$types];
    foreach ($values as $index => $value) {
        $bindValues[] = &$values[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindValues);
    $stmt->execute();
    $stmt->close();
}

function mebis_template_get_job(mysqli $conn, string $jobToken): ?array
{
    mebis_template_jobs_ensure_schema($conn);

    $stmt = $conn->prepare('SELECT * FROM mebis_lgu_template_jobs WHERE job_token = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $jobToken);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();

    return $row ?: null;
}

function mebis_template_get_latest_job_for_user(mysqli $conn, int $userId): ?array
{
    mebis_template_jobs_ensure_schema($conn);

    if ($userId <= 0) {
        return null;
    }

    $stmt = $conn->prepare('SELECT * FROM mebis_lgu_template_jobs WHERE requested_by = ? ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();

    return $row ?: null;
}

function mebis_template_get_job_for_user(mysqli $conn, string $jobToken, int $userId): ?array
{
    mebis_template_jobs_ensure_schema($conn);

    if ($userId <= 0 || $jobToken === '') {
        return null;
    }

    $stmt = $conn->prepare('SELECT * FROM mebis_lgu_template_jobs WHERE job_token = ? AND requested_by = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('si', $jobToken, $userId);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();

    return $row ?: null;
}

function mebis_template_cancel_job_for_user(mysqli $conn, string $jobToken, int $userId): bool
{
    mebis_template_jobs_ensure_schema($conn);

    if ($userId <= 0 || $jobToken === '') {
        return false;
    }

    $job = mebis_template_get_job_for_user($conn, $jobToken, $userId);
    $wasQueued = $job !== null && (string) ($job['status'] ?? '') === 'queued';

    $stmt = $conn->prepare("
        UPDATE mebis_lgu_template_jobs
        SET status = 'canceled',
            progress = CASE WHEN progress < 100 THEN progress ELSE 99 END,
            current_step = 'Canceled',
            message = 'Template generation was canceled.',
            finished_at = NOW(),
            canceled_at = NOW()
        WHERE job_token = ?
          AND requested_by = ?
          AND status IN ('queued', 'processing')
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('si', $jobToken, $userId);
    $stmt->execute();
    $changed = $stmt->affected_rows > 0;
    $stmt->close();

    if ($changed && $wasQueued) {
        mebis_template_cleanup_job_files($jobToken);
    }

    return $changed;
}

function mebis_template_job_is_canceled(mysqli $conn, string $jobToken): bool
{
    $job = mebis_template_get_job($conn, $jobToken);
    return $job !== null && (string) ($job['status'] ?? '') === 'canceled';
}

function mebis_template_claim_queued_job(mysqli $conn, string $jobToken): bool
{
    mebis_template_jobs_ensure_schema($conn);

    $stmt = $conn->prepare("
        UPDATE mebis_lgu_template_jobs
        SET status = 'processing',
            progress = 10,
            current_step = 'Generating templates',
            started_at = COALESCE(started_at, NOW()),
            message = 'Generating import templates...'
        WHERE job_token = ?
          AND status = 'queued'
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $jobToken);
    $stmt->execute();
    $claimed = $stmt->affected_rows > 0;
    $stmt->close();

    return $claimed;
}

function mebis_template_job_status_payload(array $job, ?mysqli $conn = null): array
{
    $outputs = json_decode((string) ($job['output_manifest'] ?? '[]'), true);
    if (!is_array($outputs)) {
        $outputs = [];
    }

    $normalizedOutputs = [];
    foreach ($outputs as $output) {
        if (!is_array($output)) {
            continue;
        }

        $token = (string) ($output['token'] ?? '');
        $filename = (string) ($output['filename'] ?? '');
        if ($token === '' || $filename === '') {
            continue;
        }

        $isImported = !empty($output['is_imported']);
        $importedBatchId = (string) ($output['imported_batch_id'] ?? '');

        if ($conn instanceof mysqli) {
            $historyEntry = mebis_template_find_output($conn, $token);
            if ($historyEntry) {
                $isImported = !empty($historyEntry['is_imported']);
                $importedBatchId = (string) ($historyEntry['imported_batch_id'] ?? $importedBatchId);
            }
        }

        $normalizedOutputs[] = [
            'token' => $token,
            'filename' => $filename,
            'municipality_name' => (string) ($output['municipality_name'] ?? ''),
            'rows' => (int) ($output['rows'] ?? 0),
            'is_imported' => $isImported,
            'imported_batch_id' => $importedBatchId,
            'xlsx_url' => 'file?id=' . rawurlencode($token),
            'csv_url' => 'file_csv?id=' . rawurlencode($token),
        ];
    }

    $status = (string) ($job['status'] ?? 'queued');
    $safeMessage = trim((string) ($job['message'] ?? ''));
    if ($status === 'failed' && $safeMessage === '') {
        $safeMessage = 'Template generation failed. Please review the uploaded workbook and try again.';
    } elseif ($status === 'canceled' && $safeMessage === '') {
        $safeMessage = 'Template generation was canceled.';
    }

    return [
        'job_token' => (string) ($job['job_token'] ?? ''),
        'status' => $status,
        'progress' => max(0, min(100, (int) ($job['progress'] ?? 0))),
        'current_step' => (string) ($job['current_step'] ?? 'Queued'),
        'message' => $safeMessage,
        'file_count' => (int) ($job['file_count'] ?? 0),
        'generated_count' => (int) ($job['generated_count'] ?? 0),
        'started_at' => (string) ($job['started_at'] ?? ''),
        'finished_at' => (string) ($job['finished_at'] ?? ''),
        'failed_at' => (string) ($job['failed_at'] ?? ''),
        'canceled_at' => (string) ($job['canceled_at'] ?? ''),
        'created_at' => (string) ($job['created_at'] ?? ''),
        'updated_at' => (string) ($job['updated_at'] ?? ''),
        'outputs' => $normalizedOutputs,
    ];
}

function mebis_template_run_job(mysqli $conn, string $jobToken): void
{
    $job = mebis_template_get_job($conn, $jobToken);
    if (!$job) {
        throw new RuntimeException('MEBIS template background job not found.');
    }

    $requestedBy = isset($job['requested_by']) ? (int) $job['requested_by'] : null;
    if ((string) ($job['status'] ?? '') === 'canceled') {
        mebis_template_cleanup_job_files($jobToken);
        return;
    }
    if ((string) ($job['status'] ?? '') !== 'queued') {
        return;
    }

    $manifest = json_decode((string) ($job['files_manifest'] ?? '[]'), true);
    if (!is_array($manifest) || $manifest === []) {
        throw new RuntimeException('MEBIS template background job has no files to process.');
    }

    if (!mebis_template_claim_queued_job($conn, $jobToken)) {
        return;
    }

    $generatedCount = 0;
    $requestBatchState = [];
    $outputs = [];

    try {
        mebis_template_ensure_outputs_dir();
        $fileCount = max(1, count($manifest));

        foreach (array_values($manifest) as $index => $file) {
            if (mebis_template_job_is_canceled($conn, $jobToken)) {
                return;
            }

            $path = (string) ($file['path'] ?? '');
            $originalName = (string) ($file['name'] ?? 'MEBIS workbook');
            $fileNumber = $index + 1;
            $fileStartProgress = 10 + (int) floor(($index / $fileCount) * 80);

            if ($path === '' || !is_file($path)) {
                throw new RuntimeException(sprintf('%s is no longer available for processing.', $originalName));
            }

            mebis_template_update_job($conn, $jobToken, [
                'status' => 'processing',
                'progress' => max(10, $fileStartProgress),
                'current_step' => 'Reading workbook',
                'message' => sprintf('Reading workbook %d of %d: %s', $fileNumber, $fileCount, $originalName),
            ]);

            $dataset = mebis_template_parse_workbook($path, $originalName);
            if (mebis_template_job_is_canceled($conn, $jobToken)) {
                return;
            }

            $municipality = (string) ($dataset['municipality_name'] ?? '');
            $batchNumber = mebis_template_next_batch_number($municipality, $requestBatchState);
            $outputToken = bin2hex(random_bytes(8));
            $filename = sprintf(
                '%03d_%s_%s batch %02d.xlsx',
                $index + 1,
                mebis_template_filename_label($municipality),
                $outputToken,
                $batchNumber
            );

            $outputPath = mebis_template_outputs_dir() . '/' . $filename;
            mebis_template_update_job($conn, $jobToken, [
                'progress' => min(94, $fileStartProgress + (int) floor(40 / $fileCount)),
                'current_step' => 'Writing template',
                'message' => sprintf('Writing template %d of %d: %s', $fileNumber, $fileCount, $filename),
            ]);

            mebis_template_write_workbook($dataset, $outputPath);
            if (mebis_template_job_is_canceled($conn, $jobToken)) {
                return;
            }

            mebis_template_add_history_entry($conn, [
                'token' => $outputToken,
                'filename' => $filename,
                'municipality_name' => $municipality,
                'row_count' => count($dataset['rows'] ?? []),
                'source_file' => $originalName,
                'created_by' => $requestedBy,
            ]);

            $generatedCount++;
            $outputs[] = [
                'token' => $outputToken,
                'filename' => $filename,
                'municipality_name' => $municipality,
                'rows' => count($dataset['rows'] ?? []),
                'is_imported' => false,
                'imported_batch_id' => '',
            ];

            $outputManifest = json_encode($outputs);
            mebis_template_update_job($conn, $jobToken, [
                'generated_count' => $generatedCount,
                'output_manifest' => is_string($outputManifest) ? $outputManifest : '[]',
                'progress' => min(95, 10 + (int) floor(($generatedCount / $fileCount) * 80)),
                'current_step' => 'Generated template',
                'message' => sprintf('Generated %d of %d template file%s.', $generatedCount, $fileCount, $fileCount === 1 ? '' : 's'),
            ]);
        }

        $message = sprintf(
            '%d LGU template file%s generated successfully.',
            $generatedCount,
            $generatedCount === 1 ? '' : 's'
        );

        mebis_template_update_job($conn, $jobToken, [
            'status' => 'completed',
            'progress' => 100,
            'current_step' => 'Completed',
            'generated_count' => $generatedCount,
            'message' => $message,
            'finished_at' => true,
        ]);

        app_notification_create($conn, [
            'category' => 'mebis_lgu_template',
            'title' => 'MEB import templates ready',
            'message' => $message . ' Open the Generated Files list to download them.',
            'url' => app_url('mebis-lgu-template/'),
            'icon_class' => 'fas fa-file-excel',
            'color_class' => 'text-success',
            'actor_user_id' => null,
            'target_user_id' => $requestedBy,
            'actor_name' => 'KODUS',
        ]);
    } catch (Throwable $e) {
        $message = 'Template generation failed: ' . $e->getMessage();
        mebis_template_update_job($conn, $jobToken, [
            'status' => 'failed',
            'current_step' => 'Failed',
            'message' => $message,
            'finished_at' => true,
            'failed_at' => true,
        ]);

        app_notification_create($conn, [
            'category' => 'mebis_lgu_template',
            'title' => 'MEB import template failed',
            'message' => $message,
            'url' => app_url('mebis-lgu-template/'),
            'icon_class' => 'fas fa-exclamation-triangle',
            'color_class' => 'text-danger',
            'actor_user_id' => null,
            'target_user_id' => $requestedBy,
            'actor_name' => 'KODUS',
        ]);

        throw $e;
    } finally {
        mebis_template_cleanup_job_files($jobToken);
    }
}
