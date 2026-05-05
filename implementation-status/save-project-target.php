<?php
require_once '../security.php';
security_bootstrap_session();
require_once '../auth_helpers.php';
include('../config.php');
require_once '../project_targets_helpers.php';
require_once '../socket_helpers.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();
header('Content-Type: application/json');

if (!auth_can_manage_program_targets()) {
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

function generateProjectRowId(): string
{
    try {
        return 'pt-' . bin2hex(random_bytes(8));
    } catch (Throwable $exception) {
        return 'pt-' . uniqid('', true);
    }
}

$validLawaTypes = [
    'Rehabilitation of Water System Level I (Manual Pump)',
    'Rehabilitation of Water System Level II (Pipe Laying)',
    'Construction of Small Farm Reservoir',
    'Rehabilitation of Water System',
    'Diversification of Water Supply',
    'Rehabilitation of Fishpond',
    'Installation of Shallow Tube Wells (STWs)',
    'Construction of Water Reservoir',
    'Rehabilitation of Small Farm Reservoir',
    'Installation of Pitcher Pump (Shallow Well)',
    'Installation of Jetmatic Pump (Deep Well)',
    'Rehabilitation of Water Supply',
];
$validBinhiTypes = [
    'Vegetable',
    'Crops (Banana, Corn, Rice)',
    'Disaster Resilient Crops (Taro, Sweet Potato)',
    'Fruit-Bearing Trees',
    'Tilapia (Fish pond)',
];

$selectedYear = (int) $_SESSION['selected_year'];
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$province = normalizeProjectTargetLocation((string) ($_POST['province'] ?? ''));
$municipality = normalizeProjectTargetLocation((string) ($_POST['municipality'] ?? ''));
$barangay = normalizeProjectTargetLocation((string) ($_POST['barangay'] ?? ''));
$entriesInput = $_POST['entries'] ?? [];
$lawaTarget = 0;
$binhiTarget = 0;
$binhiVegetableTarget = 0;
$binhiCropsTarget = 0;
$binhiDisasterResilientCropsTarget = 0;
$binhiFruitBearingTreesTarget = 0;
$binhiTilapiaTarget = 0;
$capbuildTarget = isset($_POST['capbuild_target']) ? (int) $_POST['capbuild_target'] : -1;
$communityActionPlanTarget = isset($_POST['community_action_plan_target']) ? (int) $_POST['community_action_plan_target'] : -1;
$targetBeneficiaries = isset($_POST['target_partner_beneficiaries']) ? (int) $_POST['target_partner_beneficiaries'] : -1;

$puroks = [];
$projectRowIds = [];
$projects = [];
$projectTypes = [];
$fertilizerEnabledFlags = [];
$fertilizerOhnTargets = [];
$fertilizerConcoctionTargets = [];
$fertilizerVermicompostTargets = [];
$binhiTargetQuantities = [];
$aquaticResources = [];
$aquaticResourceQuantities = [];
$aquaticProjectTypes = [
    'Construction of Small Farm Reservoir',
    'Rehabilitation of Fishpond',
    'Rehabilitation of Small Farm Reservoir',
];
if (is_array($entriesInput)) {
    foreach ($entriesInput as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $purok = normalizeProjectTargetLocation((string) ($entry['purok'] ?? ''));
        $projectRowId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($entry['row_id'] ?? ''));
        $projectName = trim((string) ($entry['name'] ?? ''));
        $projectType = trim((string) ($entry['type'] ?? ''));
        $classification = normalizeProjectTargetLocation((string) ($entry['classification'] ?? ''));
        $fertilizerEnabled = trim((string) ($entry['fertilizer_enabled'] ?? ''));
        $fertilizerOhnTargetRaw = trim((string) ($entry['fertilizer_ohn_target'] ?? ''));
        $fertilizerConcoctionTargetRaw = trim((string) ($entry['fertilizer_concoction_target'] ?? ''));
        $fertilizerVermicompostTargetRaw = trim((string) ($entry['fertilizer_vermicompost_target'] ?? ''));
        $binhiTargetQuantityRaw = trim((string) ($entry['binhi_target_quantity'] ?? ''));
        $aquaticResource = trim((string) ($entry['aquatic_resource'] ?? ''));
        $aquaticResourceQuantity = trim((string) ($entry['aquatic_resource_quantity'] ?? ''));

        if ($purok === '' && $projectName === '' && $projectType === '' && $classification === '') {
            continue;
        }

        if ($purok === '' || $projectName === '' || $projectType === '' || $classification === '' || !in_array($classification, ['LAWA', 'BINHI'], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Each row must include a purok, project name, project type, and classification of LAWA or BINHI.']);
            exit;
        }

        $validTypes = $classification === 'LAWA' ? $validLawaTypes : $validBinhiTypes;
        if (!in_array($projectType, $validTypes, true)) {
            $projectType = preg_replace('/\s+/', ' ', $projectType);
            if ($projectType === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Each project row needs a valid project type or a custom type value.']);
                exit;
            }
        }

        if ($classification !== 'BINHI') {
            $fertilizerEnabled = '';
            $fertilizerOhnTargetRaw = '';
            $fertilizerConcoctionTargetRaw = '';
            $fertilizerVermicompostTargetRaw = '';
        } elseif ($fertilizerEnabled !== '1') {
            $fertilizerEnabled = $fertilizerEnabled === '0' ? '0' : '';
        }

        foreach ([
            'Oriental Herbal Nutrients target' => $fertilizerOhnTargetRaw,
            'Concoction/Vermitea target' => $fertilizerConcoctionTargetRaw,
            'Vermicompost/Vermicast target' => $fertilizerVermicompostTargetRaw,
        ] as $label => $value) {
            if ($value !== '' && !preg_match('/^\d+(?:\.\d+)?$/', $value)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $label . ' must be a non-negative number when provided.']);
                exit;
            }
        }

        if ($classification === 'BINHI' && $fertilizerEnabled === '1' && $fertilizerOhnTargetRaw === '' && $fertilizerConcoctionTargetRaw === '' && $fertilizerVermicompostTargetRaw === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please enter at least one fertilizer target when the project produces or reproduces fertilizers.']);
            exit;
        }

        if ($projectRowId === '') {
            $projectRowId = generateProjectRowId();
        }

        if ($classification !== 'BINHI' || $fertilizerEnabled !== '1') {
            $fertilizerOhnTargetRaw = '';
            $fertilizerConcoctionTargetRaw = '';
            $fertilizerVermicompostTargetRaw = '';
        }

        $binhiTargetQuantity = 0;
        if ($classification === 'BINHI') {
            if ($binhiTargetQuantityRaw !== '' && !preg_match('/^\d+$/', $binhiTargetQuantityRaw)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'BINHI target quantity must be a whole number when provided.']);
                exit;
            }
            $binhiTargetQuantity = $binhiTargetQuantityRaw === '' ? 0 : (int) $binhiTargetQuantityRaw;
        }

        $hasAquaticResourceInput = $aquaticResource !== '' || $aquaticResourceQuantity !== '';
        if ($hasAquaticResourceInput) {
            $normalizedAquaticResource = strtolower($aquaticResource);
            if ($normalizedAquaticResource === 'crabs' || $normalizedAquaticResource === 'fish') {
                $aquaticResource = $normalizedAquaticResource;
            } else {
                $aquaticResource = preg_replace('/\s+/', ' ', $aquaticResource);
            }

            if ($aquaticResource === '' || $aquaticResourceQuantity === '' || !preg_match('/^\d+$/', $aquaticResourceQuantity)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Aquatic resource and quantity must both be filled in when aquatic resources are included.']);
                exit;
            }
        } else {
            $aquaticResource = '';
            $aquaticResourceQuantity = '';
        }

        $puroks[] = $purok;
        $projectRowIds[] = $projectRowId;
        $projects[] = [
            'name' => preg_replace('/\s+/', ' ', $projectName),
            'classification' => $classification,
        ];
        $projectTypes[] = preg_replace('/\s+/', ' ', $projectType);
        $fertilizerEnabledFlags[] = $fertilizerEnabled;
        $fertilizerOhnTargets[] = $fertilizerOhnTargetRaw;
        $fertilizerConcoctionTargets[] = $fertilizerConcoctionTargetRaw;
        $fertilizerVermicompostTargets[] = $fertilizerVermicompostTargetRaw;
        $binhiTargetQuantities[] = $classification === 'BINHI' ? (string) $binhiTargetQuantity : '';
        $aquaticResources[] = $aquaticResource;
        $aquaticResourceQuantities[] = $aquaticResourceQuantity;

        if ($classification === 'LAWA') {
            $lawaTarget += 1;
        } elseif ($classification === 'BINHI') {
            $binhiTarget += 1;
            if ($projectType === 'Vegetable') {
                $binhiVegetableTarget += $binhiTargetQuantity;
            } elseif ($projectType === 'Crops (Banana, Corn, Rice)') {
                $binhiCropsTarget += $binhiTargetQuantity;
            } elseif ($projectType === 'Disaster Resilient Crops (Taro, Sweet Potato)') {
                $binhiDisasterResilientCropsTarget += $binhiTargetQuantity;
            } elseif ($projectType === 'Fruit-Bearing Trees') {
                $binhiFruitBearingTreesTarget += $binhiTargetQuantity;
            } elseif ($projectType === 'Tilapia (Fish pond)') {
                $binhiTilapiaTarget += $binhiTargetQuantity;
            }
        }
    }
}

