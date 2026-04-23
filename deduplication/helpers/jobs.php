<?php

function deduplication_fetch_accessible_job(mysqli $conn, int $jobId, int $userId, string $userType, string $columns = '*'): ?array
{
    $stmt = $conn->prepare("SELECT {$columns} FROM deduplication_jobs WHERE id = ? LIMIT 1");
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
