<?php
require_once '../security.php';
security_bootstrap_session();
require_once '../auth_helpers.php';
include('../config.php');
require_once __DIR__ . '/activity_metadata.php';
require_once '../project_targets_helpers.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
header('Content-Type: application/json');

if (!isset($_SESSION['selected_year'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Fiscal year not selected.']);
    exit;
}

$municipality = trim((string) ($_GET['municipality'] ?? ''));
$province = trim((string) ($_GET['province'] ?? ''));
$selectedYear = (int) $_SESSION['selected_year'];

ensureProgramActivityMetadata($conn, $selectedYear);
ensureProjectLawaBinhiTargets($conn);

if ($municipality === '' || $province === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Province and municipality are required.']);
    exit;
}

function normalizedTargetRows(array $entries, array $row): array
{
    $targetRows = [];
    foreach ($entries as $entry) {
        $targetRows[] = [
            'project_id' => (string) ($entry['row_id'] ?? ''),
            'module_source' => 'program-targets',
            'province' => $row['province'],
            'municipality' => $row['municipality'],
            'barangay' => $row['barangay'],
            'purok' => (string) ($entry['purok'] ?? ''),
            'project_name' => (string) ($entry['project_name'] ?? ''),
            'classification' => (string) ($entry['project_classification'] ?? ''),
            'project_type' => (string) ($entry['project_type'] ?? ''),
        ];
    }

    return $targetRows;
}

function normalizedCoverageRows(array $entries, array $row): array
{
    $coverageRows = [];
    foreach ($entries as $entry) {
        $projectCode = (string) ($entry['project_code'] ?? '');
        $coverageRows[] = [
            'project_id' => $projectCode !== '' ? $projectCode : (string) (($entry['actual_project_id'] ?? '') ?: ($entry['coverage_entry_id'] ?? '')),
            'project_code' => $projectCode,
            'actual_project_id' => (string) ($entry['actual_project_id'] ?? ''),
            'target_project_row_id' => (string) ($entry['target_project_row_id'] ?? ''),
            'module_source' => 'program-activities',
            'province' => $row['province'],
            'municipality' => $row['municipality'],
            'barangay' => $row['barangay'],
            'purok' => (string) ($entry['purok'] ?? ''),
            'latitude' => isset($entry['latitude']) ? (string) $entry['latitude'] : '',
            'longitude' => isset($entry['longitude']) ? (string) $entry['longitude'] : '',
            'project_name' => (string) ($entry['project_name'] ?? ''),
            'classification' => (string) ($entry['project_classification'] ?? ''),
            'project_type' => (string) ($entry['project_type'] ?? ''),
            'fertilizer_enabled' => !empty($entry['fertilizer_enabled']) ? '1' : '0',
            'fertilizer_ohn_quantity' => isset($entry['fertilizer_ohn_quantity']) ? (string) $entry['fertilizer_ohn_quantity'] : '',
            'fertilizer_concoction_quantity' => isset($entry['fertilizer_concoction_quantity']) ? (string) $entry['fertilizer_concoction_quantity'] : '',
            'fertilizer_vermicompost_quantity' => isset($entry['fertilizer_vermicompost_quantity']) ? (string) $entry['fertilizer_vermicompost_quantity'] : '',
            'aquatic_resource' => (string) ($entry['aquatic_resource'] ?? ''),
            'aquatic_resource_quantity' => isset($entry['aquatic_resource_quantity']) ? (string) $entry['aquatic_resource_quantity'] : '',
            'drive_link' => (string) ($entry['drive_link'] ?? ''),
            'status' => (string) ($entry['status'] ?? 'pending'),
            'actual_accomplishment' => isset($entry['actual_accomplishment']) ? (string) $entry['actual_accomplishment'] : '',
            'land_area' => (string) ($entry['land_area'] ?? ''),
            'land_ownership' => (string) ($entry['land_ownership'] ?? ''),
        ];
    }

    return $coverageRows;
}

$stmt = $conn->prepare("
    SELECT
        locations.province,
        locations.municipality,
        locations.barangay,
        targets.id AS target_id,
        metadata.id AS metadata_id,
        COALESCE(targets.lawa_target, 0) AS lawa_target,
        COALESCE(targets.binhi_target, 0) AS binhi_target,
        COALESCE(targets.capbuild_target, 0) AS capbuild_target,
        COALESCE(targets.community_action_plan_target, 0) AS community_action_plan_target,
        COALESCE(targets.target_partner_beneficiaries, 0) AS target_partner_beneficiaries,
        COALESCE(actuals.beneficiary_count, 0) AS actual_beneficiaries,
        province_forums.plgu_forum,
        municipality_forums.mlgu_forum,
        metadata.blgu_forum,
        province_forums.plgu_forum_from,
        province_forums.plgu_forum_to,
        municipality_forums.mlgu_forum_from,
        municipality_forums.mlgu_forum_to,
        metadata.blgu_forum_from,
        metadata.blgu_forum_to,
        metadata.site_validation,
        metadata.stage1_start_date,
        metadata.stage1_end_date,
        metadata.stage2_start_date,
        metadata.stage2_end_date,
        metadata.stage3_start_date,
        metadata.stage3_end_date,
        metadata.drmd_monitoring_from,
        metadata.drmd_monitoring_to,
        metadata.drmd_monitoring_participants,
        metadata.joint_post_monitoring_from,
        metadata.joint_post_monitoring_to,
        metadata.joint_post_monitoring_participants,
        metadata.payout_schedule_from,
        metadata.payout_schedule_to,
        metadata.actual_lawa_accomplishment,
        metadata.actual_binhi_accomplishment,
        metadata.actual_capbuild_accomplishment,
        metadata.actual_community_action_plan_accomplishment,
        metadata.fund_obligation_partner_beneficiaries,
        metadata.fund_disbursement_served_partner_beneficiaries,
        metadata.liquidation_date,
        metadata.last_day_project_implementation,
        metadata.check_issuance_date,
        metadata.work_accomplishment_report_status,
        metadata.performance_rating_remarks,
        metadata.special_disbursing_officer,
        metadata.binhi_sites_established_target,
        metadata.binhi_sites_established_actual,
        metadata.binhi_facilities_added_target,
        metadata.binhi_facilities_added_actual,
        metadata.fertilizer_ohn_target,
        metadata.fertilizer_ohn_actual,
        metadata.fertilizer_concoction_target,
        metadata.fertilizer_concoction_actual,
        metadata.fertilizer_vermicompost_target,
        metadata.fertilizer_vermicompost_actual,
        metadata.area_land_utilized_target,
        metadata.updated_at
    FROM (
        SELECT province, municipality, barangay
        FROM project_lawa_binhi_targets
        WHERE fiscal_year = ?
          AND province = ?
          AND municipality = ?

        UNION

        SELECT province, lgu AS municipality, barangay
        FROM meb
        WHERE YEAR(time_stamp) = ?
          AND province = ?
          AND lgu = ?
        GROUP BY province, lgu, barangay
    ) AS locations
    LEFT JOIN project_lawa_binhi_targets AS targets
        ON targets.fiscal_year = ?
       AND targets.province = locations.province
       AND targets.municipality = locations.municipality
       AND targets.barangay = locations.barangay
    LEFT JOIN (
        SELECT
            province,
            lgu AS municipality,
            barangay,
            COUNT(*) AS beneficiary_count
        FROM meb
        WHERE YEAR(time_stamp) = ?
        GROUP BY province, lgu, barangay
    ) AS actuals
        ON actuals.province = locations.province
       AND actuals.municipality = locations.municipality
       AND actuals.barangay = locations.barangay
    LEFT JOIN (
        SELECT
            province,
            MAX(plgu_forum) AS plgu_forum,
            MIN(COALESCE(plgu_forum_from, plgu_forum)) AS plgu_forum_from,
            MAX(COALESCE(plgu_forum_to, plgu_forum)) AS plgu_forum_to
        FROM program_activity_metadata
        WHERE fiscal_year = ?
          AND (
              plgu_forum IS NOT NULL
              OR plgu_forum_from IS NOT NULL
              OR plgu_forum_to IS NOT NULL
          )
        GROUP BY province
    ) AS province_forums
        ON province_forums.province = locations.province
    LEFT JOIN (
        SELECT
            province,
            municipality,
            MAX(mlgu_forum) AS mlgu_forum,
            MIN(COALESCE(mlgu_forum_from, mlgu_forum)) AS mlgu_forum_from,
            MAX(COALESCE(mlgu_forum_to, mlgu_forum)) AS mlgu_forum_to
        FROM program_activity_metadata
        WHERE fiscal_year = ?
          AND (
              mlgu_forum IS NOT NULL
              OR mlgu_forum_from IS NOT NULL
              OR mlgu_forum_to IS NOT NULL
          )
        GROUP BY province, municipality
    ) AS municipality_forums
        ON municipality_forums.province = locations.province
       AND municipality_forums.municipality = locations.municipality
    LEFT JOIN program_activity_metadata AS metadata
        ON metadata.fiscal_year = ?
       AND metadata.province = locations.province
       AND metadata.municipality = locations.municipality
       AND metadata.barangay = locations.barangay
    ORDER BY locations.barangay ASC
");
$stmt->bind_param('ississiiiii', $selectedYear, $province, $municipality, $selectedYear, $province, $municipality, $selectedYear, $selectedYear, $selectedYear, $selectedYear, $selectedYear);
$stmt->execute();
$result = db_stmt_fetch_all_assoc($stmt);

$targetEntryMap = projectTargetsFetchEntriesByTargetIds(
    $conn,
    array_map(static fn(array $row): int => (int) ($row['target_id'] ?? 0), $result)
);
$actualProjectMap = programActivityFetchActualProjectsByMetadataIds(
    $conn,
    array_map(static fn(array $row): int => (int) ($row['metadata_id'] ?? 0), $result)
);

$rows = [];
foreach ($result as $row) {
    $targetEntries = $targetEntryMap[(int) ($row['target_id'] ?? 0)] ?? [];
    $actualEntries = $actualProjectMap[(int) ($row['metadata_id'] ?? 0)] ?? [];

    $targetProjectRowIds = array_map(static fn(array $entry): string => (string) ($entry['row_id'] ?? ''), $targetEntries);
    $targetProjectTokens = array_map(static fn(array $entry): string => (string) ($entry['project_name'] ?? ''), $targetEntries);
    $projectClassifications = array_map(static fn(array $entry): string => (string) ($entry['project_classification'] ?? ''), $targetEntries);
    $targetProjectTypes = array_map(static fn(array $entry): string => (string) ($entry['project_type'] ?? ''), $targetEntries);
    $targetFertilizerEnabledFlags = array_map(static fn(array $entry): string => !empty($entry['fertilizer_enabled']) ? '1' : '0', $targetEntries);
    $targetFertilizerOhnTargets = array_map(static fn(array $entry): string => isset($entry['fertilizer_ohn_target']) ? (string) $entry['fertilizer_ohn_target'] : '', $targetEntries);
    $targetFertilizerConcoctionTargets = array_map(static fn(array $entry): string => isset($entry['fertilizer_concoction_target']) ? (string) $entry['fertilizer_concoction_target'] : '', $targetEntries);
    $targetFertilizerVermicompostTargets = array_map(static fn(array $entry): string => isset($entry['fertilizer_vermicompost_target']) ? (string) $entry['fertilizer_vermicompost_target'] : '', $targetEntries);
    $targetAquaticResources = array_map(static fn(array $entry): string => (string) ($entry['aquatic_resource'] ?? ''), $targetEntries);
    $targetAquaticResourceQuantities = array_map(static fn(array $entry): string => isset($entry['aquatic_resource_quantity']) ? (string) $entry['aquatic_resource_quantity'] : '', $targetEntries);
    $puroks = array_map(static fn(array $entry): string => (string) ($entry['purok'] ?? ''), $targetEntries);

    $actualCoveragePuroks = array_map(static fn(array $entry): string => (string) ($entry['purok'] ?? ''), $actualEntries);
    $actualProjectCodes = array_map(static fn(array $entry): string => (string) ($entry['project_code'] ?? ''), $actualEntries);
    $actualProjectIds = array_map(static fn(array $entry): string => (string) ($entry['actual_project_id'] ?? ''), $actualEntries);
    $targetProjectRowLinks = array_map(static fn(array $entry): string => (string) ($entry['target_project_row_id'] ?? ''), $actualEntries);
    $coverageEntryIds = array_map(static fn(array $entry): string => (string) (($entry['coverage_entry_id'] ?? '') ?: ($entry['actual_project_id'] ?? '')), $actualEntries);
    $actualCoverageLatitudes = array_map(static fn(array $entry): string => isset($entry['latitude']) ? (string) $entry['latitude'] : '', $actualEntries);
    $actualCoverageLongitudes = array_map(static fn(array $entry): string => isset($entry['longitude']) ? (string) $entry['longitude'] : '', $actualEntries);
    $actualCoverageProjectNames = array_map(static fn(array $entry): string => (string) ($entry['project_name'] ?? ''), $actualEntries);
    $actualCoverageProjectClassifications = array_map(static fn(array $entry): string => (string) ($entry['project_classification'] ?? ''), $actualEntries);
    $actualCoverageProjectTypes = array_map(static fn(array $entry): string => (string) ($entry['project_type'] ?? ''), $actualEntries);
    $actualCoverageFertilizerEnabledFlags = array_map(static fn(array $entry): string => !empty($entry['fertilizer_enabled']) ? '1' : '0', $actualEntries);
    $actualCoverageFertilizerOhnQuantities = array_map(static fn(array $entry): string => isset($entry['fertilizer_ohn_quantity']) ? (string) $entry['fertilizer_ohn_quantity'] : '', $actualEntries);
    $actualCoverageFertilizerConcoctionQuantities = array_map(static fn(array $entry): string => isset($entry['fertilizer_concoction_quantity']) ? (string) $entry['fertilizer_concoction_quantity'] : '', $actualEntries);
    $actualCoverageFertilizerVermicompostQuantities = array_map(static fn(array $entry): string => isset($entry['fertilizer_vermicompost_quantity']) ? (string) $entry['fertilizer_vermicompost_quantity'] : '', $actualEntries);
    $actualCoverageAquaticResources = array_map(static fn(array $entry): string => (string) ($entry['aquatic_resource'] ?? ''), $actualEntries);
    $actualCoverageAquaticResourceQuantities = array_map(static fn(array $entry): string => isset($entry['aquatic_resource_quantity']) ? (string) $entry['aquatic_resource_quantity'] : '', $actualEntries);
    $actualCoverageLandAreas = array_map(static fn(array $entry): string => (string) ($entry['land_area'] ?? ''), $actualEntries);
    $actualCoverageLandOwnerships = array_map(static fn(array $entry): string => (string) ($entry['land_ownership'] ?? ''), $actualEntries);
    $actualCoverageDriveLinks = array_map(static fn(array $entry): string => (string) ($entry['drive_link'] ?? ''), $actualEntries);
    $coverageActualAccomplishments = array_map(static fn(array $entry): int => (int) ($entry['actual_accomplishment'] ?? 0), $actualEntries);
    $coverageActualStatuses = array_map(static fn(array $entry): string => (string) ($entry['status'] ?? 'pending'), $actualEntries);

    $targetRows = normalizedTargetRows($targetEntries, $row);
    $coverageRows = normalizedCoverageRows($actualEntries, $row);
    $projectTokens = $targetProjectTokens !== [] ? $targetProjectTokens : $actualCoverageProjectNames;

    $rows[] = [
        'province' => $row['province'],
        'municipality' => $row['municipality'],
        'barangay' => $row['barangay'],
        'lawa_target' => (int) $row['lawa_target'],
        'binhi_target' => (int) $row['binhi_target'],
        'capbuild_target' => (int) $row['capbuild_target'],
        'community_action_plan_target' => (int) $row['community_action_plan_target'],
        'target_partner_beneficiaries' => (int) $row['target_partner_beneficiaries'],
        'actual_beneficiaries' => (int) $row['actual_beneficiaries'],
        'puroks' => $puroks,
        'target_project_row_ids' => $targetProjectRowIds,
        'target_project_names' => $targetProjectTokens,
        'project_classifications' => $projectClassifications,
        'target_project_types' => $targetProjectTypes,
        'target_fertilizer_enabled_flags' => $targetFertilizerEnabledFlags,
        'target_fertilizer_ohn_targets' => $targetFertilizerOhnTargets,
        'target_fertilizer_concoction_targets' => $targetFertilizerConcoctionTargets,
        'target_fertilizer_vermicompost_targets' => $targetFertilizerVermicompostTargets,
        'target_aquatic_resources' => $targetAquaticResources,
        'target_aquatic_resource_quantities' => $targetAquaticResourceQuantities,
        'target_rows' => $targetRows,
        'coverage_puroks' => $actualCoveragePuroks,
        'project_codes' => $actualProjectCodes,
        'actual_project_ids' => $actualProjectIds,
        'target_project_row_ids_for_actuals' => $targetProjectRowLinks,
        'coverage_entry_ids' => $coverageEntryIds,
        'coverage_latitudes' => $actualCoverageLatitudes,
        'coverage_longitudes' => $actualCoverageLongitudes,
        'coverage_project_names' => $actualCoverageProjectNames,
        'coverage_project_classifications' => $actualCoverageProjectClassifications,
        'coverage_project_types' => $actualCoverageProjectTypes,
        'coverage_fertilizer_enabled_flags' => $actualCoverageFertilizerEnabledFlags,
        'coverage_fertilizer_ohn_quantities' => $actualCoverageFertilizerOhnQuantities,
        'coverage_fertilizer_concoction_quantities' => $actualCoverageFertilizerConcoctionQuantities,
        'coverage_fertilizer_vermicompost_quantities' => $actualCoverageFertilizerVermicompostQuantities,
        'coverage_aquatic_resources' => $actualCoverageAquaticResources,
        'coverage_aquatic_resource_quantities' => $actualCoverageAquaticResourceQuantities,
        'coverage_land_areas' => $actualCoverageLandAreas,
        'coverage_land_ownerships' => $actualCoverageLandOwnerships,
        'coverage_drive_links' => $actualCoverageDriveLinks,
        'coverage_rows' => $coverageRows,
        'coverage_actual_accomplishments' => $coverageActualAccomplishments,
        'coverage_actual_statuses' => $coverageActualStatuses,
        'plgu_forum_from' => $row['plgu_forum_from'] ?: $row['plgu_forum'],
        'plgu_forum_to' => $row['plgu_forum_to'] ?: $row['plgu_forum'],
        'mlgu_forum_from' => $row['mlgu_forum_from'] ?: $row['mlgu_forum'],
        'mlgu_forum_to' => $row['mlgu_forum_to'] ?: $row['mlgu_forum'],
        'blgu_forum_from' => $row['blgu_forum_from'] ?: $row['blgu_forum'],
        'blgu_forum_to' => $row['blgu_forum_to'] ?: $row['blgu_forum'],
        'site_validation' => $row['site_validation'],
        'stage1_start_date' => $row['stage1_start_date'],
        'stage1_end_date' => $row['stage1_end_date'],
        'stage2_start_date' => $row['stage2_start_date'],
        'stage2_end_date' => $row['stage2_end_date'],
        'stage3_start_date' => $row['stage3_start_date'],
        'stage3_end_date' => $row['stage3_end_date'],
        'drmd_monitoring_from' => $row['drmd_monitoring_from'],
        'drmd_monitoring_to' => $row['drmd_monitoring_to'],
        'drmd_monitoring_participants' => $row['drmd_monitoring_participants'],
        'joint_post_monitoring_from' => $row['joint_post_monitoring_from'],
        'joint_post_monitoring_to' => $row['joint_post_monitoring_to'],
        'joint_post_monitoring_participants' => $row['joint_post_monitoring_participants'],
        'payout_schedule_from' => $row['payout_schedule_from'],
        'payout_schedule_to' => $row['payout_schedule_to'],
        'actual_lawa_accomplishment' => (int) ($row['actual_lawa_accomplishment'] ?? 0),
        'actual_binhi_accomplishment' => (int) ($row['actual_binhi_accomplishment'] ?? 0),
        'actual_capbuild_accomplishment' => (int) ($row['actual_capbuild_accomplishment'] ?? 0),
        'actual_community_action_plan_accomplishment' => (int) ($row['actual_community_action_plan_accomplishment'] ?? 0),
        'fund_obligation_partner_beneficiaries' => (int) ($row['fund_obligation_partner_beneficiaries'] ?? 0),
        'fund_disbursement_served_partner_beneficiaries' => (int) ($row['fund_disbursement_served_partner_beneficiaries'] ?? 0),
        'liquidation_date' => $row['liquidation_date'],
        'last_day_project_implementation' => $row['last_day_project_implementation'],
        'check_issuance_date' => $row['check_issuance_date'],
        'work_accomplishment_report_status' => $row['work_accomplishment_report_status'],
        'performance_rating_remarks' => $row['performance_rating_remarks'],
        'special_disbursing_officer' => $row['special_disbursing_officer'],
        'binhi_sites_established_target' => (int) ($row['binhi_sites_established_target'] ?? 0),
        'binhi_sites_established_actual' => (int) ($row['binhi_sites_established_actual'] ?? 0),
        'binhi_facilities_added_target' => (int) ($row['binhi_facilities_added_target'] ?? 0),
        'binhi_facilities_added_actual' => (int) ($row['binhi_facilities_added_actual'] ?? 0),
        'fertilizer_ohn_target' => isset($row['fertilizer_ohn_target']) ? (float) $row['fertilizer_ohn_target'] : 0.0,
        'fertilizer_ohn_actual' => isset($row['fertilizer_ohn_actual']) ? (float) $row['fertilizer_ohn_actual'] : 0.0,
        'fertilizer_concoction_target' => isset($row['fertilizer_concoction_target']) ? (float) $row['fertilizer_concoction_target'] : 0.0,
        'fertilizer_concoction_actual' => isset($row['fertilizer_concoction_actual']) ? (float) $row['fertilizer_concoction_actual'] : 0.0,
        'fertilizer_vermicompost_target' => isset($row['fertilizer_vermicompost_target']) ? (float) $row['fertilizer_vermicompost_target'] : 0.0,
        'fertilizer_vermicompost_actual' => isset($row['fertilizer_vermicompost_actual']) ? (float) $row['fertilizer_vermicompost_actual'] : 0.0,
        'area_land_utilized_target' => isset($row['area_land_utilized_target']) ? (float) $row['area_land_utilized_target'] : 0.0,
        'projects' => $projectTokens,
        'updated_at' => $row['updated_at'],
    ];
}
$stmt->close();

echo json_encode([
    'success' => true,
    'rows' => $rows,
    'is_admin' => isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin',
]);
