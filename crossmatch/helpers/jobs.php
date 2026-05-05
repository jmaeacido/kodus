<?php

function crossmatch_ensure_job_schema(mysqli $conn): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    $result = $conn->query("SHOW COLUMNS FROM crossmatch_jobs LIKE 'user_id'");
    if ($result && $result->num_rows > 0) {
        return;
    }

    $conn->query("ALTER TABLE crossmatch_jobs ADD COLUMN user_id INT NULL AFTER id");
}

function crossmatch_fetch_accessible_job(mysqli $conn, int $jobId, int $userId, string $userType, string $columns = '*'): ?array
{
    crossmatch_ensure_job_schema($conn);

    $stmt = $conn->prepare("SELECT {$columns} FROM crossmatch_jobs WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $jobId);
    $stmt->execute();
    $job = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();

    if (!$job) {
        return null;
    }

    if ($userType === 'admin') {
        return $job;
    }

    $ownerId = isset($job['user_id']) ? (int) $job['user_id'] : 0;
    return ($ownerId > 0 && $ownerId === $userId) ? $job : null;
}

function crossmatch_find_php_binary(): string
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

function crossmatch_start_background_job(int $jobId): bool
{
    if ($jobId <= 0) {
        return false;
    }

    $php = crossmatch_find_php_binary();
    $runner = dirname(__DIR__) . '/run_job.php';

    if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
        $quote = static function (string $value): string {
            return "'" . str_replace("'", "''", $value) . "'";
        };
        $script = 'Start-Process -FilePath '
            . $quote($php)
            . ' -ArgumentList @('
            . $quote($runner)
            . ', '
            . $quote((string) $jobId)
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

    $command = escapeshellarg($php) . ' ' . escapeshellarg($runner) . ' ' . escapeshellarg((string) $jobId) . ' > /dev/null 2>&1 &';
    $handle = @popen($command, 'r');
    if (!is_resource($handle)) {
        return false;
    }
    pclose($handle);
    return true;
}
