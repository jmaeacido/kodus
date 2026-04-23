<?php

function ensureProgramActivityMetadataColumn(mysqli $conn, string $columnName, string $definition): void
{
    $safeColumn = $conn->real_escape_string($columnName);
    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'program_activity_metadata'
          AND COLUMN_NAME = '{$safeColumn}'
    ");

    $exists = false;
    if ($result instanceof mysqli_result) {
        $exists = (int) (($result->fetch_assoc()['total'] ?? 0)) > 0;
        $result->free();
    }

    if (!$exists) {
        $conn->query("ALTER TABLE program_activity_metadata ADD COLUMN {$columnName} {$definition}");
    }
}

function ensureProgramActivityMetadataIndex(mysqli $conn, string $indexName, string $indexDefinition): void
{
    $safeIndexName = $conn->real_escape_string($indexName);
    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'program_activity_metadata'
          AND INDEX_NAME = '{$safeIndexName}'
    ");

    $exists = false;
    if ($result instanceof mysqli_result) {
        $exists = (int) (($result->fetch_assoc()['total'] ?? 0)) > 0;
        $result->free();
    }

    if (!$exists) {
        $conn->query("ALTER TABLE program_activity_metadata ADD {$indexDefinition}");
    }
}

function ensureProgramActivityActualProjectIndex(mysqli $conn, string $indexName, string $indexDefinition): void
{
    $safeIndexName = $conn->real_escape_string($indexName);
    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'program_activity_actual_projects'
          AND INDEX_NAME = '{$safeIndexName}'
    ");

    $exists = false;
    if ($result instanceof mysqli_result) {
        $exists = (int) (($result->fetch_assoc()['total'] ?? 0)) > 0;
        $result->free();
    }

    if (!$exists) {
        $conn->query("ALTER TABLE program_activity_actual_projects ADD {$indexDefinition}");
    }
}

function programActivityProjectCodeLocationCode(string $value): string
{
    $sanitized = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $value));
    $code = substr($sanitized, 0, 3);
    return str_pad($code, 3, 'X');
}

function programActivityProvinceProjectCode(string $province): string
{
    static $provinceCodes = [
        'agusan del norte' => 'ADN',
        'dinagat islands' => 'PDI',
    ];

    $normalizedProvince = mb_strtolower(trim($province));
    if (isset($provinceCodes[$normalizedProvince])) {
        return $provinceCodes[$normalizedProvince];
    }

    return programActivityProjectCodeLocationCode($province);
}

function programActivityBuildProjectCode(
    int $provinceOrder,
    string $province,
    string $municipality,
    string $barangay,
    int $barangayOrder
): string {
    return sprintf(
        'PS-%d-%s-%s-%s-%04d',
        max(1, $provinceOrder),
        programActivityProvinceProjectCode($province),
        programActivityProjectCodeLocationCode($municipality),
        programActivityProjectCodeLocationCode($barangay),
        max(1, $barangayOrder)
    );
}

