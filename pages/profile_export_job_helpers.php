<?php

declare(strict_types=1);

require_once __DIR__ . '/profile_export_helpers.php';
require_once __DIR__ . '/../app_notification_helpers.php';
require_once __DIR__ . '/../base_url.php';

function meb_profile_export_jobs_dir(): string
{
    $preferredDir = __DIR__ . '/profile_exports';
    if (meb_profile_export_prepare_jobs_dir($preferredDir)) {
        return $preferredDir;
    }

    $fallbackDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'kodus-meb-profile-exports';
    if (meb_profile_export_prepare_jobs_dir($fallbackDir)) {
        error_log(sprintf('MEB profile export folder is not writable; using fallback %s', $fallbackDir));
        return $fallbackDir;
    }

    throw new RuntimeException('The profile export folder is not writable.');
}

function meb_profile_export_prepare_jobs_dir(string $dir): bool
{
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }

    @chmod($dir, 02775);

    return is_writable($dir);
}

function meb_profile_export_ensure_schema(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS meb_profile_export_jobs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_token VARCHAR(32) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'queued',
            progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
            current_step VARCHAR(191) NOT NULL DEFAULT 'Queued',
            fiscal_year INT NOT NULL,
            output_path VARCHAR(1024) DEFAULT NULL,
            output_filename VARCHAR(255) DEFAULT NULL,
            message TEXT NULL,
            requested_by INT NULL,
            started_at DATETIME NULL,
            finished_at DATETIME NULL,
            failed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_meb_profile_export_job_token (job_token),
            INDEX idx_meb_profile_export_requested_by (requested_by),
            INDEX idx_meb_profile_export_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $checked = true;
}

function meb_profile_export_create_job(mysqli $conn, int $year, int $requestedBy): string
{
    meb_profile_export_ensure_schema($conn);
    meb_profile_export_jobs_dir();

    $token = bin2hex(random_bytes(16));
    $stmt = $conn->prepare("
        INSERT INTO meb_profile_export_jobs
            (job_token, status, progress, current_step, fiscal_year, requested_by, message)
        VALUES (?, 'queued', 5, 'Queued', ?, ?, 'Waiting for the background profile generator to start.')
    ");
    if (!$stmt) {
        throw new RuntimeException('Unable to create the profile export job.');
    }

    $stmt->bind_param('sii', $token, $year, $requestedBy);
    $stmt->execute();
    $stmt->close();

    return $token;
}

function meb_profile_export_find_php_binary(): string
{
    foreach ([PHP_BINDIR . DIRECTORY_SEPARATOR . 'php', PHP_BINARY] as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
            return $candidate;
        }
    }

    return 'php';
}

function meb_profile_export_start_background_job(string $jobToken): bool
{
    $php = meb_profile_export_find_php_binary();
    $worker = __DIR__ . '/profile_export_worker.php';
    $command = escapeshellarg($php) . ' ' . escapeshellarg($worker) . ' ' . escapeshellarg($jobToken) . ' > /dev/null 2>&1 &';
    $handle = @popen($command, 'r');
    if (!is_resource($handle)) {
        return false;
    }
    pclose($handle);

    return true;
}

