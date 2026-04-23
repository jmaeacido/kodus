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
