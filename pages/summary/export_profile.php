<?php
require '../../vendor/autoload.php';
include('../../config.php');
require_once __DIR__ . '/../../export_style_helpers.php';

session_start();

if (!isset($_SESSION['selected_year'])) {
    echo "<p style='color: red;'>Fiscal year not selected. Please go back and select a year.</p>";
    exit;
}

$year = (int) $_SESSION['selected_year'];

use PhpOffice\PhpSpreadsheet\Spreadsheet;

function export_profile_fourps_label($value): string
{
    $normalized = strtoupper(trim((string) $value));
    if ($normalized === 'M') {
        return 'M - Member';
    }
    if ($normalized === 'G') {
        return 'G - Graduated';
    }

    return in_array($normalized, ['✓', 'ÂŒ“', 'Ã¢Å“â€œ', 'YES', 'Y', 'TRUE', '1'], true) ? 'M - Member' : '';
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Beneficiaries Profile');

$sheet->setCellValue('A1', 'Partner-Beneficiaries Profile');
$sheet->mergeCells('A1:T1');
$sheet->setCellValue('A2', 'Fiscal Year: ' . $year);
$sheet->mergeCells('A2:T2');

$headers = [
    'ID', 'Name', 'Age', 'Sex', 'Listahanan 3 (P)', 'LSWDO Assessment (NON)',
    'Pantawid Pamilyang Pilipino Program (4Ps)', 'Farmers (F)', 'Fisher-folks (FF)',
    'Informal Sector (IS)', 'Indigenous People (IP)', 'Senior Citizen (SC)', 'Solo Parent (SP)',
    'Lactating Women (LW)', 'Pregnant Women (PW)', 'PWD', 'Out of School Youth (OSY)',
    'Former Rebel (FR)', 'YAKAP Bayan/ Person Who Used Drugs (YB/PWUD)', 'LGBTQIA+'
];

$sheet->fromArray([$headers], null, 'A3');

$pwdMap = [
    'A' => 'Multiple Disabilities',
    'B' => 'Intellectual Disability',
    'C' => 'Learning Disability',
    'D' => 'Mental Disability',
    'E' => 'Physical Disability (Orthopedic)',
    'F' => 'Psychosocial Disability',
    'G' => 'Non-apparent Visual Disability',
    'H' => 'Non-apparent Speech and Language Impairment',
    'I' => 'Non-apparent Cancer',
    'J' => 'Non-apparent Rare Disease',
    'K' => 'Deaf/Hard of Hearing Disability'
];

$query = "SELECT * FROM meb WHERE YEAR(time_stamp) = $year ORDER BY province ASC, lgu ASC, barangay ASC, lastName ASC, firstName ASC, middleName ASC, ext ASC";

if ($result = mysqli_query($conn, $query)) {
    $rowIndex = 4;

    while ($row = mysqli_fetch_assoc($result)) {
        $name = trim(
            ($row['firstName'] ?? '') . ' ' .
            (!empty($row['middleName']) ? strtoupper(substr($row['middleName'], 0, 1)) . '. ' : '') .
            ($row['lastName'] ?? '') .
            (!empty($row['ext']) ? ' ' . $row['ext'] : '')
        );

        $sheet->fromArray([
            $row['id'] ?? '',
            $name,
            $row['age'] ?? '',
            $row['sex'] ?? '',
            $row['nhts1'] ?? '',
            $row['nhts2'] ?? '',
            export_profile_fourps_label($row['fourPs'] ?? ''),
            $row['F'] ?? '',
            $row['FF'] ?? '',
            $row['IS'] ?? '',
            $row['IP'] ?? '',
            $row['SC'] ?? '',
            $row['SP'] ?? '',
            $row['LW'] ?? '',
            $row['PW'] ?? '',
            isset($pwdMap[$row['PWD']]) ? $pwdMap[$row['PWD']] : '',
            $row['OSY'] ?? '',
            $row['FR'] ?? '',
            $row['ybDs'] ?? '',
            $row['lgbtqia'] ?? ''
        ], null, 'A' . $rowIndex);

        $rowIndex++;
    }
} else {
    die('Query failed: ' . mysqli_error($conn));
}

$lastDataRow = $rowIndex - 1;

kodus_export_apply_uniform_style($spreadsheet, $sheet, [
    'document_title' => 'Partner-Beneficiaries Profile',
    'title_range' => 'A1:T2',
    'header_range' => 'A3:T3',
    'data_range' => $lastDataRow >= 4 ? "A4:T{$lastDataRow}" : null,
    'freeze_pane' => 'A4',
    'auto_filter' => 'A3:T3',
    'left_align_ranges' => $lastDataRow >= 4 ? ["B4:B{$lastDataRow}", "P4:P{$lastDataRow}"] : [],
    'row_heights' => [1 => 30, 2 => 25, 3 => 42],
    'column_widths' => [
        'A' => 10, 'B' => 28, 'C' => 10, 'D' => 10, 'E' => 16, 'F' => 18, 'G' => 18, 'H' => 12,
        'I' => 12, 'J' => 14, 'K' => 14, 'L' => 12, 'M' => 14, 'N' => 16, 'O' => 16, 'P' => 28,
        'Q' => 18, 'R' => 14, 'S' => 20, 'T' => 12,
    ],
    'integer_ranges' => $lastDataRow >= 4 ? ["A4:A{$lastDataRow}", "C4:C{$lastDataRow}"] : [],
]);

kodus_export_stream_xlsx($spreadsheet, 'Partner-Beneficiaries_Profile_' . $year . '.xlsx');