function programActivityBackfillProjectCodes(mysqli $conn, bool $force = false): void
{
    static $backfilled = false;

    if ($backfilled && !$force) {
        return;
    }

    $result = $conn->query("
        SELECT
            ap.id,
            ap.project_code,
            ap.sort_order,
            ap.created_at AS actual_created_at,
            metadata.fiscal_year,
            metadata.province,
            metadata.municipality,
            metadata.barangay,
            metadata.created_at AS metadata_created_at
        FROM program_activity_actual_projects ap
        INNER JOIN program_activity_metadata metadata
            ON metadata.id = ap.program_activity_id
        ORDER BY
            LOWER(TRIM(metadata.province)) ASC,
            metadata.fiscal_year ASC,
            metadata.created_at ASC,
            metadata.id ASC,
            ap.sort_order ASC,
            ap.created_at ASC,
            ap.id ASC
    ");

    if (!($result instanceof mysqli_result)) {
        $backfilled = true;
        return;
    }

    $provinceCounters = [];
    $barangayCounters = [];
    $updates = [];

    while ($row = $result->fetch_assoc()) {
        $provinceKey = mb_strtolower(trim((string) ($row['province'] ?? '')));
        $municipalityKey = mb_strtolower(trim((string) ($row['municipality'] ?? '')));
        $barangayKey = mb_strtolower(trim((string) ($row['barangay'] ?? '')));
        $provinceCounters[$provinceKey] = (int) ($provinceCounters[$provinceKey] ?? 0) + 1;
        $compoundBarangayKey = implode('|', [$provinceKey, $municipalityKey, $barangayKey]);
        $barangayCounters[$compoundBarangayKey] = (int) ($barangayCounters[$compoundBarangayKey] ?? 0) + 1;

        $projectCode = programActivityBuildProjectCode(
            $provinceCounters[$provinceKey],
            (string) ($row['province'] ?? ''),
            (string) ($row['municipality'] ?? ''),
            (string) ($row['barangay'] ?? ''),
            $barangayCounters[$compoundBarangayKey]
        );

        $currentProjectCode = trim((string) ($row['project_code'] ?? ''));
        if ($currentProjectCode === $projectCode) {
            continue;
        }

        $updates[] = [
            'id' => (int) ($row['id'] ?? 0),
            'project_code' => $projectCode,
        ];
    }
    $result->free();

    if ($updates !== []) {
        $updateStmt = $conn->prepare("
            UPDATE program_activity_actual_projects
            SET project_code = ?
            WHERE id = ?
        ");

        if (!$updateStmt) {
            throw new RuntimeException('Unable to prepare project code backfill: ' . $conn->error);
        }

        foreach ($updates as $update) {
            $projectCode = (string) $update['project_code'];
            $id = (int) $update['id'];
            $updateStmt->bind_param('si', $projectCode, $id);
            $updateStmt->execute();
        }

        $updateStmt->close();
    }

    ensureProgramActivityActualProjectIndex(
        $conn,
        'uq_program_activity_project_code',
        'UNIQUE KEY uq_program_activity_project_code (project_code)'
    );

    $backfilled = true;
}

function ensureProgramActivityMetadata(mysqli $conn, ?int $defaultFiscalYear = null): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS program_activity_metadata (
            id INT NOT NULL AUTO_INCREMENT,
            fiscal_year INT NOT NULL DEFAULT 0,
            province VARCHAR(255) NOT NULL,
            municipality VARCHAR(255) NOT NULL,
            barangay VARCHAR(255) NOT NULL,
            plgu_forum DATE DEFAULT NULL,
            mlgu_forum DATE DEFAULT NULL,
            blgu_forum DATE DEFAULT NULL,
            plgu_forum_from DATE DEFAULT NULL,
            plgu_forum_to DATE DEFAULT NULL,
            mlgu_forum_from DATE DEFAULT NULL,
            mlgu_forum_to DATE DEFAULT NULL,
            blgu_forum_from DATE DEFAULT NULL,
            blgu_forum_to DATE DEFAULT NULL,
            site_validation TEXT DEFAULT NULL,
            stage1_start_date DATE DEFAULT NULL,
            stage1_end_date DATE DEFAULT NULL,
            stage2_start_date DATE DEFAULT NULL,
            stage2_end_date DATE DEFAULT NULL,
            stage3_start_date DATE DEFAULT NULL,
            stage3_end_date DATE DEFAULT NULL,
            drmd_monitoring_from DATE DEFAULT NULL,
            drmd_monitoring_to DATE DEFAULT NULL,
            drmd_monitoring_participants TEXT DEFAULT NULL,
            joint_post_monitoring_from DATE DEFAULT NULL,
            joint_post_monitoring_to DATE DEFAULT NULL,
            joint_post_monitoring_participants TEXT DEFAULT NULL,
            payout_schedule_from DATE DEFAULT NULL,
            payout_schedule_to DATE DEFAULT NULL,
            actual_lawa_accomplishment INT DEFAULT NULL,
            actual_binhi_accomplishment INT DEFAULT NULL,
            actual_capbuild_accomplishment INT DEFAULT NULL,
            actual_community_action_plan_accomplishment INT DEFAULT NULL,
            fund_obligation_partner_beneficiaries INT DEFAULT NULL,
            fund_disbursement_served_partner_beneficiaries INT DEFAULT NULL,
            liquidation_date DATE DEFAULT NULL,
            last_day_project_implementation DATE DEFAULT NULL,
            check_issuance_date DATE DEFAULT NULL,
            work_accomplishment_report_status VARCHAR(255) DEFAULT NULL,
            performance_rating_remarks TEXT DEFAULT NULL,
            special_disbursing_officer VARCHAR(255) DEFAULT NULL,
            binhi_sites_established_target INT DEFAULT NULL,
            binhi_sites_established_actual INT DEFAULT NULL,
            binhi_facilities_added_target INT DEFAULT NULL,
            binhi_facilities_added_actual INT DEFAULT NULL,
            fertilizer_ohn_target DECIMAL(14,2) DEFAULT NULL,
            fertilizer_ohn_actual DECIMAL(14,2) DEFAULT NULL,
            fertilizer_concoction_target DECIMAL(14,2) DEFAULT NULL,
            fertilizer_concoction_actual DECIMAL(14,2) DEFAULT NULL,
            fertilizer_vermicompost_target DECIMAL(14,2) DEFAULT NULL,
            fertilizer_vermicompost_actual DECIMAL(14,2) DEFAULT NULL,
            area_land_utilized_target DECIMAL(14,2) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_program_activity_location_year (fiscal_year, province, municipality, barangay)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    ensureProgramActivityMetadataColumn($conn, 'fiscal_year', 'INT NOT NULL DEFAULT 0 AFTER id');

    $legacyUniqueIndexResult = $conn->query("
        SELECT COUNT(*) AS total
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'program_activity_metadata'
          AND INDEX_NAME = 'unique_program_activity_location'
    ");
    $legacyUniqueIndexExists = false;
    if ($legacyUniqueIndexResult instanceof mysqli_result) {
        $legacyUniqueIndexExists = (int) (($legacyUniqueIndexResult->fetch_assoc()['total'] ?? 0)) > 0;
        $legacyUniqueIndexResult->free();
    }

    if ($legacyUniqueIndexExists) {
        $conn->query('ALTER TABLE program_activity_metadata DROP INDEX unique_program_activity_location');
    }

    ensureProgramActivityMetadataIndex(
        $conn,
        'unique_program_activity_location_year',
        'UNIQUE KEY unique_program_activity_location_year (fiscal_year, province, municipality, barangay)'
    );

    ensureProgramActivityMetadataColumn($conn, 'plgu_forum_from', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'plgu_forum_to', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'mlgu_forum_from', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'mlgu_forum_to', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'blgu_forum_from', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'blgu_forum_to', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'site_validation', 'TEXT DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'stage1_start_date', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'stage1_end_date', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'stage2_start_date', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'stage2_end_date', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'stage3_start_date', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'stage3_end_date', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'drmd_monitoring_from', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'drmd_monitoring_to', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'drmd_monitoring_participants', 'TEXT DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'joint_post_monitoring_from', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'joint_post_monitoring_to', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'joint_post_monitoring_participants', 'TEXT DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'payout_schedule_from', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'payout_schedule_to', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'actual_lawa_accomplishment', 'INT DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'actual_binhi_accomplishment', 'INT DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'actual_capbuild_accomplishment', 'INT DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'actual_community_action_plan_accomplishment', 'INT DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'fund_obligation_partner_beneficiaries', 'INT DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'fund_disbursement_served_partner_beneficiaries', 'INT DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'liquidation_date', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'last_day_project_implementation', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'check_issuance_date', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'work_accomplishment_report_status', 'VARCHAR(255) DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'performance_rating_remarks', 'TEXT DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'special_disbursing_officer', 'VARCHAR(255) DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'binhi_sites_established_target', 'INT DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'binhi_sites_established_actual', 'INT DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'binhi_facilities_added_target', 'INT DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'binhi_facilities_added_actual', 'INT DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'fertilizer_ohn_target', 'DECIMAL(14,2) DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'fertilizer_ohn_actual', 'DECIMAL(14,2) DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'fertilizer_concoction_target', 'DECIMAL(14,2) DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'fertilizer_concoction_actual', 'DECIMAL(14,2) DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'fertilizer_vermicompost_target', 'DECIMAL(14,2) DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'fertilizer_vermicompost_actual', 'DECIMAL(14,2) DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'area_land_utilized_target', 'DECIMAL(14,2) DEFAULT NULL');

    $conn->query("
        UPDATE program_activity_metadata
        SET
            plgu_forum_from = COALESCE(plgu_forum_from, plgu_forum),
            plgu_forum_to = COALESCE(plgu_forum_to, plgu_forum),
            mlgu_forum_from = COALESCE(mlgu_forum_from, mlgu_forum),
            mlgu_forum_to = COALESCE(mlgu_forum_to, mlgu_forum),
            blgu_forum_from = COALESCE(blgu_forum_from, blgu_forum),
            blgu_forum_to = COALESCE(blgu_forum_to, blgu_forum)
    ");

    $metadataCountResult = $conn->query("SELECT COUNT(*) AS total FROM program_activity_metadata");
    $metadataCount = 0;
    if ($metadataCountResult instanceof mysqli_result) {
        $metadataCount = (int) (($metadataCountResult->fetch_assoc()['total'] ?? 0));
        $metadataCountResult->free();
    }

    $impStatusExists = false;
    $impStatusExistsResult = $conn->query("
        SELECT COUNT(*) AS total
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = 'imp_status'
    ");
    if ($impStatusExistsResult instanceof mysqli_result) {
        $impStatusExists = (int) (($impStatusExistsResult->fetch_assoc()['total'] ?? 0)) > 0;
        $impStatusExistsResult->free();
    }

    if ($metadataCount === 0 && $impStatusExists) {
        $seedFiscalYear = $defaultFiscalYear ?? (int) date('Y');
        $conn->query("
            INSERT INTO program_activity_metadata (
                fiscal_year,
                province,
                municipality,
                barangay,
                plgu_forum,
                mlgu_forum,
                blgu_forum,
                plgu_forum_from,
                plgu_forum_to,
                mlgu_forum_from,
                mlgu_forum_to,
                blgu_forum_from,
                blgu_forum_to,
                site_validation,
                stage1_start_date,
                stage1_end_date,
                stage2_start_date,
                stage2_end_date,
                stage3_start_date,
                stage3_end_date,
                drmd_monitoring_from,
                drmd_monitoring_to,
                drmd_monitoring_participants,
                joint_post_monitoring_from,
                joint_post_monitoring_to,
                joint_post_monitoring_participants,
                payout_schedule_from,
                payout_schedule_to,
                actual_lawa_accomplishment,
                actual_binhi_accomplishment,
                actual_capbuild_accomplishment,
                actual_community_action_plan_accomplishment,
                fund_obligation_partner_beneficiaries,
                fund_disbursement_served_partner_beneficiaries,
                liquidation_date,
                last_day_project_implementation,
                check_issuance_date,
                work_accomplishment_report_status,
                performance_rating_remarks,
                special_disbursing_officer,
                binhi_sites_established_target,
                binhi_sites_established_actual,
                binhi_facilities_added_target,
                binhi_facilities_added_actual,
                fertilizer_ohn_target,
                fertilizer_ohn_actual,
                fertilizer_concoction_target,
                fertilizer_concoction_actual,
                fertilizer_vermicompost_target,
                fertilizer_vermicompost_actual,
                area_land_utilized_target,
                created_at,
                updated_at
            )
            SELECT
                {$seedFiscalYear} AS fiscal_year,
                province,
                municipality,
                barangay,
                MAX(plgu_forum) AS plgu_forum,
                MAX(mlgu_forum) AS mlgu_forum,
                MAX(blgu_forum) AS blgu_forum,
                MIN(plgu_forum) AS plgu_forum_from,
                MAX(plgu_forum) AS plgu_forum_to,
                MIN(mlgu_forum) AS mlgu_forum_from,
                MAX(mlgu_forum) AS mlgu_forum_to,
                MIN(blgu_forum) AS blgu_forum_from,
                MAX(blgu_forum) AS blgu_forum_to,
                NULL AS site_validation,
                NULL AS stage1_start_date,
                NULL AS stage1_end_date,
                NULL AS stage2_start_date,
                NULL AS stage2_end_date,
                NULL AS stage3_start_date,
                NULL AS stage3_end_date,
                NULL AS drmd_monitoring_from,
                NULL AS drmd_monitoring_to,
                NULL AS drmd_monitoring_participants,
                NULL AS joint_post_monitoring_from,
                NULL AS joint_post_monitoring_to,
                NULL AS joint_post_monitoring_participants,
                NULL AS payout_schedule_from,
                NULL AS payout_schedule_to,
                NULL AS actual_lawa_accomplishment,
                NULL AS actual_binhi_accomplishment,
                NULL AS actual_capbuild_accomplishment,
                NULL AS actual_community_action_plan_accomplishment,
                NULL AS fund_obligation_partner_beneficiaries,
                NULL AS fund_disbursement_served_partner_beneficiaries,
                NULL AS liquidation_date,
                NULL AS last_day_project_implementation,
                NULL AS check_issuance_date,
                NULL AS work_accomplishment_report_status,
                NULL AS performance_rating_remarks,
                NULL AS special_disbursing_officer,
                NULL AS binhi_sites_established_target,
                NULL AS binhi_sites_established_actual,
                NULL AS binhi_facilities_added_target,
                NULL AS binhi_facilities_added_actual,
                NULL AS fertilizer_ohn_target,
                NULL AS fertilizer_ohn_actual,
                NULL AS fertilizer_concoction_target,
                NULL AS fertilizer_concoction_actual,
                NULL AS fertilizer_vermicompost_target,
                NULL AS fertilizer_vermicompost_actual,
                NULL AS area_land_utilized_target,
                MIN(created_at) AS created_at,
                MAX(updated_at) AS updated_at
            FROM imp_status
            GROUP BY province, municipality, barangay
        ");
    }

    if ($defaultFiscalYear !== null && $defaultFiscalYear > 0) {
        $hasYearScopedRowsResult = $conn->query("
            SELECT COUNT(*) AS total
            FROM program_activity_metadata
            WHERE fiscal_year > 0
        ");
        $hasYearScopedRows = false;
        if ($hasYearScopedRowsResult instanceof mysqli_result) {
            $hasYearScopedRows = (int) (($hasYearScopedRowsResult->fetch_assoc()['total'] ?? 0)) > 0;
            $hasYearScopedRowsResult->free();
        }

        if (!$hasYearScopedRows) {
            $stmt = $conn->prepare('UPDATE program_activity_metadata SET fiscal_year = ? WHERE fiscal_year = 0');
            if ($stmt) {
                $stmt->bind_param('i', $defaultFiscalYear);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS program_activity_actual_projects (
            id BIGINT NOT NULL AUTO_INCREMENT,
            program_activity_id INT NOT NULL,
            actual_project_id VARCHAR(64) NOT NULL,
            project_code VARCHAR(64) DEFAULT NULL,
            coverage_entry_id VARCHAR(64) DEFAULT NULL,
            target_project_row_id VARCHAR(64) DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            purok VARCHAR(255) NOT NULL,
            latitude DECIMAL(10,7) DEFAULT NULL,
            longitude DECIMAL(10,7) DEFAULT NULL,
            project_name VARCHAR(255) NOT NULL,
            project_classification VARCHAR(32) NOT NULL,
            project_type VARCHAR(255) NOT NULL,
            fertilizer_enabled TINYINT(1) NOT NULL DEFAULT 0,
            fertilizer_ohn_quantity DECIMAL(14,2) DEFAULT NULL,
            fertilizer_concoction_quantity DECIMAL(14,2) DEFAULT NULL,
            fertilizer_vermicompost_quantity DECIMAL(14,2) DEFAULT NULL,
            aquatic_resource VARCHAR(255) DEFAULT NULL,
            aquatic_resource_quantity INT DEFAULT NULL,
            actual_accomplishment VARCHAR(255) DEFAULT NULL,
            land_area VARCHAR(255) DEFAULT NULL,
            land_ownership VARCHAR(255) DEFAULT NULL,
            drive_link VARCHAR(2048) DEFAULT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_program_activity_actual_project (program_activity_id, actual_project_id),
            KEY idx_program_activity_actual_project_parent (program_activity_id, sort_order),
            CONSTRAINT fk_program_activity_actual_project_parent
                FOREIGN KEY (program_activity_id) REFERENCES program_activity_metadata (id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $actualProjectColumns = [];
    $actualProjectColumnStmt = $conn->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'program_activity_actual_projects'
    ");
    if ($actualProjectColumnStmt) {
        $actualProjectColumnStmt->execute();
        foreach (db_stmt_fetch_all_assoc($actualProjectColumnStmt) as $row) {
            $actualProjectColumns[] = (string) ($row['COLUMN_NAME'] ?? '');
        }
        $actualProjectColumnStmt->close();
    }

    if (!in_array('project_code', $actualProjectColumns, true)) {
        $conn->query("ALTER TABLE program_activity_actual_projects ADD COLUMN project_code VARCHAR(64) DEFAULT NULL AFTER actual_project_id");
    }

    programActivityBackfillProjectCodes($conn);

    $initialized = true;
}

function programActivityReplaceActualProjects(mysqli $conn, int $programActivityId, array $rows): void
{
    $deleteStmt = $conn->prepare('DELETE FROM program_activity_actual_projects WHERE program_activity_id = ?');
    if ($deleteStmt) {
        $deleteStmt->bind_param('i', $programActivityId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }

    if ($rows === []) {
        return;
    }

    $insertStmt = $conn->prepare("
        INSERT INTO program_activity_actual_projects (
            program_activity_id,
            actual_project_id,
            project_code,
            coverage_entry_id,
            target_project_row_id,
            sort_order,
            purok,
            latitude,
            longitude,
            project_name,
            project_classification,
            project_type,
            fertilizer_enabled,
            fertilizer_ohn_quantity,
            fertilizer_concoction_quantity,
            fertilizer_vermicompost_quantity,
            aquatic_resource,
            aquatic_resource_quantity,
            actual_accomplishment,
            land_area,
            land_ownership,
            drive_link,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$insertStmt) {
        throw new RuntimeException('Unable to prepare program activity project insert: ' . $conn->error);
    }

    foreach ($rows as $row) {
        $programActivityIdParam = $programActivityId;
        $actualProjectId = (string) ($row['actual_project_id'] ?? '');
        $projectCode = $row['project_code'] ?? null;
        $coverageEntryId = $row['coverage_entry_id'] ?? null;
        $targetProjectRowId = $row['target_project_row_id'] ?? null;
        $sortOrder = (int) ($row['sort_order'] ?? 0);
        $purok = (string) ($row['purok'] ?? '');
        $latitude = $row['latitude'] ?? null;
        $longitude = $row['longitude'] ?? null;
        $projectName = (string) ($row['project_name'] ?? '');
        $projectClassification = (string) ($row['project_classification'] ?? '');
        $projectType = (string) ($row['project_type'] ?? '');
        $fertilizerEnabled = !empty($row['fertilizer_enabled']) ? 1 : 0;
        $fertilizerOhnQuantity = $row['fertilizer_ohn_quantity'] ?? null;
        $fertilizerConcoctionQuantity = $row['fertilizer_concoction_quantity'] ?? null;
        $fertilizerVermicompostQuantity = $row['fertilizer_vermicompost_quantity'] ?? null;
        $aquaticResource = $row['aquatic_resource'] ?? null;
        $aquaticResourceQuantity = $row['aquatic_resource_quantity'] ?? null;
        $actualAccomplishment = $row['actual_accomplishment'] ?? null;
        $landArea = $row['land_area'] ?? null;
        $landOwnership = $row['land_ownership'] ?? null;
        $driveLink = $row['drive_link'] ?? null;
        $status = (string) ($row['status'] ?? 'pending');

        $insertStmt->bind_param(
            'issssisddsssidddsisssss',
            $programActivityIdParam,
            $actualProjectId,
            $projectCode,
            $coverageEntryId,
            $targetProjectRowId,
            $sortOrder,
            $purok,
            $latitude,
            $longitude,
            $projectName,
            $projectClassification,
            $projectType,
            $fertilizerEnabled,
            $fertilizerOhnQuantity,
            $fertilizerConcoctionQuantity,
            $fertilizerVermicompostQuantity,
            $aquaticResource,
            $aquaticResourceQuantity,
            $actualAccomplishment,
            $landArea,
            $landOwnership,
            $driveLink,
            $status
        );
        $insertStmt->execute();
    }

    $insertStmt->close();
}

function programActivityFetchActualProjectsByMetadataIds(mysqli $conn, array $metadataIds): array
{
    $metadataIds = array_values(array_filter(array_map('intval', $metadataIds), static fn(int $value): bool => $value > 0));
    if ($metadataIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($metadataIds), '?'));
    $types = str_repeat('i', count($metadataIds));
    $stmt = $conn->prepare("
        SELECT
            id,
            program_activity_id,
            actual_project_id,
            project_code,
            coverage_entry_id,
            target_project_row_id,
            sort_order,
            purok,
            latitude,
            longitude,
            project_name,
            project_classification,
            project_type,
            fertilizer_enabled,
            fertilizer_ohn_quantity,
            fertilizer_concoction_quantity,
            fertilizer_vermicompost_quantity,
            aquatic_resource,
            aquatic_resource_quantity,
            actual_accomplishment,
            land_area,
            land_ownership,
            drive_link,
            status
        FROM program_activity_actual_projects
        WHERE program_activity_id IN ($placeholders)
        ORDER BY program_activity_id ASC, sort_order ASC, id ASC
    ");

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$metadataIds);
    $stmt->execute();
    $rows = db_stmt_fetch_all_assoc($stmt);
    $stmt->close();

    $grouped = [];
    foreach ($rows as $row) {
        $grouped[(int) ($row['program_activity_id'] ?? 0)][] = $row;
    }

    return $grouped;
}
