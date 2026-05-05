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

    @chmod($dir, 02775);

    if (!is_writable($dir)) {
        throw new RuntimeException('The generated template output folder is not writable by the web server.');
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
            imported_at DATETIME NULL,
            imported_batch_id VARCHAR(20) NULL,
            imported_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_mebis_lgu_output_token (output_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $conn->query($sql);
    $columns = [
        'imported_at' => 'ALTER TABLE mebis_lgu_template_outputs ADD COLUMN imported_at DATETIME NULL AFTER created_by',
        'imported_batch_id' => 'ALTER TABLE mebis_lgu_template_outputs ADD COLUMN imported_batch_id VARCHAR(20) NULL AFTER imported_at',
        'imported_by' => 'ALTER TABLE mebis_lgu_template_outputs ADD COLUMN imported_by INT NULL AFTER imported_batch_id',
    ];

    foreach ($columns as $column => $alterSql) {
        $result = $conn->query("SHOW COLUMNS FROM mebis_lgu_template_outputs LIKE '" . $conn->real_escape_string($column) . "'");
        if ($result && $result->num_rows === 0) {
            $conn->query($alterSql);
        }
        if ($result) {
            $result->close();
        }
    }

    $initialized = true;
}

function mebis_template_backfill_imported_outputs(mysqli $conn): void
{
    mebis_template_history_ensure_schema($conn);

    $result = $conn->query("
        SELECT output_token, municipality_name, row_count, created_at
        FROM mebis_lgu_template_outputs
        WHERE imported_at IS NULL
        ORDER BY id DESC
    ");
    if (!$result) {
        return;
    }

    $candidates = [];
    while ($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }
    $result->close();

    if ($candidates === []) {
        return;
    }

    $stmt = $conn->prepare("
        SELECT batch_id, COUNT(*) AS row_count, MIN(time_stamp) AS imported_at
        FROM meb
        WHERE UPPER(TRIM(lgu)) = UPPER(TRIM(?))
          AND time_stamp >= ?
        GROUP BY batch_id
        HAVING row_count = ?
        ORDER BY imported_at ASC
        LIMIT 1
    ");
    if (!$stmt) {
        return;
    }

    $markStmt = $conn->prepare("
        UPDATE mebis_lgu_template_outputs
        SET imported_at = ?,
            imported_batch_id = ?,
            imported_by = COALESCE(imported_by, created_by)
        WHERE output_token = ?
          AND imported_at IS NULL
        LIMIT 1
    ");
    if (!$markStmt) {
        $stmt->close();
        return;
    }

    foreach ($candidates as $candidate) {
        $municipality = (string) ($candidate['municipality_name'] ?? '');
        $rowCount = (int) ($candidate['row_count'] ?? 0);
        $createdAt = (string) ($candidate['created_at'] ?? '');
        $token = (string) ($candidate['output_token'] ?? '');

        if ($municipality === '' || $rowCount <= 0 || $createdAt === '' || $token === '') {
            continue;
        }

        $stmt->bind_param('ssi', $municipality, $createdAt, $rowCount);
        $stmt->execute();
        $match = db_stmt_fetch_one_assoc($stmt);

        if (!$match) {
            continue;
        }

        $importedAt = (string) ($match['imported_at'] ?? date('Y-m-d H:i:s'));
        $batchId = (string) ($match['batch_id'] ?? '');
        if ($batchId === '') {
            continue;
        }

        $markStmt->bind_param('sss', $importedAt, $batchId, $token);
        $markStmt->execute();
    }

    $markStmt->close();
    $stmt->close();
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
    mebis_template_backfill_imported_outputs($conn);

    $result = $conn->query("
        SELECT id, output_token, filename, municipality_name, row_count, source_file, created_by,
               imported_at, imported_batch_id, imported_by, created_at
        FROM mebis_lgu_template_outputs
        ORDER BY id DESC
    ");

    if (!$result) {
        return [];
    }

    $entries = [];
    $seenFilenames = [];
    while ($row = $result->fetch_assoc()) {
        $filename = (string) ($row['filename'] ?? '');
        if ($filename === '' || !is_file(mebis_template_outputs_dir() . '/' . $filename)) {
            continue;
        }
        if (isset($seenFilenames[$filename])) {
            continue;
        }
        $seenFilenames[$filename] = true;

        $entries[] = [
            'id' => (int) ($row['id'] ?? 0),
            'token' => (string) ($row['output_token'] ?? ''),
            'filename' => $filename,
            'municipality_name' => (string) ($row['municipality_name'] ?? ''),
            'rows' => (int) ($row['row_count'] ?? 0),
            'source_file' => (string) ($row['source_file'] ?? ''),
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'imported_at' => (string) ($row['imported_at'] ?? ''),
            'imported_batch_id' => (string) ($row['imported_batch_id'] ?? ''),
            'imported_by' => isset($row['imported_by']) ? (int) $row['imported_by'] : null,
            'is_imported' => trim((string) ($row['imported_at'] ?? '')) !== '',
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
        SELECT id, output_token, filename, municipality_name, row_count, source_file, created_by,
               imported_at, imported_batch_id, imported_by, created_at
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
        'imported_at' => (string) ($row['imported_at'] ?? ''),
        'imported_batch_id' => (string) ($row['imported_batch_id'] ?? ''),
        'imported_by' => isset($row['imported_by']) ? (int) ($row['imported_by']) : null,
        'is_imported' => trim((string) ($row['imported_at'] ?? '')) !== '',
        'created_at' => (string) ($row['created_at'] ?? ''),
    ];
}

function mebis_template_mark_output_imported(mysqli $conn, string $token, string $batchId, ?int $importedBy): void
{
    mebis_template_history_ensure_schema($conn);

    $stmt = $conn->prepare("
        UPDATE mebis_lgu_template_outputs
        SET imported_at = COALESCE(imported_at, NOW()),
            imported_batch_id = COALESCE(imported_batch_id, ?),
            imported_by = COALESCE(imported_by, ?)
        WHERE output_token = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('sis', $batchId, $importedBy, $token);
    $stmt->execute();
    $stmt->close();
}
