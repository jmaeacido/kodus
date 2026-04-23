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
        'lawa_potential_area_per_cum' => [
            'label' => 'LAWA Potential Area per cum',
            'value_type' => 'number',
            'unit' => 'sqm/cum',
            'description' => 'Multiplier used to convert computed LAWA water capacity into potential agricultural land coverage.',
            'defaults' => [
                2025 => 20.0,
                2026 => 20.0,
            ],
        ],
        'binhi_yield_factor_vegetable' => [
            'label' => 'BINHI Yield Factor: Vegetable',
            'value_type' => 'number',
            'unit' => 'kg/unit',
            'description' => 'Potential produce multiplier applied to the actual number of planted/introduced vegetable BINHI inputs.',
            'defaults' => [
                2025 => 0.30,
                2026 => 0.30,
            ],
        ],
        'binhi_yield_factor_crops' => [
            'label' => 'BINHI Yield Factor: Crops (Banana, Corn, Rice)',
            'value_type' => 'number',
            'unit' => 'kg/unit',
            'description' => 'Potential produce multiplier applied to the actual number of planted/introduced BINHI crop inputs.',
            'defaults' => [
                2025 => 10.0,
                2026 => 10.0,
            ],
        ],
        'binhi_yield_factor_disaster_resilient_crops' => [
            'label' => 'BINHI Yield Factor: Disaster Resilient Crops',
            'value_type' => 'number',
            'unit' => 'kg/unit',
            'description' => 'Potential produce multiplier applied to the actual number of planted/introduced disaster resilient crop BINHI inputs.',
            'defaults' => [
                2025 => 0.10,
                2026 => 0.10,
            ],
        ],
        'binhi_yield_factor_fruit_bearing_trees' => [
            'label' => 'BINHI Yield Factor: Fruit-Bearing Trees',
            'value_type' => 'number',
            'unit' => 'kg/unit',
            'description' => 'Potential produce multiplier applied to the actual number of planted/introduced fruit-bearing tree BINHI inputs.',
            'defaults' => [
                2025 => 10.0,
                2026 => 10.0,
            ],
        ],
        'binhi_yield_factor_tilapia' => [
            'label' => 'BINHI Yield Factor: Tilapia',
            'value_type' => 'number',
            'unit' => 'kg/unit',
            'description' => 'Potential produce multiplier applied to the actual number of introduced tilapia BINHI inputs.',
            'defaults' => [
                2025 => 0.25,
                2026 => 0.25,
            ],
        ],
        'binhi_individual_feed_requirement_vegetable' => [
            'label' => 'BINHI Feed Requirement: Vegetable',
            'value_type' => 'number',
            'unit' => 'kg/person',
            'description' => 'Kilograms of vegetable produce used to estimate the number of individuals that can be fed.',
            'defaults' => [
                2025 => 60.0,
                2026 => 60.0,
            ],
        ],
        'binhi_individual_feed_requirement_crops' => [
            'label' => 'BINHI Feed Requirement: Crops (Banana, Corn, Rice)',
            'value_type' => 'number',
            'unit' => 'kg/person',
            'description' => 'Kilograms of crop produce used to estimate the number of individuals that can be fed.',
            'defaults' => [
                2025 => 50.0,
                2026 => 50.0,
            ],
        ],
        'binhi_individual_feed_requirement_disaster_resilient_crops' => [
            'label' => 'BINHI Feed Requirement: Disaster Resilient Crops',
            'value_type' => 'number',
            'unit' => 'kg/person',
            'description' => 'Kilograms of disaster resilient crop produce used to estimate the number of individuals that can be fed.',
            'defaults' => [
                2025 => 35.0,
                2026 => 35.0,
            ],
        ],
        'binhi_individual_feed_requirement_fruit_bearing_trees' => [
            'label' => 'BINHI Feed Requirement: Fruit-Bearing Trees',
            'value_type' => 'number',
            'unit' => 'kg/person',
            'description' => 'Kilograms of fruit produce used to estimate the number of individuals that can be fed.',
            'defaults' => [
                2025 => 50.0,
                2026 => 50.0,
            ],
        ],
        'binhi_individual_feed_requirement_tilapia' => [
            'label' => 'BINHI Feed Requirement: Tilapia',
            'value_type' => 'number',
            'unit' => 'kg/person',
            'description' => 'Kilograms of tilapia produce used to estimate the number of individuals that can be fed.',
            'defaults' => [
                2025 => 10.0,
                2026 => 10.0,
            ],
        ],
        'binhi_family_size' => [
            'label' => 'BINHI Family Size',
            'value_type' => 'number',
            'unit' => 'members/family',
            'description' => 'Estimated family size used to convert individuals fed into families fed.',
            'defaults' => [
                2025 => 5.0,
                2026 => 5.0,
            ],
        ],
        'lawa_factor_rehabilitation_water_system_level_1_manual_pump' => [
            'label' => 'LAWA Factor: Rehab Water System Level I (Manual Pump)',
            'value_type' => 'number',
            'unit' => 'cum/sqm',
            'description' => 'Default water-capacity coefficient applied to land area for Rehabilitation of Water System Level I (Manual Pump).',
            'defaults' => [
                2025 => 0.12,
                2026 => 0.12,
            ],
        ],
        'lawa_factor_rehabilitation_water_system_level_2_pipe_laying' => [
            'label' => 'LAWA Factor: Rehab Water System Level II (Pipe Laying)',
            'value_type' => 'number',
            'unit' => 'cum/sqm',
            'description' => 'Default water-capacity coefficient applied to land area for Rehabilitation of Water System Level II (Pipe Laying).',
            'defaults' => [
                2025 => 0.16,
                2026 => 0.16,
            ],
        ],
        'lawa_factor_construction_small_farm_reservoir' => [
            'label' => 'LAWA Factor: Construction of Small Farm Reservoir',
            'value_type' => 'number',
            'unit' => 'cum/sqm',
            'description' => 'Default water-capacity coefficient applied to land area for Construction of Small Farm Reservoir.',
            'defaults' => [
                2025 => 1.50,
                2026 => 1.50,
            ],
        ],
        'lawa_factor_rehabilitation_water_system' => [
            'label' => 'LAWA Factor: Rehabilitation of Water System',
            'value_type' => 'number',
            'unit' => 'cum/sqm',
            'description' => 'Default water-capacity coefficient applied to land area for Rehabilitation of Water System.',
            'defaults' => [
                2025 => 0.16,
                2026 => 0.16,
            ],
        ],
        'lawa_factor_diversification_water_supply' => [
            'label' => 'LAWA Factor: Diversification of Water Supply',
            'value_type' => 'number',
            'unit' => 'cum/sqm',
            'description' => 'Default water-capacity coefficient applied to land area for Diversification of Water Supply. Review this value if field guidance changes.',
            'defaults' => [
                2025 => 1.00,
                2026 => 1.00,
            ],
        ],
        'lawa_factor_rehabilitation_fishpond' => [
            'label' => 'LAWA Factor: Rehabilitation of Fishpond',
            'value_type' => 'number',
            'unit' => 'cum/sqm',
            'description' => 'Default water-capacity coefficient applied to land area for Rehabilitation of Fishpond.',
            'defaults' => [
                2025 => 1.50,
                2026 => 1.50,
            ],
        ],
        'lawa_factor_installation_shallow_tube_wells' => [
            'label' => 'LAWA Factor: Installation of Shallow Tube Wells',
            'value_type' => 'number',
            'unit' => 'cum/sqm',
            'description' => 'Default water-capacity coefficient applied to land area for Installation of Shallow Tube Wells (STWs).',
            'defaults' => [
                2025 => 7.20,
                2026 => 7.20,
            ],
        ],
        'lawa_factor_construction_water_reservoir' => [
            'label' => 'LAWA Factor: Construction of Water Reservoir',
            'value_type' => 'number',
            'unit' => 'cum/sqm',
            'description' => 'Default water-capacity coefficient applied to land area for Construction of Water Reservoir.',
            'defaults' => [
                2025 => 0.6667,
                2026 => 0.6667,
            ],
        ],
        'lawa_factor_rehabilitation_small_farm_reservoir' => [
            'label' => 'LAWA Factor: Rehabilitation of Small Farm Reservoir',
            'value_type' => 'number',
            'unit' => 'cum/sqm',
            'description' => 'Default water-capacity coefficient applied to land area for Rehabilitation of Small Farm Reservoir.',
            'defaults' => [
                2025 => 1.50,
                2026 => 1.50,
            ],
        ],
        'lawa_factor_installation_pitcher_pump' => [
            'label' => 'LAWA Factor: Installation of Pitcher Pump',
            'value_type' => 'number',
            'unit' => 'cum/sqm',
            'description' => 'Default water-capacity coefficient applied to land area for Installation of Pitcher Pump (Shallow Well).',
            'defaults' => [
                2025 => 1.00,
                2026 => 1.00,
            ],
        ],
        'lawa_factor_installation_jetmatic_pump' => [
            'label' => 'LAWA Factor: Installation of Jetmatic Pump',
            'value_type' => 'number',
            'unit' => 'cum/sqm',
            'description' => 'Default water-capacity coefficient applied to land area for Installation of Jetmatic Pump (Deep Well).',
            'defaults' => [
                2025 => 1.00,
                2026 => 1.00,
            ],
        ],
        'lawa_factor_rehabilitation_water_supply' => [
            'label' => 'LAWA Factor: Rehabilitation of Water Supply',
            'value_type' => 'number',
            'unit' => 'cum/sqm',
            'description' => 'Default water-capacity coefficient applied to land area for Rehabilitation of Water Supply.',
            'defaults' => [
                2025 => 1.00,
                2026 => 1.00,
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
    $row = db_stmt_fetch_one_assoc($stmt);
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
    $row = db_stmt_fetch_one_assoc($stmt);
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
    $row = db_stmt_fetch_one_assoc($stmt);
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
    $hasConflict = (bool) db_stmt_fetch_one_assoc($conflictStmt);
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
    $success = $stmt->execute() && $stmt->affected_rows > 0;
    $stmt->close();

    return $success;
}

function project_variable_lawa_capacity_type_map(): array
{
    return [
        'Rehabilitation of Water System Level I (Manual Pump)' => 'lawa_factor_rehabilitation_water_system_level_1_manual_pump',
        'Rehabilitation of Water System Level II (Pipe Laying)' => 'lawa_factor_rehabilitation_water_system_level_2_pipe_laying',
        'Construction of Small Farm Reservoir' => 'lawa_factor_construction_small_farm_reservoir',
        'Rehabilitation of Water System' => 'lawa_factor_rehabilitation_water_system',
        'Diversification of Water Supply' => 'lawa_factor_diversification_water_supply',
        'Rehabilitation of Fishpond' => 'lawa_factor_rehabilitation_fishpond',
        'Installation of Shallow Tube Wells (STWs)' => 'lawa_factor_installation_shallow_tube_wells',
        'Construction of Water Reservoir' => 'lawa_factor_construction_water_reservoir',
        'Rehabilitation of Small Farm Reservoir' => 'lawa_factor_rehabilitation_small_farm_reservoir',
        'Installation of Pitcher Pump (Shallow Well)' => 'lawa_factor_installation_pitcher_pump',
        'Installation of Jetmatic Pump (Deep Well)' => 'lawa_factor_installation_jetmatic_pump',
        'Rehabilitation of Water Supply' => 'lawa_factor_rehabilitation_water_supply',
    ];
}

function project_variable_binhi_yield_type_map(): array
{
    return [
        'Vegetable' => 'binhi_yield_factor_vegetable',
        'Crops (Banana, Corn, Rice)' => 'binhi_yield_factor_crops',
        'Disaster Resilient Crops (Taro, Sweet Potato)' => 'binhi_yield_factor_disaster_resilient_crops',
        'Fruit-Bearing Trees' => 'binhi_yield_factor_fruit_bearing_trees',
        'Tilapia (Fish pond)' => 'binhi_yield_factor_tilapia',
    ];
}

function project_variable_binhi_feed_requirement_type_map(): array
{
    return [
        'Vegetable' => 'binhi_individual_feed_requirement_vegetable',
        'Crops (Banana, Corn, Rice)' => 'binhi_individual_feed_requirement_crops',
        'Disaster Resilient Crops (Taro, Sweet Potato)' => 'binhi_individual_feed_requirement_disaster_resilient_crops',
        'Fruit-Bearing Trees' => 'binhi_individual_feed_requirement_fruit_bearing_trees',
        'Tilapia (Fish pond)' => 'binhi_individual_feed_requirement_tilapia',
    ];
}

function project_variable_get_lawa_capacity_factor(mysqli $conn, int $year, string $projectType): ?float
{
    static $cache = [];

    $projectType = trim($projectType);
    if ($projectType === '') {
        return null;
    }

    $cacheKey = $year . '||' . $projectType;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $typeMap = project_variable_lawa_capacity_type_map();
    $variableKey = $typeMap[$projectType] ?? null;
    if ($variableKey === null) {
        $cache[$cacheKey] = null;
        return null;
    }

    $catalog = project_variable_catalog();
    $default = 0.0;
    if (isset($catalog[$variableKey]['defaults'][$year])) {
        $default = (float) $catalog[$variableKey]['defaults'][$year];
    } elseif (!empty($catalog[$variableKey]['defaults'])) {
        $default = (float) reset($catalog[$variableKey]['defaults']);
    }

    $cache[$cacheKey] = project_variable_get_number($conn, $variableKey, $year, $default);

    return $cache[$cacheKey];
}

function project_variable_get_lawa_potential_area_multiplier(mysqli $conn, int $year): float
{
    static $cache = [];

    if (array_key_exists($year, $cache)) {
        return $cache[$year];
    }

    $catalog = project_variable_catalog();
    $default = 20.0;
    if (isset($catalog['lawa_potential_area_per_cum']['defaults'][$year])) {
        $default = (float) $catalog['lawa_potential_area_per_cum']['defaults'][$year];
    }

    $cache[$year] = project_variable_get_number($conn, 'lawa_potential_area_per_cum', $year, $default);

    return $cache[$year];
}

function project_variable_get_binhi_yield_factor(mysqli $conn, int $year, string $projectType): ?float
{
    static $cache = [];

    $projectType = trim($projectType);
    if ($projectType === '') {
        return null;
    }

    $cacheKey = $year . '||yield||' . $projectType;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $typeMap = project_variable_binhi_yield_type_map();
    $variableKey = $typeMap[$projectType] ?? null;
    if ($variableKey === null) {
        $cache[$cacheKey] = null;
        return null;
    }

    $catalog = project_variable_catalog();
    $default = 0.0;
    if (isset($catalog[$variableKey]['defaults'][$year])) {
        $default = (float) $catalog[$variableKey]['defaults'][$year];
    } elseif (!empty($catalog[$variableKey]['defaults'])) {
        $default = (float) reset($catalog[$variableKey]['defaults']);
    }

    $cache[$cacheKey] = project_variable_get_number($conn, $variableKey, $year, $default);

    return $cache[$cacheKey];
}

function project_variable_get_binhi_individual_feed_requirement(mysqli $conn, int $year, string $projectType): ?float
{
    static $cache = [];

    $projectType = trim($projectType);
    if ($projectType === '') {
        return null;
    }

    $cacheKey = $year . '||feed||' . $projectType;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $typeMap = project_variable_binhi_feed_requirement_type_map();
    $variableKey = $typeMap[$projectType] ?? null;
    if ($variableKey === null) {
        $cache[$cacheKey] = null;
        return null;
    }

    $catalog = project_variable_catalog();
    $default = 0.0;
    if (isset($catalog[$variableKey]['defaults'][$year])) {
        $default = (float) $catalog[$variableKey]['defaults'][$year];
    } elseif (!empty($catalog[$variableKey]['defaults'])) {
        $default = (float) reset($catalog[$variableKey]['defaults']);
    }

    $cache[$cacheKey] = project_variable_get_number($conn, $variableKey, $year, $default);

    return $cache[$cacheKey];
}

function project_variable_get_binhi_family_size(mysqli $conn, int $year): float
{
    static $cache = [];

    if (array_key_exists($year, $cache)) {
        return $cache[$year];
    }

    $catalog = project_variable_catalog();
    $default = 5.0;
    if (isset($catalog['binhi_family_size']['defaults'][$year])) {
        $default = (float) $catalog['binhi_family_size']['defaults'][$year];
    }

    $cache[$year] = project_variable_get_number($conn, 'binhi_family_size', $year, $default);

    return $cache[$year];
}
