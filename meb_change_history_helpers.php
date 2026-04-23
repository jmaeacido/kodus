<?php

function meb_change_history_ensure_schema(mysqli $conn): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    $conn->query(
        "CREATE TABLE IF NOT EXISTS meb_change_history (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            meb_id INT NOT NULL,
            user_id INT NULL DEFAULT NULL,
            edit_reason TEXT DEFAULT NULL,
            before_json LONGTEXT NOT NULL,
            after_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_meb_change_history_meb_id (meb_id),
            INDEX idx_meb_change_history_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function meb_change_history_create(
    mysqli $conn,
    int $mebId,
    ?int $userId,
    ?string $editReason,
    array $before,
    array $after
): int {
    meb_change_history_ensure_schema($conn);

    $beforeJson = json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $afterJson = json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!is_string($beforeJson) || !is_string($afterJson)) {
        return 0;
    }

    $stmt = $conn->prepare(
        'INSERT INTO meb_change_history (meb_id, user_id, edit_reason, before_json, after_json, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    );

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('iisss', $mebId, $userId, $editReason, $beforeJson, $afterJson);

    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }

    $historyId = (int) $stmt->insert_id;
    $stmt->close();

    return $historyId;
}

function meb_change_history_find(mysqli $conn, int $historyId): ?array
{
    meb_change_history_ensure_schema($conn);

    $stmt = $conn->prepare(
        'SELECT h.*, u.username, u.first_name, u.last_name
         FROM meb_change_history h
         LEFT JOIN users u ON u.id = h.user_id
         WHERE h.id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $historyId);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();

    if (!$row) {
        return null;
    }

    $row['before'] = json_decode((string) ($row['before_json'] ?? '{}'), true);
    $row['after'] = json_decode((string) ($row['after_json'] ?? '{}'), true);

    if (!is_array($row['before'])) {
        $row['before'] = [];
    }

    if (!is_array($row['after'])) {
        $row['after'] = [];
    }

    return $row;
}
