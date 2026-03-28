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
$isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';

$stmt = $conn->prepare("
    SELECT id, fiscal_year, province, municipality, barangay, puroks, project_names, project_classifications, lawa_target, binhi_target, capbuild_target, community_action_plan_target, target_partner_beneficiaries, updated_at
    FROM project_lawa_binhi_targets
    WHERE fiscal_year = ?
    ORDER BY province ASC, municipality ASC, barangay ASC
");
$stmt->bind_param('i', $selectedYear);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $actions = '<span class="text-muted">View only</span>';
    if ($isAdmin) {
        $actions = '<span class="kodus-row-actions"><button type="button" class="btn btn-sm btn-primary edit-target-btn" data-id="' . (int) $row['id'] . '" title="Edit" aria-label="Edit"><i class="nav-icon fas fa-pen"></i></button>'
            . '<button type="button" class="btn btn-sm btn-danger delete-target-btn" data-id="' . (int) $row['id'] . '" data-location="' . htmlspecialchars($row['barangay'] . ', ' . $row['municipality'], ENT_QUOTES, 'UTF-8') . '" title="Delete" aria-label="Delete"><i class="nav-icon fas fa-trash"></i></button>';
        $actions .= '</span>';
    }

    $data[] = [
        'id' => (int) $row['id'],
        'fiscal_year' => (int) $row['fiscal_year'],
        'province' => $row['province'],
        'municipality' => $row['municipality'],
        'barangay' => $row['barangay'],
        'puroks' => parseProjectTargetMultiValueCell($row['puroks'] ?? ''),
        'puroks_display' => implode(', ', parseProjectTargetMultiValueCell($row['puroks'] ?? '')),
        'project_names' => parseProjectTargetMultiValueCell($row['project_names'] ?? '', false),
        'project_classifications' => parseProjectTargetMultiValueCell($row['project_classifications'] ?? ''),
        'lawa_target' => (int) ($row['lawa_target'] ?? 0),
        'binhi_target' => (int) ($row['binhi_target'] ?? 0),
        'capbuild_target' => (int) ($row['capbuild_target'] ?? 0),
        'community_action_plan_target' => (int) ($row['community_action_plan_target'] ?? 0),
        'project_names_display' => implode(', ', parseProjectTargetMultiValueCell($row['project_names'] ?? '', false)),
        'project_classifications_display' => implode(', ', parseProjectTargetMultiValueCell($row['project_classifications'] ?? '')),
        'target_partner_beneficiaries' => (int) $row['target_partner_beneficiaries'],
        'updated_at' => !empty($row['updated_at']) ? date('M d, Y h:i A', strtotime($row['updated_at'])) : '',
        'action' => $actions,
    ];
}
$stmt->close();

echo json_encode(['data' => $data]);
