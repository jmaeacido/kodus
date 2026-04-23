<?php

function ensureProjectLawaBinhiTargets(mysqli $conn): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS project_lawa_binhi_targets (
            id INT NOT NULL AUTO_INCREMENT,
            fiscal_year INT NOT NULL,
            province VARCHAR(255) NOT NULL,
            municipality VARCHAR(255) NOT NULL,
            barangay VARCHAR(255) NOT NULL,
            lawa_target INT NOT NULL DEFAULT 0,
            binhi_target INT NOT NULL DEFAULT 0,
            binhi_vegetable_target INT NOT NULL DEFAULT 0,
            binhi_crops_target INT NOT NULL DEFAULT 0,
            binhi_disaster_resilient_crops_target INT NOT NULL DEFAULT 0,
            binhi_fruit_bearing_trees_target INT NOT NULL DEFAULT 0,
            binhi_tilapia_target INT NOT NULL DEFAULT 0,
            capbuild_target INT NOT NULL DEFAULT 2,
            community_action_plan_target INT NOT NULL DEFAULT 1,
            target_partner_beneficiaries INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_project_target_location (fiscal_year, province, municipality, barangay),
            KEY idx_project_target_year_location (fiscal_year, province, municipality)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    projectTargetsEnsureColumn($conn, 'lawa_target', "ALTER TABLE project_lawa_binhi_targets ADD COLUMN lawa_target INT NOT NULL DEFAULT 0 AFTER barangay");
    projectTargetsEnsureColumn($conn, 'binhi_target', "ALTER TABLE project_lawa_binhi_targets ADD COLUMN binhi_target INT NOT NULL DEFAULT 0 AFTER lawa_target");
    projectTargetsEnsureColumn($conn, 'binhi_vegetable_target', "ALTER TABLE project_lawa_binhi_targets ADD COLUMN binhi_vegetable_target INT NOT NULL DEFAULT 0 AFTER binhi_target");
    projectTargetsEnsureColumn($conn, 'binhi_crops_target', "ALTER TABLE project_lawa_binhi_targets ADD COLUMN binhi_crops_target INT NOT NULL DEFAULT 0 AFTER binhi_vegetable_target");
    projectTargetsEnsureColumn($conn, 'binhi_disaster_resilient_crops_target', "ALTER TABLE project_lawa_binhi_targets ADD COLUMN binhi_disaster_resilient_crops_target INT NOT NULL DEFAULT 0 AFTER binhi_crops_target");
    projectTargetsEnsureColumn($conn, 'binhi_fruit_bearing_trees_target', "ALTER TABLE project_lawa_binhi_targets ADD COLUMN binhi_fruit_bearing_trees_target INT NOT NULL DEFAULT 0 AFTER binhi_disaster_resilient_crops_target");
    projectTargetsEnsureColumn($conn, 'binhi_tilapia_target', "ALTER TABLE project_lawa_binhi_targets ADD COLUMN binhi_tilapia_target INT NOT NULL DEFAULT 0 AFTER binhi_fruit_bearing_trees_target");
    projectTargetsEnsureColumn($conn, 'capbuild_target', "ALTER TABLE project_lawa_binhi_targets ADD COLUMN capbuild_target INT NOT NULL DEFAULT 2 AFTER binhi_target");
    projectTargetsEnsureColumn($conn, 'community_action_plan_target', "ALTER TABLE project_lawa_binhi_targets ADD COLUMN community_action_plan_target INT NOT NULL DEFAULT 1 AFTER capbuild_target");
    projectTargetsEnsureIntDefault($conn, 'binhi_vegetable_target', 0);
    projectTargetsEnsureIntDefault($conn, 'binhi_crops_target', 0);
    projectTargetsEnsureIntDefault($conn, 'binhi_disaster_resilient_crops_target', 0);
    projectTargetsEnsureIntDefault($conn, 'binhi_fruit_bearing_trees_target', 0);
    projectTargetsEnsureIntDefault($conn, 'binhi_tilapia_target', 0);
    projectTargetsEnsureIntDefault($conn, 'capbuild_target', 2);
    projectTargetsEnsureIntDefault($conn, 'community_action_plan_target', 1);

    $conn->query("
        CREATE TABLE IF NOT EXISTS project_target_entries (
            id BIGINT NOT NULL AUTO_INCREMENT,
            target_id INT NOT NULL,
            row_id VARCHAR(64) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            purok VARCHAR(255) NOT NULL,
            project_name VARCHAR(255) NOT NULL,
            project_type VARCHAR(255) NOT NULL,
            project_classification VARCHAR(32) NOT NULL,
            fertilizer_enabled TINYINT(1) NOT NULL DEFAULT 0,
            fertilizer_ohn_target DECIMAL(14,2) DEFAULT NULL,
            fertilizer_concoction_target DECIMAL(14,2) DEFAULT NULL,
            fertilizer_vermicompost_target DECIMAL(14,2) DEFAULT NULL,
            binhi_target_quantity INT DEFAULT NULL,
            aquatic_resource VARCHAR(255) DEFAULT NULL,
            aquatic_resource_quantity INT DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_project_target_entry_target_row (target_id, row_id),
            KEY idx_project_target_entry_target_sort (target_id, sort_order),
            CONSTRAINT fk_project_target_entry_target
                FOREIGN KEY (target_id) REFERENCES project_lawa_binhi_targets (id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $initialized = true;
}

function projectTargetsEnsureColumn(mysqli $conn, string $columnName, string $alterSql): void
{
    $safeColumn = $conn->real_escape_string($columnName);
    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'project_lawa_binhi_targets'
          AND COLUMN_NAME = '{$safeColumn}'
    ");

    $exists = false;
    if ($result instanceof mysqli_result) {
        $exists = ((int) ($result->fetch_assoc()['total'] ?? 0)) > 0;
        $result->free();
    }

    if (!$exists) {
        $conn->query($alterSql);
    }
}

function projectTargetsEnsureIntDefault(mysqli $conn, string $columnName, int $expectedDefault): void
{
    $safeColumn = $conn->real_escape_string($columnName);
    $result = $conn->query("
        SELECT COLUMN_DEFAULT
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'project_lawa_binhi_targets'
          AND COLUMN_NAME = '{$safeColumn}'
        LIMIT 1
    ");

    $currentDefault = null;
    if ($result instanceof mysqli_result) {
        $row = $result->fetch_assoc();
        $currentDefault = $row['COLUMN_DEFAULT'] ?? null;
        $result->free();
    }

    if ((string) $currentDefault !== (string) $expectedDefault) {
        $conn->query("
            ALTER TABLE project_lawa_binhi_targets
            MODIFY COLUMN {$safeColumn} INT NOT NULL DEFAULT {$expectedDefault}
        ");
    }
}

function normalizeProjectTargetLocation(string $value): string
{
    $normalized = preg_replace('/\s+/', ' ', trim($value));
    return mb_strtoupper((string) $normalized, 'UTF-8');
}

function normalizeProjectTargetList(array $values, bool $uppercase = true): array
{
    $normalized = array_map(static function ($value) use ($uppercase) {
        $clean = preg_replace('/\s+/', ' ', trim((string) $value));
        if ($clean === '') {
            return '';
        }

        return $uppercase ? mb_strtoupper($clean, 'UTF-8') : $clean;
    }, $values);

    return array_values(array_filter($normalized, static fn($value) => $value !== ''));
}

function parseProjectTargetMultiValueCell(?string $value, bool $uppercase = true): array
{
    // Stored multi-value cells use "||" as the canonical delimiter.
    // Keep commas inside values such as "Crops (Banana, Corn, Rice)" so
    // row-aligned arrays do not shift when records are reloaded.
    $parts = preg_split('/\|\||[\r\n]+/', (string) $value);
    return normalizeProjectTargetList($parts ?: [], $uppercase);
}

function projectTargetsBuildEntryRows(
    array $projectRowIds,
    array $puroks,
    array $projects,
    array $projectTypes,
    array $fertilizerEnabledFlags,
    array $fertilizerOhnTargets,
    array $fertilizerConcoctionTargets,
    array $fertilizerVermicompostTargets,
    array $binhiTargetQuantities,
    array $aquaticResources,
    array $aquaticResourceQuantities
): array {
    $rows = [];

    foreach ($projects as $index => $project) {
        $rows[] = [
            'row_id' => (string) ($projectRowIds[$index] ?? ''),
            'sort_order' => $index,
            'purok' => (string) ($puroks[$index] ?? ''),
            'project_name' => (string) ($project['name'] ?? ''),
            'project_type' => (string) ($projectTypes[$index] ?? ''),
            'project_classification' => (string) ($project['classification'] ?? ''),
            'fertilizer_enabled' => (string) ($fertilizerEnabledFlags[$index] ?? '') === '1' ? 1 : 0,
            'fertilizer_ohn_target' => projectTargetsNullableDecimal($fertilizerOhnTargets[$index] ?? ''),
            'fertilizer_concoction_target' => projectTargetsNullableDecimal($fertilizerConcoctionTargets[$index] ?? ''),
            'fertilizer_vermicompost_target' => projectTargetsNullableDecimal($fertilizerVermicompostTargets[$index] ?? ''),
            'binhi_target_quantity' => projectTargetsNullableInt($binhiTargetQuantities[$index] ?? ''),
            'aquatic_resource' => projectTargetsNullableString($aquaticResources[$index] ?? ''),
            'aquatic_resource_quantity' => projectTargetsNullableInt($aquaticResourceQuantities[$index] ?? ''),
        ];
    }

    return $rows;
}

function projectTargetsReplaceEntries(mysqli $conn, int $targetId, array $rows): void
{
    $deleteStmt = $conn->prepare('DELETE FROM project_target_entries WHERE target_id = ?');
    if ($deleteStmt) {
        $deleteStmt->bind_param('i', $targetId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }

    if ($rows === []) {
        return;
    }

    $insertStmt = $conn->prepare("
        INSERT INTO project_target_entries (
            target_id,
            row_id,
            sort_order,
            purok,
            project_name,
            project_type,
            project_classification,
            fertilizer_enabled,
            fertilizer_ohn_target,
            fertilizer_concoction_target,
            fertilizer_vermicompost_target,
            binhi_target_quantity,
            aquatic_resource,
            aquatic_resource_quantity
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$insertStmt) {
        throw new RuntimeException('Unable to prepare project target entry insert: ' . $conn->error);
    }

    foreach ($rows as $row) {
        $targetIdParam = $targetId;
        $rowId = (string) ($row['row_id'] ?? '');
        $sortOrder = (int) ($row['sort_order'] ?? 0);
        $purok = (string) ($row['purok'] ?? '');
        $projectName = (string) ($row['project_name'] ?? '');
        $projectType = (string) ($row['project_type'] ?? '');
        $projectClassification = (string) ($row['project_classification'] ?? '');
        $fertilizerEnabled = !empty($row['fertilizer_enabled']) ? 1 : 0;
        $fertilizerOhnTarget = array_key_exists('fertilizer_ohn_target', $row) ? $row['fertilizer_ohn_target'] : null;
        $fertilizerConcoctionTarget = array_key_exists('fertilizer_concoction_target', $row) ? $row['fertilizer_concoction_target'] : null;
        $fertilizerVermicompostTarget = array_key_exists('fertilizer_vermicompost_target', $row) ? $row['fertilizer_vermicompost_target'] : null;
        $binhiTargetQuantity = array_key_exists('binhi_target_quantity', $row) ? $row['binhi_target_quantity'] : null;
        $aquaticResource = array_key_exists('aquatic_resource', $row) ? $row['aquatic_resource'] : null;
        $aquaticResourceQuantity = array_key_exists('aquatic_resource_quantity', $row) ? $row['aquatic_resource_quantity'] : null;

        $insertStmt->bind_param(
            'isissssidddisi',
            $targetIdParam,
            $rowId,
            $sortOrder,
            $purok,
            $projectName,
            $projectType,
            $projectClassification,
            $fertilizerEnabled,
            $fertilizerOhnTarget,
            $fertilizerConcoctionTarget,
            $fertilizerVermicompostTarget,
            $binhiTargetQuantity,
            $aquaticResource,
            $aquaticResourceQuantity
        );
        $insertStmt->execute();
    }

    $insertStmt->close();
}

function projectTargetsFetchEntriesByTargetIds(mysqli $conn, array $targetIds): array
{
    $targetIds = array_values(array_filter(array_map('intval', $targetIds), static fn(int $value): bool => $value > 0));
    if ($targetIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
    $types = str_repeat('i', count($targetIds));
    $stmt = $conn->prepare("
        SELECT
            id,
            target_id,
            row_id,
            sort_order,
            purok,
            project_name,
            project_type,
            project_classification,
            fertilizer_enabled,
            fertilizer_ohn_target,
            fertilizer_concoction_target,
            fertilizer_vermicompost_target,
            binhi_target_quantity,
            aquatic_resource,
            aquatic_resource_quantity
        FROM project_target_entries
        WHERE target_id IN ($placeholders)
        ORDER BY target_id ASC, sort_order ASC, id ASC
    ");

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$targetIds);
    $stmt->execute();
    $rows = db_stmt_fetch_all_assoc($stmt);
    $stmt->close();

    $grouped = [];
    foreach ($rows as $row) {
        $grouped[(int) ($row['target_id'] ?? 0)][] = $row;
    }

    return $grouped;
}

function projectTargetsNullableString($value): ?string
{
    $normalized = trim((string) $value);
    return $normalized === '' ? null : $normalized;
}

function projectTargetsNullableInt($value): ?int
{
    $normalized = trim((string) $value);
    if ($normalized === '' || !preg_match('/^-?\d+$/', $normalized)) {
        return null;
    }

    return (int) $normalized;
}

function projectTargetsNullableDecimal($value): ?float
{
    $normalized = trim((string) $value);
    if ($normalized === '' || !is_numeric($normalized)) {
        return null;
    }

    return (float) $normalized;
}
