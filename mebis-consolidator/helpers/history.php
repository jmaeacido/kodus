<?php

declare(strict_types=1);

function mebis_outputs_dir(): string
{
    return dirname(__DIR__) . '/outputs';
}

function mebis_ensure_outputs_dir(): void
{
    $dir = mebis_outputs_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function mebis_history_ensure_schema(mysqli $conn): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $sql = "
        CREATE TABLE IF NOT EXISTS mebis_consolidator_outputs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            output_token VARCHAR(32) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            row_count INT UNSIGNED NOT NULL DEFAULT 0,
            source_files_json LONGTEXT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_mebis_output_token (output_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $conn->query($sql);
    $initialized = true;
}

function mebis_add_history_entry(mysqli $conn, array $entry): void
{
    mebis_history_ensure_schema($conn);

    $token = (string) ($entry['token'] ?? '');
    $filename = (string) ($entry['filename'] ?? '');
    $rowCount = (int) ($entry['row_count'] ?? 0);
    $sourceFilesJson = json_encode($entry['source_files'] ?? [], JSON_UNESCAPED_SLASHES);
    $createdBy = isset($entry['created_by']) ? (int) $entry['created_by'] : null;

    $stmt = $conn->prepare("
        INSERT INTO mebis_consolidator_outputs (output_token, filename, row_count, source_files_json, created_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    if ($stmt === false) {
        throw new RuntimeException('Unable to prepare MEBIS output history insert.');
    }

    $stmt->bind_param('ssisi', $token, $filename, $rowCount, $sourceFilesJson, $createdBy);
    $stmt->execute();
    $stmt->close();
}

function mebis_list_outputs(mysqli $conn): array
{
    mebis_history_ensure_schema($conn);
    mebis_ensure_outputs_dir();

    $result = $conn->query("
        SELECT id, output_token, filename, row_count, source_files_json, created_by, created_at
        FROM mebis_consolidator_outputs
        ORDER BY id DESC
    ");

    if (!$result) {
        return [];
    }

    $entries = [];
    while ($row = $result->fetch_assoc()) {
        $filename = (string) ($row['filename'] ?? '');
        if ($filename === '') {
            continue;
        }

        $path = mebis_outputs_dir() . '/' . $filename;
        $fileExists = is_file($path);

        $sourceFiles = json_decode((string) ($row['source_files_json'] ?? '[]'), true);
        $entries[] = [
            'id' => (int) ($row['id'] ?? 0),
            'token' => (string) ($row['output_token'] ?? ''),
            'filename' => $filename,
            'rows' => (int) ($row['row_count'] ?? 0),
            'source_files' => is_array($sourceFiles) ? $sourceFiles : [],
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'file_exists' => $fileExists,
        ];
    }

    $result->close();
    return $entries;
}

function mebis_find_output(mysqli $conn, string $token): ?array
{
    mebis_history_ensure_schema($conn);

    $stmt = $conn->prepare("
        SELECT id, output_token, filename, row_count, source_files_json, created_by, created_at
        FROM mebis_consolidator_outputs
        WHERE output_token = ?
        LIMIT 1
    ");
    if ($stmt === false) {
        return null;
    }

    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();

    if (!$row) {
        return null;
    }

    $filename = (string) ($row['filename'] ?? '');
    if ($filename === '') {
        return null;
    }

    $path = mebis_outputs_dir() . '/' . $filename;
    $fileExists = is_file($path);

    $sourceFiles = json_decode((string) ($row['source_files_json'] ?? '[]'), true);

    return [
        'id' => (int) ($row['id'] ?? 0),
        'token' => (string) ($row['output_token'] ?? ''),
        'filename' => $filename,
        'rows' => (int) ($row['row_count'] ?? 0),
        'source_files' => is_array($sourceFiles) ? $sourceFiles : [],
        'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
        'created_at' => (string) ($row['created_at'] ?? ''),
        'file_exists' => $fileExists,
    ];
}
