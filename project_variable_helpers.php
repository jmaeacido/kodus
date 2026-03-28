<?php

function project_variable_catalog(): array
{
    return [
        'daily_wage_rate' => [
            'label' => 'Daily Wage Rate',
            'value_type' => 'number',
            'unit' => 'PHP/day',
            'description' => 'Daily wage rate used to compute payout totals.',
            'defaults' => [
                2025 => 385.00,
                2026 => 435.00,
            ],
        ],
        'working_days' => [
            'label' => 'Working Days',
            'value_type' => 'number',
            'unit' => 'days',
            'description' => 'Number of working days multiplied by the daily wage rate for payout totals.',
            'defaults' => [
                2025 => 20,
                2026 => 20,
            ],
        ],
    ];
}

function project_variable_ensure_schema(mysqli $conn): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS project_variable_config (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            fiscal_year INT NOT NULL,
            variable_key VARCHAR(100) NOT NULL,
            variable_label VARCHAR(150) NOT NULL,
            value_type VARCHAR(20) NOT NULL DEFAULT 'number',
            value_number DECIMAL(14,4) DEFAULT NULL,
            value_text TEXT DEFAULT NULL,
            unit VARCHAR(50) DEFAULT NULL,
            notes VARCHAR(255) DEFAULT NULL,
            updated_by INT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_variable_year_key (fiscal_year, variable_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $indexResult = $conn->query("
        SELECT COUNT(*) AS total
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'project_variable_config'
          AND INDEX_NAME = 'uq_project_variable_year_key'
    ");

    $hasUniqueIndex = false;
    if ($indexResult instanceof mysqli_result) {
        $hasUniqueIndex = (int) (($indexResult->fetch_assoc()['total'] ?? 0)) > 0;
        $indexResult->free();
    }

    if (!$hasUniqueIndex) {
        @$conn->query("ALTER TABLE project_variable_config ADD UNIQUE KEY uq_project_variable_year_key (fiscal_year, variable_key)");
    }

    $catalog = project_variable_catalog();
    $stmt = $conn->prepare("
        INSERT INTO project_variable_config (
            fiscal_year, variable_key, variable_label, value_type, value_number, value_text, unit, notes, updated_by
        )
        VALUES (?, ?, ?, ?, ?, NULL, ?, ?, NULL)
        ON DUPLICATE KEY UPDATE id = id
    ");

    if ($stmt) {
        foreach ($catalog as $key => $meta) {
            foreach ($meta['defaults'] as $year => $value) {
                $label = $meta['label'];
                $valueType = $meta['value_type'];
                $valueNumber = (float) $value;
                $unit = $meta['unit'];
                $notes = $meta['description'];
                $stmt->bind_param('isssdss', $year, $key, $label, $valueType, $valueNumber, $unit, $notes);
                $stmt->execute();
            }
        }
        $stmt->close();
    }

    $initialized = true;
}

function project_variable_list_all(mysqli $conn): array
{
    project_variable_ensure_schema($conn);

    $rows = [];
    $result = $conn->query("
        SELECT id, fiscal_year, variable_key, variable_label, value_type, value_number, value_text, unit, notes, updated_by, created_at, updated_at
        FROM project_variable_config
        ORDER BY fiscal_year DESC, variable_label ASC, variable_key ASC
    ");

    if (!$result instanceof mysqli_result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int) ($row['id'] ?? 0),
            'fiscal_year' => (int) ($row['fiscal_year'] ?? 0),
            'variable_key' => (string) ($row['variable_key'] ?? ''),
            'variable_label' => (string) ($row['variable_label'] ?? ''),
            'value_type' => (string) ($row['value_type'] ?? 'number'),
            'value_number' => isset($row['value_number']) ? (float) $row['value_number'] : null,
            'value_text' => (string) ($row['value_text'] ?? ''),
            'unit' => (string) ($row['unit'] ?? ''),
            'notes' => (string) ($row['notes'] ?? ''),
            'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    $result->free();

    return $rows;
}

function project_variable_get(mysqli $conn, string $key, int $year): ?array
{
    project_variable_ensure_schema($conn);

    $stmt = $conn->prepare("
        SELECT id, fiscal_year, variable_key, variable_label, value_type, value_number, value_text, unit, notes, updated_by, created_at, updated_at
        FROM project_variable_config
        WHERE fiscal_year = ? AND variable_key = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('is', $year, $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'fiscal_year' => (int) ($row['fiscal_year'] ?? $year),
        'variable_key' => (string) ($row['variable_key'] ?? ''),
        'variable_label' => (string) ($row['variable_label'] ?? ''),
        'value_type' => (string) ($row['value_type'] ?? 'number'),
        'value_number' => isset($row['value_number']) ? (float) $row['value_number'] : null,
        'value_text' => (string) ($row['value_text'] ?? ''),
        'unit' => (string) ($row['unit'] ?? ''),
        'notes' => (string) ($row['notes'] ?? ''),
        'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function project_variable_get_by_id(mysqli $conn, int $id): ?array
{
    project_variable_ensure_schema($conn);

    $stmt = $conn->prepare("
        SELECT id, fiscal_year, variable_key, variable_label, value_type, value_number, value_text, unit, notes, updated_by, created_at, updated_at
        FROM project_variable_config
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'fiscal_year' => (int) ($row['fiscal_year'] ?? 0),
        'variable_key' => (string) ($row['variable_key'] ?? ''),
        'variable_label' => (string) ($row['variable_label'] ?? ''),
        'value_type' => (string) ($row['value_type'] ?? 'number'),
        'value_number' => isset($row['value_number']) ? (float) $row['value_number'] : null,
        'value_text' => (string) ($row['value_text'] ?? ''),
        'unit' => (string) ($row['unit'] ?? ''),
        'notes' => (string) ($row['notes'] ?? ''),
        'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function project_variable_get_number(mysqli $conn, string $key, int $year, float $default = 0.0): float
{
    $row = project_variable_get($conn, $key, $year);
    if (!$row || $row['value_type'] !== 'number' || $row['value_number'] === null) {
        return $default;
    }

    return (float) $row['value_number'];
}

function project_variable_find_existing_id(mysqli $conn, int $year, string $variableKey): ?int
{
    project_variable_ensure_schema($conn);

    $stmt = $conn->prepare("
        SELECT id
        FROM project_variable_config
        WHERE fiscal_year = ? AND variable_key = ?
        ORDER BY id ASC
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('is', $year, $variableKey);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    return $row ? (int) $row['id'] : null;
}


function project_variable_upsert(
    mysqli $conn,
    int $year,
    string $variableKey,
    string $variableLabel,
    string $valueType,
    ?float $valueNumber,
    ?string $valueText,
    string $unit,
    string $notes,
    ?int $updatedBy
): bool {
    project_variable_ensure_schema($conn);

    $stmt = $conn->prepare("
        INSERT INTO project_variable_config (
            fiscal_year, variable_key, variable_label, value_type, value_number, value_text, unit, notes, updated_by
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            variable_label = VALUES(variable_label),
            value_type = VALUES(value_type),
            value_number = VALUES(value_number),
            value_text = VALUES(value_text),
            unit = VALUES(unit),
            notes = VALUES(notes),
            updated_by = VALUES(updated_by)
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        'isssdsssi',
        $year,
        $variableKey,
        $variableLabel,
        $valueType,
        $valueNumber,
        $valueText,
        $unit,
        $notes,
        $updatedBy
    );
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function project_variable_save(
    mysqli $conn,
    int $year,
    string $variableKey,
    string $variableLabel,
    string $valueType,
    ?float $valueNumber,
    ?string $valueText,
    string $unit,
    string $notes,
    ?int $updatedBy,
    ?int $recordId = null
): bool {
    project_variable_ensure_schema($conn);

    if ($recordId !== null && $recordId > 0) {
        $existingRecord = project_variable_get_by_id($conn, $recordId);
        if (!$existingRecord) {
            $matchedId = project_variable_find_existing_id($conn, $year, $variableKey);
            if ($matchedId !== null) {
                $recordId = $matchedId;
            }
        }
    }

    if (($recordId === null || $recordId <= 0)) {
        $matchedId = project_variable_find_existing_id($conn, $year, $variableKey);
        if ($matchedId !== null) {
            $recordId = $matchedId;
        }
    }

    if ($recordId === null || $recordId <= 0) {
        return project_variable_upsert(
            $conn,
            $year,
            $variableKey,
            $variableLabel,
            $valueType,
            $valueNumber,
            $valueText,
            $unit,
            $notes,
            $updatedBy
        );
    }

    $conflictStmt = $conn->prepare("
        SELECT id
        FROM project_variable_config
        WHERE fiscal_year = ? AND variable_key = ?
          AND id <> ?
        LIMIT 1
    ");

    if (!$conflictStmt) {
        return false;
    }

    $conflictStmt->bind_param('isi', $year, $variableKey, $recordId);
    $conflictStmt->execute();
    $conflictResult = $conflictStmt->get_result();
    $hasConflict = $conflictResult instanceof mysqli_result && $conflictResult->fetch_assoc();
    if ($conflictResult instanceof mysqli_result) {
        $conflictResult->free();
    }
    $conflictStmt->close();

    if ($hasConflict) {
        return false;
    }

    $stmt = $conn->prepare("
        UPDATE project_variable_config
        SET fiscal_year = ?,
            variable_key = ?,
            variable_label = ?,
            value_type = ?,
            value_number = ?,
            value_text = ?,
            unit = ?,
            notes = ?,
            updated_by = ?
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        'isssdsssii',
        $year,
        $variableKey,
        $variableLabel,
        $valueType,
        $valueNumber,
        $valueText,
        $unit,
        $notes,
        $updatedBy,
        $recordId
    );
    $success = $stmt->execute() && $stmt->affected_rows >= 0;
    $stmt->close();

    return $success;
}

function project_variable_delete(mysqli $conn, int $id): bool
{
    project_variable_ensure_schema($conn);

    $stmt = $conn->prepare("
        DELETE FROM project_variable_config
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $id);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}
