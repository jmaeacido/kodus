<?php

declare(strict_types=1);

function mebis_template_outputs_dir(): string
{
    return dirname(__DIR__) . '/outputs';
}

function mebis_template_ensure_outputs_dir(): void
{
    $dir = mebis_template_outputs_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function mebis_template_history_ensure_schema(mysqli $conn): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $sql = "
        CREATE TABLE IF NOT EXISTS mebis_lgu_template_outputs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            output_token VARCHAR(32) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            municipality_name VARCHAR(191) NOT NULL DEFAULT '',
            row_count INT UNSIGNED NOT NULL DEFAULT 0,
            source_file VARCHAR(255) NOT NULL DEFAULT '',
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_mebis_lgu_output_token (output_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $conn->query($sql);
    $initialized = true;
}

function mebis_template_add_history_entry(mysqli $conn, array $entry): void
{
    mebis_template_history_ensure_schema($conn);

    $token = (string) ($entry['token'] ?? '');
    $filename = (string) ($entry['filename'] ?? '');
    $municipality = (string) ($entry['municipality_name'] ?? '');
    $rowCount = (int) ($entry['row_count'] ?? 0);
    $sourceFile = (string) ($entry['source_file'] ?? '');
    $createdBy = isset($entry['created_by']) ? (int) $entry['created_by'] : null;

    $stmt = $conn->prepare("
        INSERT INTO mebis_lgu_template_outputs (output_token, filename, municipality_name, row_count, source_file, created_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    if ($stmt === false) {
        throw new RuntimeException('Unable to prepare MEBIS LGU template history insert.');
    }

    $stmt->bind_param('sssisi', $token, $filename, $municipality, $rowCount, $sourceFile, $createdBy);
    $stmt->execute();
    $stmt->close();
}

function mebis_template_list_outputs(mysqli $conn): array
{
    mebis_template_history_ensure_schema($conn);
    mebis_template_ensure_outputs_dir();

    $result = $conn->query("
        SELECT id, output_token, filename, municipality_name, row_count, source_file, created_by, created_at
        FROM mebis_lgu_template_outputs
        ORDER BY id DESC
    ");

    if (!$result) {
        return [];
    }

    $entries = [];
    while ($row = $result->fetch_assoc()) {
        $filename = (string) ($row['filename'] ?? '');
        if ($filename === '' || !is_file(mebis_template_outputs_dir() . '/' . $filename)) {
            continue;
        }

        $entries[] = [
            'id' => (int) ($row['id'] ?? 0),
            'token' => (string) ($row['output_token'] ?? ''),
            'filename' => $filename,
            'municipality_name' => (string) ($row['municipality_name'] ?? ''),
            'rows' => (int) ($row['row_count'] ?? 0),
            'source_file' => (string) ($row['source_file'] ?? ''),
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    $result->close();
    return $entries;
}

function mebis_template_find_output(mysqli $conn, string $token): ?array
{
    mebis_template_history_ensure_schema($conn);

    $stmt = $conn->prepare("
        SELECT id, output_token, filename, municipality_name, row_count, source_file, created_by, created_at
        FROM mebis_lgu_template_outputs
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
    if ($filename === '' || !is_file(mebis_template_outputs_dir() . '/' . $filename)) {
        return null;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'token' => (string) ($row['output_token'] ?? ''),
        'filename' => $filename,
        'municipality_name' => (string) ($row['municipality_name'] ?? ''),
        'rows' => (int) ($row['row_count'] ?? 0),
        'source_file' => (string) ($row['source_file'] ?? ''),
        'created_by' => isset($row['created_by']) ? (int) ($row['created_by']) : null,
        'created_at' => (string) ($row['created_at'] ?? ''),
    ];
}
