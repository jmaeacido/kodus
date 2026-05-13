<?php
require '../vendor/autoload.php';
include('../config.php');
require_once __DIR__ . '/../export_style_helpers.php';
require_once __DIR__ . '/../project_targets_helpers.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

session_start();

if (!isset($_SESSION['selected_year'])) {
    echo "<p style='color: red;'>Fiscal year not selected. Please go back and select.</p>";
    exit;
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo "<p style='color: red;'>Access denied.</p>";
    exit;
}

$selectedYear = (int) $_SESSION['selected_year'];
ensureProjectLawaBinhiTargets($conn);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('MEB Validation');

$sheet->setCellValue('A1', 'MEB Validation Target vs Actual');
$sheet->mergeCells('A1:G1');
$sheet->setCellValue('A2', 'Fiscal Year: ' . $selectedYear);
$sheet->mergeCells('A2:G2');

$headers = [
    'PROVINCE',
    'MUNICIPALITY',
    'BARANGAY',
    'TARGET PARTNER-BENEFICIARIES',
    'IMPORTED PARTNER-BENEFICIARIES',
    'VARIANCE',
    'VALIDATION STATUS',
];
$sheet->fromArray([$headers], null, 'A3');

$comparisonSql = projectTargetsValidationComparisonSql();
$selectedYearStart = sprintf('%04d-01-01 00:00:00', $selectedYear);
$selectedYearEnd = sprintf('%04d-01-01 00:00:00', $selectedYear + 1);
$sql = "
    SELECT
        comparison.province,
        comparison.municipality,
        comparison.barangay,
        comparison.target_beneficiaries,
        comparison.actual_beneficiaries,
        comparison.variance
    FROM ({$comparisonSql}) AS comparison
    ORDER BY comparison.province ASC, comparison.municipality ASC, comparison.barangay ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iss', $selectedYear, $selectedYearStart, $selectedYearEnd);
$stmt->execute();
$result = db_stmt_fetch_all_assoc($stmt);

$rowIndex = 4;
foreach ($result as $row) {
    $targetBeneficiaries = (int) ($row['target_beneficiaries'] ?? 0);
    $actualBeneficiaries = (int) ($row['actual_beneficiaries'] ?? 0);

    if ($targetBeneficiaries <= 0 && $actualBeneficiaries > 0) {
        $status = 'Unplanned Import';
    } elseif ($targetBeneficiaries <= 0) {
        $status = 'No Target';
    } elseif ($actualBeneficiaries === 0) {
        $status = 'No Import';
    } elseif ($actualBeneficiaries < $targetBeneficiaries) {
        $status = 'Partial';
    } elseif ($actualBeneficiaries === $targetBeneficiaries) {
        $status = 'Validated';
    } else {
        $status = 'Over Target';
    }

    $sheet->fromArray([[
        $row['province'],
        $row['municipality'],
        $row['barangay'],
        $targetBeneficiaries,
        $actualBeneficiaries,
        (int) ($row['variance'] ?? 0),
        $status,
    ]], null, 'A' . $rowIndex);
    $rowIndex++;
}
$stmt->close();

$lastDataRow = $rowIndex - 1;
kodus_export_apply_uniform_style($spreadsheet, $sheet, [
    'document_title' => 'MEB Validation Target vs Actual',
    'title_range' => 'A1:G2',
    'header_range' => 'A3:G3',
    'data_range' => $lastDataRow >= 4 ? "A4:G{$lastDataRow}" : null,
    'freeze_pane' => 'A4',
    'auto_filter' => 'A3:G3',
    'left_align_ranges' => $lastDataRow >= 4 ? ["A4:C{$lastDataRow}", "G4:G{$lastDataRow}"] : [],
    'integer_ranges' => $lastDataRow >= 4 ? ["D4:F{$lastDataRow}"] : [],
    'row_heights' => [1 => 28, 2 => 22, 3 => 28],
    'auto_size_columns' => range('A', 'G'),
]);

kodus_export_stream_xlsx($spreadsheet, 'MEB_Validation_Target_vs_Actual_' . $selectedYear . '.xlsx');
