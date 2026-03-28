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

function ensureProgramActivityMetadata(mysqli $conn): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS program_activity_metadata (
            id INT NOT NULL AUTO_INCREMENT,
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
            site_validation VARCHAR(1000) DEFAULT NULL,
            stage1_start_date DATE DEFAULT NULL,
            stage1_end_date DATE DEFAULT NULL,
            stage2_start_date DATE DEFAULT NULL,
            stage2_end_date DATE DEFAULT NULL,
            stage3_start_date DATE DEFAULT NULL,
            stage3_end_date DATE DEFAULT NULL,
            drmd_monitoring_from DATE DEFAULT NULL,
            drmd_monitoring_to DATE DEFAULT NULL,
            drmd_monitoring_participants VARCHAR(1000) DEFAULT NULL,
            joint_post_monitoring_from DATE DEFAULT NULL,
            joint_post_monitoring_to DATE DEFAULT NULL,
            joint_post_monitoring_participants VARCHAR(1000) DEFAULT NULL,
            payout_schedule_from DATE DEFAULT NULL,
            payout_schedule_to DATE DEFAULT NULL,
            fund_obligation_partner_beneficiaries INT DEFAULT NULL,
            fund_disbursement_served_partner_beneficiaries INT DEFAULT NULL,
            liquidation_date DATE DEFAULT NULL,
            last_day_project_implementation DATE DEFAULT NULL,
            check_issuance_date DATE DEFAULT NULL,
            work_accomplishment_report_status VARCHAR(255) DEFAULT NULL,
            performance_rating_remarks TEXT DEFAULT NULL,
            special_disbursing_officer VARCHAR(255) DEFAULT NULL,
            project_names VARCHAR(1000) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_program_activity_location (province, municipality, barangay)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    ensureProgramActivityMetadataColumn($conn, 'plgu_forum_from', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'plgu_forum_to', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'mlgu_forum_from', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'mlgu_forum_to', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'blgu_forum_from', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'blgu_forum_to', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'site_validation', 'VARCHAR(1000) DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'stage1_start_date', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'stage1_end_date', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'stage2_start_date', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'stage2_end_date', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'stage3_start_date', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'stage3_end_date', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'drmd_monitoring_from', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'drmd_monitoring_to', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'drmd_monitoring_participants', 'VARCHAR(1000) DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'joint_post_monitoring_from', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'joint_post_monitoring_to', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'joint_post_monitoring_participants', 'VARCHAR(1000) DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'payout_schedule_from', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'payout_schedule_to', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'fund_obligation_partner_beneficiaries', 'INT DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'fund_disbursement_served_partner_beneficiaries', 'INT DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'liquidation_date', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'last_day_project_implementation', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'check_issuance_date', 'DATE DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'work_accomplishment_report_status', 'VARCHAR(255) DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'performance_rating_remarks', 'TEXT DEFAULT NULL');
    ensureProgramActivityMetadataColumn($conn, 'special_disbursing_officer', 'VARCHAR(255) DEFAULT NULL');

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
        $conn->query("
            INSERT INTO program_activity_metadata (
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
                fund_obligation_partner_beneficiaries,
                fund_disbursement_served_partner_beneficiaries,
                liquidation_date,
                last_day_project_implementation,
                check_issuance_date,
                work_accomplishment_report_status,
                performance_rating_remarks,
                special_disbursing_officer,
                project_names,
                created_at,
                updated_at
            )
            SELECT
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
                NULL AS fund_obligation_partner_beneficiaries,
                NULL AS fund_disbursement_served_partner_beneficiaries,
                NULL AS liquidation_date,
                NULL AS last_day_project_implementation,
                NULL AS check_issuance_date,
                NULL AS work_accomplishment_report_status,
                NULL AS performance_rating_remarks,
                NULL AS special_disbursing_officer,
                GROUP_CONCAT(DISTINCT NULLIF(TRIM(project_names), '') SEPARATOR '||') AS project_names,
                MIN(created_at) AS created_at,
                MAX(updated_at) AS updated_at
            FROM imp_status
            GROUP BY province, municipality, barangay
        ");
    }

    $initialized = true;
}
