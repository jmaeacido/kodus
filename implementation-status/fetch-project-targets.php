<?php
require_once '../security.php';
security_bootstrap_session();
require_once '../auth_helpers.php';
include('../config.php');
require_once '../project_targets_helpers.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
header('Content-Type: application/json');

ensureProjectLawaBinhiTargets($conn);

if (!isset($_SESSION['selected_year'])) {
    echo json_encode(['data' => [], 'error' => 'Fiscal year not selected']);
    exit;
}

$selectedYear = (int) $_SESSION['selected_year'];
$canManageTargets = auth_can_manage_program_targets();

$stmt = $conn->prepare("
    SELECT id, fiscal_year, province, municipality, barangay, lawa_target, binhi_target, binhi_vegetable_target, binhi_crops_target, binhi_disaster_resilient_crops_target, binhi_fruit_bearing_trees_target, binhi_tilapia_target, capbuild_target, community_action_plan_target, target_partner_beneficiaries, updated_at
    FROM project_lawa_binhi_targets
    WHERE fiscal_year = ?
    ORDER BY province ASC, municipality ASC, barangay ASC
");
$stmt->bind_param('i', $selectedYear);
$stmt->execute();
$result = db_stmt_fetch_all_assoc($stmt);
$entryMap = projectTargetsFetchEntriesByTargetIds($conn, array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $result));