$entryRows = projectTargetsBuildEntryRows(
    $projectRowIds,
    $puroks,
    $projects,
    $projectTypes,
    $fertilizerEnabledFlags,
    $fertilizerOhnTargets,
    $fertilizerConcoctionTargets,
    $fertilizerVermicompostTargets,
    $binhiTargetQuantities,
    $aquaticResources,
    $aquaticResourceQuantities
);

if (empty($projects)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please add at least one project entry before saving the baseline target.']);
    exit;
}

if (
    $province === '' ||
    $municipality === '' ||
    $barangay === '' ||
    $capbuildTarget < 0 ||
    $communityActionPlanTarget < 0 ||
    $targetBeneficiaries < 0
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please complete all fields with valid target counts, including the barangay target partner-beneficiaries.']);
    exit;
}

$locationStmt = $conn->prepare("
    SELECT 1
    FROM provinces p
    INNER JOIN municipality m
        ON m.province_id = p.id
    INNER JOIN barangay b
        ON b.municipality_id = m.id
    WHERE p.province_name = ?
      AND m.municipality_name = ?
      AND b.brgy_name = ?
    LIMIT 1
");
if (!$locationStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not validate the selected location.']);
    exit;
}
$locationStmt->bind_param('sss', $province, $municipality, $barangay);
$locationStmt->execute();
$validLocation = db_stmt_fetch_one_assoc($locationStmt);
$locationStmt->close();

if (!$validLocation) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please select a valid province, municipality, and barangay from the dropdowns.']);
    exit;
}