function meb_profile_export_update_job(mysqli $conn, string $jobToken, array $fields): void
{
    meb_profile_export_ensure_schema($conn);

    $allowed = [
        'status' => 's',
        'progress' => 'i',
        'current_step' => 's',
        'message' => 's',
        'output_path' => 's',
        'output_filename' => 's',
        'started_at' => 'raw',
        'finished_at' => 'raw',
        'failed_at' => 'raw',
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
    $stmt = $conn->prepare('UPDATE meb_profile_export_jobs SET ' . implode(', ', $sets) . ' WHERE job_token = ? LIMIT 1');
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

function meb_profile_export_get_job(mysqli $conn, string $jobToken): ?array
{
    meb_profile_export_ensure_schema($conn);
    $stmt = $conn->prepare('SELECT * FROM meb_profile_export_jobs WHERE job_token = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $jobToken);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();

    return $row ?: null;
}

function meb_profile_export_get_job_for_user(mysqli $conn, string $jobToken, int $userId): ?array
{
    meb_profile_export_ensure_schema($conn);
    $stmt = $conn->prepare('SELECT * FROM meb_profile_export_jobs WHERE job_token = ? AND requested_by = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('si', $jobToken, $userId);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();

    return $row ?: null;
}

function meb_profile_export_latest_job_for_user(mysqli $conn, int $userId): ?array
{
    meb_profile_export_ensure_schema($conn);
    $stmt = $conn->prepare('SELECT * FROM meb_profile_export_jobs WHERE requested_by = ? ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();

    return $row ?: null;
}

function meb_profile_export_job_payload(array $job): array
{
    $status = (string) ($job['status'] ?? 'queued');
    $token = (string) ($job['job_token'] ?? '');

    return [
        'job_token' => $token,
        'status' => $status,
        'progress' => max(0, min(100, (int) ($job['progress'] ?? 0))),
        'current_step' => (string) ($job['current_step'] ?? 'Queued'),
        'message' => (string) ($job['message'] ?? ''),
        'fiscal_year' => (int) ($job['fiscal_year'] ?? 0),
        'download_url' => $status === 'completed' ? 'profile_export_download?job=' . rawurlencode($token) : '',
        'output_filename' => (string) ($job['output_filename'] ?? ''),
        'created_at' => (string) ($job['created_at'] ?? ''),
        'updated_at' => (string) ($job['updated_at'] ?? ''),
        'finished_at' => (string) ($job['finished_at'] ?? ''),
    ];
}

function meb_profile_export_run_job(mysqli $conn, string $jobToken): void
{
    $job = meb_profile_export_get_job($conn, $jobToken);
    if (!$job || (string) ($job['status'] ?? '') !== 'queued') {
        return;
    }

    $requestedBy = (int) ($job['requested_by'] ?? 0);
    $year = (int) ($job['fiscal_year'] ?? 0);
    $filename = 'Partner-Beneficiaries Profile ' . $year . '.xlsx';
    $path = meb_profile_export_jobs_dir() . '/' . $jobToken . '.xlsx';

    try {
        meb_profile_export_update_job($conn, $jobToken, [
            'status' => 'processing',
            'progress' => 12,
            'current_step' => 'Reading records',
            'message' => 'Gathering MEB records for the profile workbook.',
            'started_at' => true,
        ]);

        meb_profile_export_update_job($conn, $jobToken, [
            'progress' => 45,
            'current_step' => 'Building workbook',
            'message' => 'Applying profile columns, formulas, and formatting.',
        ]);

        export_profile_save_workbook($conn, $year, $path);

        meb_profile_export_update_job($conn, $jobToken, [
            'status' => 'completed',
            'progress' => 100,
            'current_step' => 'Completed',
            'message' => 'Partner-Beneficiaries Profile workbook is ready to download.',
            'output_path' => $path,
            'output_filename' => $filename,
            'finished_at' => true,
        ]);

        app_notification_create($conn, [
            'category' => 'meb_profile_export',
            'title' => 'Profile file ready',
            'message' => 'Partner-Beneficiaries Profile ' . $year . ' is ready to download.',
            'url' => app_url('pages/profile_export_download?job=' . rawurlencode($jobToken)),
            'icon_class' => 'fas fa-file-excel',
            'color_class' => 'text-success',
            'target_user_id' => $requestedBy > 0 ? $requestedBy : null,
            'actor_name' => 'KODUS',
        ]);
    } catch (Throwable $e) {
        $message = 'Profile file generation failed: ' . $e->getMessage();
        meb_profile_export_update_job($conn, $jobToken, [
            'status' => 'failed',
            'progress' => 100,
            'current_step' => 'Failed',
            'message' => $message,
            'failed_at' => true,
            'finished_at' => true,
        ]);

        app_notification_create($conn, [
            'category' => 'meb_profile_export',
            'title' => 'Profile file failed',
            'message' => $message,
            'url' => app_url('pages/data-tracking-meb'),
            'icon_class' => 'fas fa-exclamation-triangle',
            'color_class' => 'text-danger',
            'target_user_id' => $requestedBy > 0 ? $requestedBy : null,
            'actor_name' => 'KODUS',
        ]);

        throw $e;
    }
}
