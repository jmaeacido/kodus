<?php
require_once '../security.php';
security_bootstrap_session();
require_once '../auth_helpers.php';
include('../config.php');
require_once '../project_targets_helpers.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
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
        'PROJECT CLASSIFICATION',
        'LAWA TARGET',
        'BINHI TARGET',
        'TARGET PARTNER-BENEFICIARIES',
    ];

    foreach ($requiredHeaders as $requiredHeader) {
        if (!isset($normalizedHeaders[$requiredHeader])) {
            throw new RuntimeException('Column mismatch. Expected headers: PROVINCE, MUNICIPALITY, BARANGAY, PUROK, PROJECT NAME, PROJECT CLASSIFICATION, LAWA TARGET, BINHI TARGET, TARGET PARTNER-BENEFICIARIES.');
        }
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

    $importedRows = 0;
    foreach ($rows as $row) {
        $province = normalizeProjectTargetLocation((string) ($row[$normalizedHeaders['PROVINCE']] ?? ''));
        $municipality = normalizeProjectTargetLocation((string) ($row[$normalizedHeaders['MUNICIPALITY']] ?? ''));
        $barangay = normalizeProjectTargetLocation((string) ($row[$normalizedHeaders['BARANGAY']] ?? ''));
        $puroks = parseProjectTargetMultiValueCell((string) ($row[$normalizedHeaders['PUROK']] ?? ''));
        $projectNames = parseProjectTargetMultiValueCell((string) ($row[$normalizedHeaders['PROJECT NAME']] ?? ''), false);
        $projectClassifications = parseProjectTargetMultiValueCell((string) ($row[$normalizedHeaders['PROJECT CLASSIFICATION']] ?? ''));
        $lawaTargetRaw = trim((string) ($row[$normalizedHeaders['LAWA TARGET']] ?? ''));
        $binhiTargetRaw = trim((string) ($row[$normalizedHeaders['BINHI TARGET']] ?? ''));
        $targetRaw = trim((string) ($row[$normalizedHeaders['TARGET PARTNER-BENEFICIARIES']] ?? ''));

        if ($province === '' && $municipality === '' && $barangay === '' && empty($puroks) && empty($projectNames) && empty($projectClassifications) && $lawaTargetRaw === '' && $binhiTargetRaw === '' && $targetRaw === '') {
            continue;
        }

        if (
            $province === '' ||
            $municipality === '' ||
            $barangay === '' ||
            $lawaTargetRaw === '' ||
            $binhiTargetRaw === '' ||
            !is_numeric(str_replace(',', '', $lawaTargetRaw)) ||
            !is_numeric(str_replace(',', '', $binhiTargetRaw))
        ) {
            throw new RuntimeException('Every row must include province, municipality, barangay, and numeric LAWA and BINHI target counts.');
        }

        $lawaTarget = (int) str_replace(',', '', $lawaTargetRaw);
        $binhiTarget = (int) str_replace(',', '', $binhiTargetRaw);
        if ($lawaTarget < 0 || $binhiTarget < 0) {
            throw new RuntimeException('LAWA and BINHI target counts cannot be negative.');
        }
        $computedTargetBeneficiaries = $lawaTarget + $binhiTarget;
        if ($targetRaw !== '' && is_numeric(str_replace(',', '', $targetRaw))) {
            $importedTotal = (int) str_replace(',', '', $targetRaw);
            if ($importedTotal !== $computedTargetBeneficiaries) {
                throw new RuntimeException('Target Partner-Beneficiaries must equal the sum of LAWA Target and BINHI Target.');
            }
        }
        $targetBeneficiaries = $computedTargetBeneficiaries;

        if (count($puroks) !== count($projectNames) || count($projectNames) !== count($projectClassifications)) {
            throw new RuntimeException('Purok, Project Name, and Project Classification must have the same number of linked entries per row.');
        }

        foreach ($projectClassifications as $classification) {
            if (!in_array($classification, ['LAWA', 'BINHI'], true)) {
                throw new RuntimeException('Project Classification values must be LAWA or BINHI.');
            }
        }

        $puroksValue = implode('||', $puroks);
        $projectNamesValue = implode('||', $projectNames);
        $projectClassificationsValue = implode('||', $projectClassifications);

        $stmt->bind_param('issssssiii', $selectedYear, $province, $municipality, $barangay, $puroksValue, $projectNamesValue, $projectClassificationsValue, $lawaTarget, $binhiTarget, $targetBeneficiaries);
        $stmt->execute();
        $importedRows++;
    }

    $stmt->close();

    if ($importedRows === 0) {
        throw new RuntimeException('No target rows were imported.');
    }

    $_SESSION['target_import_success'] = "Imported {$importedRows} baseline target row(s) for fiscal year {$selectedYear}.";
} catch (Throwable $error) {
    $_SESSION['target_import_error'] = $error->getMessage();
}

header('Location: program-targets');
exit;
