<?php

function role_change_ensure_column(mysqli $conn, string $columnName, string $definition): void
{
    $safeColumn = $conn->real_escape_string($columnName);
    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = '{$safeColumn}'
    ");

    $exists = false;
    if ($result instanceof mysqli_result) {
        $exists = (int) (($result->fetch_assoc()['total'] ?? 0)) > 0;
        $result->free();
    }

    if (!$exists) {
        $conn->query("ALTER TABLE users ADD COLUMN {$columnName} {$definition}");
    }
}

function role_change_ensure_schema(mysqli $conn): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    role_change_ensure_column($conn, 'role_change_old_type', "VARCHAR(20) DEFAULT NULL");
    role_change_ensure_column($conn, 'role_change_new_type', "VARCHAR(20) DEFAULT NULL");
    role_change_ensure_column($conn, 'role_change_reason', "VARCHAR(30) DEFAULT NULL");
    role_change_ensure_column($conn, 'role_change_message', "VARCHAR(255) DEFAULT NULL");
    role_change_ensure_column($conn, 'role_change_force_logout_at', "DATETIME DEFAULT NULL");

    $initialized = true;
}

function role_change_schedule(
    mysqli $conn,
    int $userId,
    string $oldType,
    string $newType,
    int $countdownSeconds = 20,
    string $reason = 'role_change',
    ?string $message = null
): bool
{
    role_change_ensure_schema($conn);

    $forceLogoutAt = date('Y-m-d H:i:s', time() + max(5, $countdownSeconds));
    $stmt = $conn->prepare("
        UPDATE users
        SET role_change_old_type = ?,
            role_change_new_type = ?,
            role_change_reason = ?,
            role_change_message = ?,
            role_change_force_logout_at = ?
        WHERE id = ?
    ");

    if (!$stmt) {
        return false;
    }

    $noticeMessage = $message ?? '';
    $stmt->bind_param('sssssi', $oldType, $newType, $reason, $noticeMessage, $forceLogoutAt, $userId);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function role_change_clear(mysqli $conn, int $userId): void
{
    role_change_ensure_schema($conn);

    $stmt = $conn->prepare("
        UPDATE users
        SET role_change_old_type = NULL,
            role_change_new_type = NULL,
            role_change_reason = NULL,
            role_change_message = NULL,
            role_change_force_logout_at = NULL
        WHERE id = ?
    ");

    if (!$stmt) {
        return;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

function role_change_get_state(mysqli $conn, int $userId): ?array
{
    role_change_ensure_schema($conn);

    $stmt = $conn->prepare("
        SELECT role_change_old_type, role_change_new_type, role_change_reason, role_change_message, role_change_force_logout_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    if (!$row || empty($row['role_change_force_logout_at'])) {
        return null;
    }

    $forceLogoutAt = strtotime((string) $row['role_change_force_logout_at']);
    if ($forceLogoutAt === false) {
        return null;
    }

    return [
        'old_type' => (string) ($row['role_change_old_type'] ?? ''),
        'new_type' => (string) ($row['role_change_new_type'] ?? ''),
        'reason' => (string) ($row['role_change_reason'] ?? 'role_change'),
        'message' => (string) ($row['role_change_message'] ?? ''),
        'force_logout_at' => date(DATE_ATOM, $forceLogoutAt),
        'seconds_remaining' => max(0, $forceLogoutAt - time()),
        'expired' => $forceLogoutAt <= time(),
    ];
}
