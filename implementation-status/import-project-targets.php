<?php
require_once '../security.php';
security_bootstrap_session();
require_once '../auth_helpers.php';
include('../config.php');
require_once '../project_targets_helpers.php';
require_once '../socket_helpers.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();

if (!auth_can_manage_program_targets()) {
    $_SESSION['target_import_error'] = 'Access denied.';
    header('Location: program-targets');
    exit;
}

if (!isset($_SESSION['selected_year'])) {
    $_SESSION['target_import_error'] = 'Fiscal year not selected.';
    header('Location: program-targets');
    exit;
}

ensureProjectLawaBinhiTargets($conn);

$selectedYear = (int) $_SESSION['selected_year'];

if (!isset($_FILES['targetFile']) || $_FILES['targetFile']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['target_import_error'] = 'Please choose an Excel file to import.';
    header('Location: program-targets');
    exit;
}

$fileTmpPath = $_FILES['targetFile']['tmp_name'];
$fileName = $_FILES['targetFile']['name'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!in_array($fileExtension, ['xls', 'xlsx'], true)) {
    $_SESSION['target_import_error'] = 'Invalid file type. Please upload an Excel file.';
    header('Location: program-targets');
    exit;
}

try {
    $spreadsheet = IOFactory::load($fileTmpPath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);

    if (count($rows) < 2) {
        throw new RuntimeException('The Excel file does not contain any data rows.');
    }

    $headerRow = array_shift($rows);
    $normalizedHeaders = [];
    foreach ($headerRow as $columnKey => $headerValue) {
        $normalized = strtoupper(trim((string) $headerValue));
        $normalizedHeaders[$normalized] = $columnKey;
    }

    $requiredHeaders = [
        'PROVINCE',
        'MUNICIPALITY',
        'BARANGAY',
        'PUROK',
        'PROJECT NAME',
        'PROJECT TYPE',
        'PROJECT CLASSIFICATION',
        'LAWA TARGET',
        'BINHI TARGET',
        'CAPBUILD TARGET',
        'COMMUNITY ACTION PLAN TARGET',
        'TARGET PARTNER-BENEFICIARIES',
    ];

    $optionalBinhiTypeHeaders = [
        'BINHI VEGETABLE TARGET',
        'BINHI CROPS TARGET',
        'BINHI DISASTER RESILIENT CROPS TARGET',
        'BINHI FRUIT-BEARING TREES TARGET',
        'BINHI TILAPIA TARGET',
    ];

    foreach ($requiredHeaders as $requiredHeader) {
        if (!isset($normalizedHeaders[$requiredHeader])) {
            throw new RuntimeException('Column mismatch. Expected headers: PROVINCE, MUNICIPALITY, BARANGAY, PUROK, PROJECT NAME, PROJECT TYPE, PROJECT CLASSIFICATION, LAWA TARGET, BINHI TARGET, CAPBUILD TARGET, COMMUNITY ACTION PLAN TARGET, TARGET PARTNER-BENEFICIARIES.');
        }
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
    $targetLookupStmt = $conn->prepare("
        SELECT id
        FROM project_lawa_binhi_targets
        WHERE fiscal_year = ?
          AND province = ?
          AND municipality = ?
          AND barangay = ?
        LIMIT 1
    ");

    $importedRows = 0;
    foreach ($rows as $row) {
        $province = normalizeProjectTargetLocation((string) ($row[$normalizedHeaders['PROVINCE']] ?? ''));
        $municipality = normalizeProjectTargetLocation((string) ($row[$normalizedHeaders['MUNICIPALITY']] ?? ''));
        $barangay = normalizeProjectTargetLocation((string) ($row[$normalizedHeaders['BARANGAY']] ?? ''));
        $puroks = parseProjectTargetMultiValueCell((string) ($row[$normalizedHeaders['PUROK']] ?? ''));
        $projectNames = parseProjectTargetMultiValueCell((string) ($row[$normalizedHeaders['PROJECT NAME']] ?? ''), false);
        $projectTypes = parseProjectTargetMultiValueCell((string) ($row[$normalizedHeaders['PROJECT TYPE']] ?? ''), false);
        $projectClassifications = parseProjectTargetMultiValueCell((string) ($row[$normalizedHeaders['PROJECT CLASSIFICATION']] ?? ''));
        $lawaTargetRaw = trim((string) ($row[$normalizedHeaders['LAWA TARGET']] ?? ''));
        $binhiTargetRaw = trim((string) ($row[$normalizedHeaders['BINHI TARGET']] ?? ''));
        $capbuildTargetRaw = trim((string) ($row[$normalizedHeaders['CAPBUILD TARGET']] ?? ''));
        $communityActionPlanTargetRaw = trim((string) ($row[$normalizedHeaders['COMMUNITY ACTION PLAN TARGET']] ?? ''));
        $targetRaw = trim((string) ($row[$normalizedHeaders['TARGET PARTNER-BENEFICIARIES']] ?? ''));
        $binhiVegetableTargetRaw = isset($normalizedHeaders['BINHI VEGETABLE TARGET']) ? trim((string) ($row[$normalizedHeaders['BINHI VEGETABLE TARGET']] ?? '')) : '0';
        $binhiCropsTargetRaw = isset($normalizedHeaders['BINHI CROPS TARGET']) ? trim((string) ($row[$normalizedHeaders['BINHI CROPS TARGET']] ?? '')) : '0';
        $binhiDisasterResilientCropsTargetRaw = isset($normalizedHeaders['BINHI DISASTER RESILIENT CROPS TARGET']) ? trim((string) ($row[$normalizedHeaders['BINHI DISASTER RESILIENT CROPS TARGET']] ?? '')) : '0';
        $binhiFruitBearingTreesTargetRaw = isset($normalizedHeaders['BINHI FRUIT-BEARING TREES TARGET']) ? trim((string) ($row[$normalizedHeaders['BINHI FRUIT-BEARING TREES TARGET']] ?? '')) : '0';
        $binhiTilapiaTargetRaw = isset($normalizedHeaders['BINHI TILAPIA TARGET']) ? trim((string) ($row[$normalizedHeaders['BINHI TILAPIA TARGET']] ?? '')) : '0';

        if ($province === '' && $municipality === '' && $barangay === '' && empty($puroks) && empty($projectNames) && empty($projectTypes) && empty($projectClassifications) && $lawaTargetRaw === '' && $binhiTargetRaw === '' && $capbuildTargetRaw === '' && $communityActionPlanTargetRaw === '' && $targetRaw === '') {
            continue;
        }

        if (
            $province === '' ||
            $municipality === '' ||
            $barangay === '' ||
            $lawaTargetRaw === '' ||
            $binhiTargetRaw === '' ||
            $capbuildTargetRaw === '' ||
            $communityActionPlanTargetRaw === '' ||
            !is_numeric(str_replace(',', '', $lawaTargetRaw)) ||
            !is_numeric(str_replace(',', '', $binhiTargetRaw)) ||
            !is_numeric(str_replace(',', '', $capbuildTargetRaw)) ||
            !is_numeric(str_replace(',', '', $communityActionPlanTargetRaw))
        ) {
            throw new RuntimeException('Every row must include province, municipality, barangay, and numeric LAWA, BINHI, CapBuild, and Community action plan target counts.');
        }

        if (!auth_can_edit_implementation_province($conn, $province)) {
            throw new RuntimeException('Editors can only import implementation targets for their assigned province.');
        }

        $lawaTarget = (int) str_replace(',', '', $lawaTargetRaw);
        $binhiTarget = (int) str_replace(',', '', $binhiTargetRaw);
        $binhiVegetableTarget = $binhiVegetableTargetRaw !== '' && is_numeric(str_replace(',', '', $binhiVegetableTargetRaw)) ? (int) str_replace(',', '', $binhiVegetableTargetRaw) : 0;
        $binhiCropsTarget = $binhiCropsTargetRaw !== '' && is_numeric(str_replace(',', '', $binhiCropsTargetRaw)) ? (int) str_replace(',', '', $binhiCropsTargetRaw) : 0;
        $binhiDisasterResilientCropsTarget = $binhiDisasterResilientCropsTargetRaw !== '' && is_numeric(str_replace(',', '', $binhiDisasterResilientCropsTargetRaw)) ? (int) str_replace(',', '', $binhiDisasterResilientCropsTargetRaw) : 0;
        $binhiFruitBearingTreesTarget = $binhiFruitBearingTreesTargetRaw !== '' && is_numeric(str_replace(',', '', $binhiFruitBearingTreesTargetRaw)) ? (int) str_replace(',', '', $binhiFruitBearingTreesTargetRaw) : 0;
        $binhiTilapiaTarget = $binhiTilapiaTargetRaw !== '' && is_numeric(str_replace(',', '', $binhiTilapiaTargetRaw)) ? (int) str_replace(',', '', $binhiTilapiaTargetRaw) : 0;
        $capbuildTarget = (int) str_replace(',', '', $capbuildTargetRaw);
        $communityActionPlanTarget = (int) str_replace(',', '', $communityActionPlanTargetRaw);
        if ($lawaTarget < 0 || $binhiTarget < 0 || $binhiVegetableTarget < 0 || $binhiCropsTarget < 0 || $binhiDisasterResilientCropsTarget < 0 || $binhiFruitBearingTreesTarget < 0 || $binhiTilapiaTarget < 0 || $capbuildTarget < 0 || $communityActionPlanTarget < 0) {
            throw new RuntimeException('LAWA, BINHI, CapBuild, and Community action plan target counts cannot be negative.');
        }
        if ($targetRaw === '' || !is_numeric(str_replace(',', '', $targetRaw))) {
            throw new RuntimeException('Every row must include a numeric Target Partner-Beneficiaries value for the barangay.');
        }
        $targetBeneficiaries = (int) str_replace(',', '', $targetRaw);
        if ($targetBeneficiaries < 0) {
            throw new RuntimeException('Target Partner-Beneficiaries cannot be negative.');
        }

        if (count($puroks) !== count($projectNames) || count($projectNames) !== count($projectTypes) || count($projectTypes) !== count($projectClassifications)) {
            throw new RuntimeException('Purok, Project Name, Project Type, and Project Classification must have the same number of linked entries per row.');
        }

        foreach ($projectClassifications as $classification) {
            if (!in_array($classification, ['LAWA', 'BINHI'], true)) {
                throw new RuntimeException('Project Classification values must be LAWA or BINHI.');
            }
        }

        $targetEntries = [];
        foreach ($projectNames as $index => $projectName) {
            $targetEntries[] = [
                'row_id' => 'import-' . $selectedYear . '-' . md5($province . '|' . $municipality . '|' . $barangay . '|' . $index . '|' . $projectName),
                'sort_order' => $index,
                'purok' => (string) ($puroks[$index] ?? ''),
                'project_name' => (string) $projectName,
                'project_type' => (string) ($projectTypes[$index] ?? ''),
                'project_classification' => (string) ($projectClassifications[$index] ?? ''),
                'fertilizer_enabled' => 0,
                'fertilizer_ohn_target' => null,
                'fertilizer_concoction_target' => null,
                'fertilizer_vermicompost_target' => null,
                'binhi_target_quantity' => null,
                'aquatic_resource' => null,
                'aquatic_resource_quantity' => null,
            ];
        }

        $stmt->bind_param('isssiiiiiiiiii', $selectedYear, $province, $municipality, $barangay, $lawaTarget, $binhiTarget, $binhiVegetableTarget, $binhiCropsTarget, $binhiDisasterResilientCropsTarget, $binhiFruitBearingTreesTarget, $binhiTilapiaTarget, $capbuildTarget, $communityActionPlanTarget, $targetBeneficiaries);
        $stmt->execute();
        $targetLookupStmt->bind_param('isss', $selectedYear, $province, $municipality, $barangay);
        $targetLookupStmt->execute();
        $targetRecord = db_stmt_fetch_one_assoc($targetLookupStmt) ?: [];
        if ($targetRecord !== []) {
            projectTargetsReplaceEntries($conn, (int) ($targetRecord['id'] ?? 0), $targetEntries);
        }
        $importedRows++;
    }

    $stmt->close();
    $targetLookupStmt->close();

    if ($importedRows === 0) {
        throw new RuntimeException('No target rows were imported.');
    }

    kodus_socket_broadcast('kodus.meb', 'meb.validation.changed', [
        'action' => 'targets_imported',
        'row_count' => $importedRows,
        'fiscal_year' => $selectedYear,
    ]);

    $_SESSION['target_import_success'] = "Imported {$importedRows} baseline target row(s) for fiscal year {$selectedYear}.";
} catch (Throwable $error) {
    $_SESSION['target_import_error'] = $error->getMessage();
}

header('Location: program-targets');
exit;
