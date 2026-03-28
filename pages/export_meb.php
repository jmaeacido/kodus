<?php
require '../vendor/autoload.php';
include('../config.php');
require_once __DIR__ . '/../export_style_helpers.php';

session_start();

// Ensure fiscal year is selected
if (!isset($_SESSION['selected_year'])) {
    echo "<p style='color: red;'>Fiscal year not selected. Please go back and select.</p>";
    exit;
}

$year = (int) $_SESSION['selected_year'];

use PhpOffice\PhpSpreadsheet\Spreadsheet;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Master List');

// =====================================================
// TITLE
// =====================================================
$sheet->setCellValue('A1', 'Master List of Eligible Beneficiaries');
$sheet->mergeCells('A1:Z1');

$sheet->setCellValue('A2', 'Fiscal Year: ' . $year);
$sheet->mergeCells('A2:Z2');

// =====================================================
// HEADERS
// =====================================================
$headers = [
    'LAST NAME',
    'FIRST NAME',
    'MIDDLE NAME',
    'EXT.',
    'PUROK',
    'BARANGAY',
    'CITY / MUNICIPALITY',
    'PROVINCE',
    'BIRTHDATE',
    'AGE',
    'SEX',
    'CIVIL STATUS',
    'National Household Targeting System for Poverty Reduction (NHTS-PR) Poor',
    'National Household Targeting System for Poverty Reduction (NHTS-PR) Non-poor but considered poor by LSWDO assessment',
    'Pantawid Pamilyang Pilipino Program (4Ps)',
    'Farmers (F)',
    'Fisherfolks (FF)',
    'Indigenous People (IP)',
    'Senior Citizen (SC)',
    'Solo Parent (SP)',
    'Pregnant Women (PW)',
    'Persons with Disability (PWD)',
    'Out-of-School Youth (OSY)',
    'Former Rebel (FR)',
    'YAKAP Bayan/Drug Surenderee (YB/DS)',
    'LGBTQIA+'
];

$sheet->fromArray([$headers], null, 'A3');

// =====================================================
// COLUMN WIDTHS
// =====================================================
$sheet->getColumnDimension('A')->setWidth(18);
$sheet->getColumnDimension('B')->setWidth(18);
$sheet->getColumnDimension('C')->setWidth(18);
$sheet->getColumnDimension('D')->setWidth(8);
$sheet->getColumnDimension('E')->setWidth(12);
$sheet->getColumnDimension('F')->setWidth(18);
$sheet->getColumnDimension('G')->setWidth(22);
$sheet->getColumnDimension('H')->setWidth(18);
$sheet->getColumnDimension('I')->setWidth(14);
$sheet->getColumnDimension('J')->setWidth(8);
$sheet->getColumnDimension('K')->setWidth(10);
$sheet->getColumnDimension('L')->setWidth(14);
$sheet->getColumnDimension('M')->setWidth(24);
$sheet->getColumnDimension('N')->setWidth(28);
$sheet->getColumnDimension('O')->setWidth(18);
$sheet->getColumnDimension('P')->setWidth(12);
$sheet->getColumnDimension('Q')->setWidth(14);
$sheet->getColumnDimension('R')->setWidth(14);
$sheet->getColumnDimension('S')->setWidth(14);
$sheet->getColumnDimension('T')->setWidth(14);
$sheet->getColumnDimension('U')->setWidth(14);
$sheet->getColumnDimension('V')->setWidth(20);
$sheet->getColumnDimension('W')->setWidth(18);
$sheet->getColumnDimension('X')->setWidth(14);
$sheet->getColumnDimension('Y')->setWidth(22);
$sheet->getColumnDimension('Z')->setWidth(12);

// =====================================================
// QUERY DATA
// =====================================================
$query = "
    SELECT 
        lastName, 
        firstName, 
        middleName, 
        ext, 
        purok, 
        barangay, 
        lgu, 
        province, 
        birthDate, 
        age, 
        sex, 
        civilStatus, 
        nhts1, 
        nhts2, 
        fourPs, 
        F, 
        FF, 
        IP, 
        SC, 
        SP, 
        PW, 
        PWD, 
        OSY, 
        FR, 
        ybDs, 
        lgbtqia
    FROM meb
    WHERE YEAR(time_stamp) = $year
    ORDER BY province, lgu
";

if ($result = mysqli_query($conn, $query)) {
    $rowIndex = 4;

    while ($row = mysqli_fetch_assoc($result)) {
        $sheet->fromArray(
            array_values(array_map(fn($v) => $v ?? '', $row)),
            null,
            'A' . $rowIndex
        );
        $rowIndex++;
    }
} else {
    die('Query failed: ' . mysqli_error($conn));
}

$lastDataRow = $rowIndex - 1;

kodus_export_apply_uniform_style($spreadsheet, $sheet, [
    'document_title' => 'Master List of Eligible Beneficiaries',
    'title_range' => 'A1:Z2',
    'header_range' => 'A3:Z3',
    'data_range' => $lastDataRow >= 4 ? "A4:Z{$lastDataRow}" : null,
    'freeze_pane' => 'A4',
    'auto_filter' => 'A3:Z3',
    'left_align_ranges' => $lastDataRow >= 4 ? ["A4:H{$lastDataRow}", "L4:L{$lastDataRow}"] : [],
    'row_heights' => [1 => 30, 2 => 25, 3 => 55],
    'column_widths' => [
        'A' => 18, 'B' => 18, 'C' => 18, 'D' => 8, 'E' => 12, 'F' => 18, 'G' => 22, 'H' => 18,
        'I' => 14, 'J' => 8, 'K' => 10, 'L' => 14, 'M' => 24, 'N' => 28, 'O' => 18, 'P' => 12,
        'Q' => 14, 'R' => 14, 'S' => 14, 'T' => 14, 'U' => 14, 'V' => 20, 'W' => 18, 'X' => 14,
        'Y' => 22, 'Z' => 12,
    ],
    'integer_ranges' => $lastDataRow >= 4 ? ["J4:J{$lastDataRow}"] : [],
]);

kodus_export_stream_xlsx($spreadsheet, 'Master_list_of_Eligible_Beneficiaries_' . $year . '.xlsx');
?>