$data = [];
foreach ($result as $row) {
    $entryRows = $entryMap[(int) $row['id']] ?? [];
    $projectRowIds = [];
    $projectNames = [];
    $projectTypes = [];
    $projectClassifications = [];
    $puroks = [];
    $fertilizerEnabledFlags = [];
    $fertilizerOhnTargets = [];
    $fertilizerConcoctionTargets = [];
    $fertilizerVermicompostTargets = [];
    $binhiTargetQuantities = [];
    $aquaticResources = [];
    $aquaticResourceQuantities = [];
    $projectRows = [];
    foreach ($entryRows as $index => $entryRow) {
        $projectRowIds[] = (string) ($entryRow['row_id'] ?? ('target-' . (int) $row['id'] . '-' . $index));
        $projectNames[] = (string) ($entryRow['project_name'] ?? '');
        $projectTypes[] = (string) ($entryRow['project_type'] ?? '');
        $projectClassifications[] = (string) ($entryRow['project_classification'] ?? '');
        $puroks[] = (string) ($entryRow['purok'] ?? '');
        $fertilizerEnabledFlags[] = !empty($entryRow['fertilizer_enabled']) ? '1' : '0';
        $fertilizerOhnTargets[] = $entryRow['fertilizer_ohn_target'] !== null ? (string) $entryRow['fertilizer_ohn_target'] : '';
        $fertilizerConcoctionTargets[] = $entryRow['fertilizer_concoction_target'] !== null ? (string) $entryRow['fertilizer_concoction_target'] : '';
        $fertilizerVermicompostTargets[] = $entryRow['fertilizer_vermicompost_target'] !== null ? (string) $entryRow['fertilizer_vermicompost_target'] : '';
        $binhiTargetQuantities[] = $entryRow['binhi_target_quantity'] !== null ? (string) $entryRow['binhi_target_quantity'] : '';
        $aquaticResources[] = (string) ($entryRow['aquatic_resource'] ?? '');
        $aquaticResourceQuantities[] = $entryRow['aquatic_resource_quantity'] !== null ? (string) $entryRow['aquatic_resource_quantity'] : '';
        $projectRows[] = [
            'project_id' => $projectRowIds[$index] ?? ('target-' . (int) $row['id'] . '-' . $index),
            'parent_record_id' => (int) $row['id'],
            'module_source' => 'program-targets',
            'fiscal_year' => (int) $row['fiscal_year'],
            'province' => $row['province'],
            'municipality' => $row['municipality'],
            'barangay' => $row['barangay'],
            'purok' => $puroks[$index] ?? '',
            'project_name' => $projectNames[$index] ?? '',
            'project_type' => $projectTypes[$index] ?? '',
            'classification' => $projectClassifications[$index] ?? '',
            'fertilizer_enabled' => $fertilizerEnabledFlags[$index] ?? '',
            'fertilizer_ohn_target' => $fertilizerOhnTargets[$index] ?? '',
            'fertilizer_concoction_target' => $fertilizerConcoctionTargets[$index] ?? '',
            'fertilizer_vermicompost_target' => $fertilizerVermicompostTargets[$index] ?? '',
            'binhi_target_quantity' => $binhiTargetQuantities[$index] ?? '',
            'aquatic_resource' => $aquaticResources[$index] ?? '',
            'aquatic_resource_quantity' => $aquaticResourceQuantities[$index] ?? '',
        ];
    }

    $actions = '<span class="text-muted">View only</span>';
    if ($canManageTargets) {
        $actions = '<span class="kodus-row-actions"><button type="button" class="btn btn-sm btn-primary edit-target-btn" data-id="' . (int) $row['id'] . '" data-no-loader="true" title="Edit" aria-label="Edit"><i class="nav-icon fas fa-pen"></i></button>'
            . '<button type="button" class="btn btn-sm btn-danger delete-target-btn" data-id="' . (int) $row['id'] . '" data-location="' . htmlspecialchars($row['barangay'] . ', ' . $row['municipality'], ENT_QUOTES, 'UTF-8') . '" data-no-loader="true" title="Delete" aria-label="Delete"><i class="nav-icon fas fa-trash"></i></button>';
        $actions .= '</span>';
    }

    $data[] = [
        'id' => (int) $row['id'],
        'fiscal_year' => (int) $row['fiscal_year'],
        'province' => $row['province'],
        'municipality' => $row['municipality'],
        'barangay' => $row['barangay'],
        'puroks' => $puroks,
        'project_row_ids' => $projectRowIds,
        'puroks_display' => implode(', ', $puroks),
        'project_names' => $projectNames,
        'project_types' => $projectTypes,
        'project_classifications' => $projectClassifications,
        'fertilizer_enabled_flags' => $fertilizerEnabledFlags,
        'fertilizer_ohn_targets' => $fertilizerOhnTargets,
        'fertilizer_concoction_targets' => $fertilizerConcoctionTargets,
        'fertilizer_vermicompost_targets' => $fertilizerVermicompostTargets,
        'binhi_target_quantities' => $binhiTargetQuantities,
        'aquatic_resources' => $aquaticResources,
        'aquatic_resource_quantities' => $aquaticResourceQuantities,
        'project_rows' => $projectRows,
        'lawa_target' => (int) ($row['lawa_target'] ?? 0),
        'binhi_target' => (int) ($row['binhi_target'] ?? 0),
        'binhi_vegetable_target' => (int) ($row['binhi_vegetable_target'] ?? 0),
        'binhi_crops_target' => (int) ($row['binhi_crops_target'] ?? 0),
        'binhi_disaster_resilient_crops_target' => (int) ($row['binhi_disaster_resilient_crops_target'] ?? 0),
        'binhi_fruit_bearing_trees_target' => (int) ($row['binhi_fruit_bearing_trees_target'] ?? 0),
        'binhi_tilapia_target' => (int) ($row['binhi_tilapia_target'] ?? 0),
        'capbuild_target' => (int) ($row['capbuild_target'] ?? 0),
        'community_action_plan_target' => (int) ($row['community_action_plan_target'] ?? 0),
        'project_names_display' => implode(', ', $projectNames),
        'project_types_display' => implode(', ', $projectTypes),
        'project_classifications_display' => implode(', ', $projectClassifications),
        'target_partner_beneficiaries' => (int) $row['target_partner_beneficiaries'],
        'updated_at' => !empty($row['updated_at']) ? date('M d, Y h:i A', strtotime($row['updated_at'])) : '',
        'action' => $actions,
    ];
}
$stmt->close();

echo json_encode(['data' => $data]);
