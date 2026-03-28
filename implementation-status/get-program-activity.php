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

ensureProgramActivityMetadata($conn);
ensureProjectLawaBinhiTargets($conn);

if (!isset($_SESSION['selected_year'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Fiscal year not selected.']);
    exit;
}

$municipality = trim((string) ($_GET['municipality'] ?? ''));
$province = trim((string) ($_GET['province'] ?? ''));
$selectedYear = (int) $_SESSION['selected_year'];

if ($municipality === '' || $province === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Province and municipality are required.']);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        locations.province,
        locations.municipality,
        locations.barangay,
        COALESCE(targets.lawa_target, 0) AS lawa_target,
        COALESCE(targets.binhi_target, 0) AS binhi_target,
        COALESCE(targets.target_partner_beneficiaries, 0) AS target_partner_beneficiaries,
        targets.puroks,
        targets.project_names AS target_project_names,
        targets.project_classifications,
        COALESCE(actuals.beneficiary_count, 0) AS actual_beneficiaries,
        metadata.plgu_forum,
        metadata.mlgu_forum,
        metadata.blgu_forum,
        metadata.plgu_forum_from,
        metadata.plgu_forum_to,
        metadata.mlgu_forum_from,
        metadata.mlgu_forum_to,
        metadata.blgu_forum_from,
        metadata.blgu_forum_to,
        metadata.site_validation,
        metadata.stage1_start_date,
        metadata.stage1_end_date,
        metadata.stage2_start_date,
        metadata.stage2_end_date,
        metadata.stage3_start_date,
        metadata.stage3_end_date,
        metadata.project_names,
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
    LEFT JOIN program_activity_metadata AS metadata
        ON metadata.province = locations.province
       AND metadata.municipality = locations.municipality
       AND metadata.barangay = locations.barangay
    ORDER BY locations.barangay ASC
");
$stmt->bind_param('ississii', $selectedYear, $province, $municipality, $selectedYear, $province, $municipality, $selectedYear, $selectedYear);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $savedProjectTokens = parseProjectTargetMultiValueCell((string) ($row['project_names'] ?? ''), false);
    $targetProjectTokens = parseProjectTargetMultiValueCell((string) ($row['target_project_names'] ?? ''), false);
    $projectTokens = $savedProjectTokens ?: $targetProjectTokens;
    $projectClassifications = parseProjectTargetMultiValueCell((string) ($row['project_classifications'] ?? ''));
    $puroks = parseProjectTargetMultiValueCell((string) ($row['puroks'] ?? ''));

    $rows[] = [
        'province' => $row['province'],
        'municipality' => $row['municipality'],
        'barangay' => $row['barangay'],
        'lawa_target' => (int) $row['lawa_target'],
        'binhi_target' => (int) $row['binhi_target'],
        'target_partner_beneficiaries' => (int) $row['target_partner_beneficiaries'],
        'actual_beneficiaries' => (int) $row['actual_beneficiaries'],
        'puroks' => $puroks,
        'target_project_names' => $targetProjectTokens,
        'project_classifications' => $projectClassifications,
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
