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
            puroks TEXT DEFAULT NULL,
            project_names TEXT DEFAULT NULL,
            project_classifications TEXT DEFAULT NULL,
            lawa_target INT NOT NULL DEFAULT 0,
            binhi_target INT NOT NULL DEFAULT 0,
            capbuild_target INT NOT NULL DEFAULT 0,
            community_action_plan_target INT NOT NULL DEFAULT 0,
            target_partner_beneficiaries INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_project_target_location (fiscal_year, province, municipality, barangay),
            KEY idx_project_target_year_location (fiscal_year, province, municipality)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    projectTargetsEnsureColumn($conn, 'puroks', "ALTER TABLE project_lawa_binhi_targets ADD COLUMN puroks TEXT DEFAULT NULL");
    projectTargetsEnsureColumn($conn, 'project_names', "ALTER TABLE project_lawa_binhi_targets ADD COLUMN project_names TEXT DEFAULT NULL");
    projectTargetsEnsureColumn($conn, 'project_classifications', "ALTER TABLE project_lawa_binhi_targets ADD COLUMN project_classifications TEXT DEFAULT NULL");
    projectTargetsEnsureColumn($conn, 'lawa_target', "ALTER TABLE project_lawa_binhi_targets ADD COLUMN lawa_target INT NOT NULL DEFAULT 0 AFTER project_classifications");
    projectTargetsEnsureColumn($conn, 'binhi_target', "ALTER TABLE project_lawa_binhi_targets ADD COLUMN binhi_target INT NOT NULL DEFAULT 0 AFTER lawa_target");
    projectTargetsEnsureColumn($conn, 'capbuild_target', "ALTER TABLE project_lawa_binhi_targets ADD COLUMN capbuild_target INT NOT NULL DEFAULT 0 AFTER binhi_target");
    projectTargetsEnsureColumn($conn, 'community_action_plan_target', "ALTER TABLE project_lawa_binhi_targets ADD COLUMN community_action_plan_target INT NOT NULL DEFAULT 0 AFTER capbuild_target");

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
    $parts = preg_split('/\|\||[\r\n,]+/', (string) $value);
    return normalizeProjectTargetList($parts ?: [], $uppercase);
}
