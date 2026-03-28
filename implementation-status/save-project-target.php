<?php
require_once '../security.php';
security_bootstrap_session();
require_once '../auth_helpers.php';
include('../config.php');
require_once '../project_targets_helpers.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();
header('Content-Type: application/json');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

if (!isset($_SESSION['selected_year'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Fiscal year not selected.']);
    exit;
}

ensureProjectLawaBinhiTargets($conn);

$selectedYear = (int) $_SESSION['selected_year'];
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$province = normalizeProjectTargetLocation((string) ($_POST['province'] ?? ''));
$municipality = normalizeProjectTargetLocation((string) ($_POST['municipality'] ?? ''));
$barangay = normalizeProjectTargetLocation((string) ($_POST['barangay'] ?? ''));
$entriesInput = $_POST['entries'] ?? [];
$lawaTarget = isset($_POST['lawa_target']) ? (int) $_POST['lawa_target'] : -1;
$binhiTarget = isset($_POST['binhi_target']) ? (int) $_POST['binhi_target'] : -1;

$puroks = [];
$projects = [];
if (is_array($entriesInput)) {
    foreach ($entriesInput as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $purok = normalizeProjectTargetLocation((string) ($entry['purok'] ?? ''));
        $projectName = trim((string) ($entry['name'] ?? ''));
        $classification = normalizeProjectTargetLocation((string) ($entry['classification'] ?? ''));

        if ($purok === '' && $projectName === '' && $classification === '') {
            continue;
        }

        if ($purok === '' || $projectName === '' || $classification === '' || !in_array($classification, ['LAWA', 'BINHI'], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Each row must include a purok, project name, and classification of LAWA or BINHI.']);
            exit;
        }

        $puroks[] = $purok;
        $projects[] = [
            'name' => preg_replace('/\s+/', ' ', $projectName),
            'classification' => $classification,
        ];
    }
}

$projectNames = implode('||', array_map(static fn($project) => $project['name'], $projects));
$projectClassifications = implode('||', array_map(static fn($project) => $project['classification'], $projects));
$puroksValue = implode('||', $puroks);
$targetBeneficiaries = $lawaTarget + $binhiTarget;

if ($province === '' || $municipality === '' || $barangay === '' || $lawaTarget < 0 || $binhiTarget < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please complete all fields with valid LAWA and BINHI target counts.']);
    exit;
}

if ($id > 0) {
    $stmt = $conn->prepare("
        UPDATE project_lawa_binhi_targets
        SET province = ?, municipality = ?, barangay = ?, puroks = ?, project_names = ?, project_classifications = ?, lawa_target = ?, binhi_target = ?, target_partner_beneficiaries = ?
        WHERE id = ? AND fiscal_year = ?
    ");
    $stmt->bind_param('ssssssiiiii', $province, $municipality, $barangay, $puroksValue, $projectNames, $projectClassifications, $lawaTarget, $binhiTarget, $targetBeneficiaries, $id, $selectedYear);
    $stmt->execute();

    if ($stmt->errno) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Could not update target. Please check for duplicate locations.']);
        $stmt->close();
        exit;
    }

    $updated = $stmt->affected_rows >= 0;
    $stmt->close();

    echo json_encode(['success' => $updated, 'message' => 'Baseline target updated successfully.']);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO project_lawa_binhi_targets (fiscal_year, province, municipality, barangay, puroks, project_names, project_classifications, lawa_target, binhi_target, target_partner_beneficiaries)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        puroks = VALUES(puroks),
        project_names = VALUES(project_names),
        project_classifications = VALUES(project_classifications),
        lawa_target = VALUES(lawa_target),
        binhi_target = VALUES(binhi_target),
        target_partner_beneficiaries = VALUES(target_partner_beneficiaries),
        updated_at = CURRENT_TIMESTAMP
");
$stmt->bind_param('issssssiii', $selectedYear, $province, $municipality, $barangay, $puroksValue, $projectNames, $projectClassifications, $lawaTarget, $binhiTarget, $targetBeneficiaries);
$stmt->execute();

if ($stmt->errno) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Could not save target.']);
    $stmt->close();
    exit;
}

$stmt->close();

echo json_encode(['success' => true, 'message' => 'Baseline target saved successfully.']);