if (!auth_can_edit_implementation_province($conn, $province)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Editors can only edit implementation records in their assigned province.']);
    exit;
}

if ($id > 0) {
    $existingStmt = $conn->prepare('SELECT id, province FROM project_lawa_binhi_targets WHERE id = ? AND fiscal_year = ? LIMIT 1');
    $existingStmt->bind_param('ii', $id, $selectedYear);
    $existingStmt->execute();
    $existingTarget = db_stmt_fetch_one_assoc($existingStmt);
    $existingStmt->close();

    if (!$existingTarget) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'The selected baseline target could not be found for the active fiscal year.']);
        exit;
    }

    if (!auth_can_edit_implementation_province($conn, $existingTarget['province'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Editors can only edit implementation records in their assigned province.']);
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE project_lawa_binhi_targets
        SET province = ?, municipality = ?, barangay = ?, lawa_target = ?, binhi_target = ?, binhi_vegetable_target = ?, binhi_crops_target = ?, binhi_disaster_resilient_crops_target = ?, binhi_fruit_bearing_trees_target = ?, binhi_tilapia_target = ?, capbuild_target = ?, community_action_plan_target = ?, target_partner_beneficiaries = ?
        WHERE id = ? AND fiscal_year = ?
    ");
    $stmt->bind_param('sssiiiiiiiiiiii', $province, $municipality, $barangay, $lawaTarget, $binhiTarget, $binhiVegetableTarget, $binhiCropsTarget, $binhiDisasterResilientCropsTarget, $binhiFruitBearingTreesTarget, $binhiTilapiaTarget, $capbuildTarget, $communityActionPlanTarget, $targetBeneficiaries, $id, $selectedYear);
    $stmt->execute();

    if ($stmt->errno) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Could not update target. Please check for duplicate locations.']);
        $stmt->close();
        exit;
    }

    $updated = $stmt->affected_rows >= 0;
    $stmt->close();
    projectTargetsReplaceEntries($conn, $id, $entryRows);

    kodus_socket_broadcast('kodus.meb', 'meb.validation.changed', [
        'action' => 'target_updated',
        'target_id' => $id,
        'fiscal_year' => $selectedYear,
    ]);

    echo json_encode(['success' => $updated, 'message' => 'Baseline target updated successfully.']);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO project_lawa_binhi_targets (
        fiscal_year,
        province,
        municipality,
        barangay,
        lawa_target,
        binhi_target,
        binhi_vegetable_target,
        binhi_crops_target,
        binhi_disaster_resilient_crops_target,
        binhi_fruit_bearing_trees_target,
        binhi_tilapia_target,
        capbuild_target,
        community_action_plan_target,
        target_partner_beneficiaries
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        lawa_target = VALUES(lawa_target),
        binhi_target = VALUES(binhi_target),
        binhi_vegetable_target = VALUES(binhi_vegetable_target),
        binhi_crops_target = VALUES(binhi_crops_target),
        binhi_disaster_resilient_crops_target = VALUES(binhi_disaster_resilient_crops_target),
        binhi_fruit_bearing_trees_target = VALUES(binhi_fruit_bearing_trees_target),
        binhi_tilapia_target = VALUES(binhi_tilapia_target),
        capbuild_target = VALUES(capbuild_target),
        community_action_plan_target = VALUES(community_action_plan_target),
        target_partner_beneficiaries = VALUES(target_partner_beneficiaries),
        updated_at = CURRENT_TIMESTAMP
");
$stmt->bind_param('isssiiiiiiiiii', $selectedYear, $province, $municipality, $barangay, $lawaTarget, $binhiTarget, $binhiVegetableTarget, $binhiCropsTarget, $binhiDisasterResilientCropsTarget, $binhiFruitBearingTreesTarget, $binhiTilapiaTarget, $capbuildTarget, $communityActionPlanTarget, $targetBeneficiaries);
$stmt->execute();

if ($stmt->errno) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Could not save target.']);
    $stmt->close();
    exit;
}

$targetId = (int) $stmt->insert_id;
$stmt->close();

if ($targetId <= 0) {
    $lookupStmt = $conn->prepare("
        SELECT id
        FROM project_lawa_binhi_targets
        WHERE fiscal_year = ? AND province = ? AND municipality = ? AND barangay = ?
        LIMIT 1
    ");
    if ($lookupStmt) {
        $lookupStmt->bind_param('isss', $selectedYear, $province, $municipality, $barangay);
        $lookupStmt->execute();
        $targetId = (int) ((db_stmt_fetch_one_assoc($lookupStmt)['id'] ?? 0));
        $lookupStmt->close();
    }
}

if ($targetId > 0) {
    projectTargetsReplaceEntries($conn, $targetId, $entryRows);
}

kodus_socket_broadcast('kodus.meb', 'meb.validation.changed', [
    'action' => 'target_saved',
    'target_id' => $targetId,
    'fiscal_year' => $selectedYear,
]);

echo json_encode(['success' => true, 'message' => 'Baseline target saved successfully.']);
