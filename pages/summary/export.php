<?php
require '../../vendor/autoload.php';
include('../../config.php');
require_once __DIR__ . '/../../export_style_helpers.php';

session_start();

if (!isset($_SESSION['selected_year'])) {
    echo "<p style='color: red;'>Fiscal year not selected. Please go back and select.</p>";
    exit;
}

$year = (int) $_SESSION['selected_year'];
$checkMarkHex = 'E29C93';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Partner Beneficiaries');

$sheet->setCellValue('A1', 'Summary of Partner-Beneficiaries per Sector');
$sheet->mergeCells('A1:V1');
$sheet->setCellValue('A2', 'Fiscal Year: ' . $year);
$sheet->mergeCells('A2:V2');

$headers = [
    'Province', 'City or Municipality', 'No. of Partner-Beneficiaries', 'MALE', 'FEMALE',
    'Listahanan 3 (P)', 'LSWDO Assessment (NON)', '4Ps Member', '4Ps Graduated', 'Farmers (F)', 'Fisher-folks (FF)',
    'Informal Sector (IS)', 'Indigenous People (IP)', 'Senior Citizen (SC)', 'Solo Parent (SP)',
    'Lactating Women (LW)', 'Pregnant Women (PW)', 'Persons with Disability (PWD)',
    'Out of School Youth (OSY)', 'Former Rebel (FR)', 'YAKAP Bayan/ PWUD', 'LGBTQIA+'
];

$sheet->fromArray([$headers], null, 'A3');

$query = "
    SELECT
        province,
        lgu,
        COUNT(*) AS beneficiary_count,
        SUM(CASE WHEN sex = 'MALE' THEN 1 ELSE 0 END) AS male_count,
        SUM(CASE WHEN sex = 'FEMALE' THEN 1 ELSE 0 END) AS female_count,
        SUM(CASE WHEN HEX(COALESCE(nhts1, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS nhts1_count,
        SUM(CASE WHEN HEX(COALESCE(nhts2, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS nhts2_count,
        SUM(CASE WHEN HEX(COALESCE(fourPs, '')) = '{$checkMarkHex}' OR COALESCE(fourPs, '') = 'M' THEN 1 ELSE 0 END) AS fourPs_member_count,
        SUM(CASE WHEN fourPs = 'G' THEN 1 ELSE 0 END) AS fourPs_graduated_count,
        SUM(CASE WHEN HEX(COALESCE(F, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS farmers_count,
        SUM(CASE WHEN HEX(COALESCE(FF, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS fisherfolks_count,
        SUM(CASE WHEN HEX(COALESCE(`IS`, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS is_count,
        SUM(CASE WHEN HEX(COALESCE(IP, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS ip_count,
        SUM(CASE WHEN HEX(COALESCE(SC, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS sc_count,
        SUM(CASE WHEN HEX(COALESCE(SP, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS sp_count,
        SUM(CASE WHEN HEX(COALESCE(LW, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS lw_count,
        SUM(CASE WHEN HEX(COALESCE(PW, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS pw_count,
        SUM(CASE WHEN PWD REGEXP '^[A-Z]$' THEN 1 ELSE 0 END) AS pwd_count,
        SUM(CASE WHEN HEX(COALESCE(OSY, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS osy_count,
        SUM(CASE WHEN HEX(COALESCE(FR, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS fr_count,
        SUM(CASE WHEN HEX(COALESCE(ybDs, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS ybDs_count,
        SUM(CASE WHEN HEX(COALESCE(lgbtqia, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS lgbtqia_count
    FROM meb
    WHERE YEAR(time_stamp) = {$year}
    GROUP BY province, lgu
    ORDER BY province, lgu
";

$result = mysqli_query($conn, $query);
if (!$result) {
    die('Query failed: ' . mysqli_error($conn));
}

$rowIndex = 4;
$totals = array_fill_keys([
    'beneficiary_count', 'male_count', 'female_count', 'nhts1_count', 'nhts2_count', 'fourPs_member_count',
    'fourPs_graduated_count', 'farmers_count', 'fisherfolks_count', 'is_count', 'ip_count', 'sc_count', 'sp_count',
    'lw_count', 'pw_count', 'pwd_count', 'osy_count', 'fr_count', 'ybDs_count', 'lgbtqia_count'
], 0);

while ($row = $result->fetch_assoc()) {
    $sheet->fromArray([
        $row['province'], $row['lgu'], $row['beneficiary_count'], $row['male_count'], $row['female_count'],
        $row['nhts1_count'], $row['nhts2_count'], $row['fourPs_member_count'], $row['fourPs_graduated_count'],
        $row['farmers_count'], $row['fisherfolks_count'], $row['is_count'], $row['ip_count'], $row['sc_count'],
        $row['sp_count'], $row['lw_count'], $row['pw_count'], $row['pwd_count'], $row['osy_count'],
        $row['fr_count'], $row['ybDs_count'], $row['lgbtqia_count']
    ], null, 'A' . $rowIndex);

    foreach ($totals as $key => $value) {
        $totals[$key] += (int) ($row[$key] ?? 0);
    }

    $rowIndex++;
}

$sheet->setCellValue("A{$rowIndex}", 'Total');
$sheet->mergeCells("A{$rowIndex}:B{$rowIndex}");
$sheet->setCellValue("C{$rowIndex}", $totals['beneficiary_count']);
$sheet->setCellValue("D{$rowIndex}", $totals['male_count']);
$sheet->setCellValue("E{$rowIndex}", $totals['female_count']);
$sheet->setCellValue("F{$rowIndex}", $totals['nhts1_count']);
$sheet->setCellValue("G{$rowIndex}", $totals['nhts2_count']);
$sheet->setCellValue("H{$rowIndex}", $totals['fourPs_member_count']);
$sheet->setCellValue("I{$rowIndex}", $totals['fourPs_graduated_count']);
$sheet->setCellValue("J{$rowIndex}", $totals['farmers_count']);
$sheet->setCellValue("K{$rowIndex}", $totals['fisherfolks_count']);
$sheet->setCellValue("L{$rowIndex}", $totals['is_count']);
$sheet->setCellValue("M{$rowIndex}", $totals['ip_count']);
$sheet->setCellValue("N{$rowIndex}", $totals['sc_count']);
$sheet->setCellValue("O{$rowIndex}", $totals['sp_count']);
$sheet->setCellValue("P{$rowIndex}", $totals['lw_count']);
$sheet->setCellValue("Q{$rowIndex}", $totals['pw_count']);
$sheet->setCellValue("R{$rowIndex}", $totals['pwd_count']);
$sheet->setCellValue("S{$rowIndex}", $totals['osy_count']);
$sheet->setCellValue("T{$rowIndex}", $totals['fr_count']);
$sheet->setCellValue("U{$rowIndex}", $totals['ybDs_count']);
$sheet->setCellValue("V{$rowIndex}", $totals['lgbtqia_count']);

kodus_export_apply_uniform_style($spreadsheet, $sheet, [
    'document_title' => 'Summary of Partner-Beneficiaries per Sector',
    'title_range' => 'A1:V2',
    'header_range' => 'A3:V3',
    'data_range' => $rowIndex > 4 ? 'A4:V' . ($rowIndex - 1) : null,
    'total_range' => "A{$rowIndex}:V{$rowIndex}",
    'freeze_pane' => 'A4',
    'auto_filter' => 'A3:V3',
    'left_align_ranges' => ["A4:B{$rowIndex}"],
    'integer_ranges' => ["C4:V{$rowIndex}"],
    'row_heights' => [1 => 30, 2 => 25, 3 => 42],
    'column_widths' => [
        'A' => 18, 'B' => 22, 'C' => 20, 'D' => 12, 'E' => 12, 'F' => 16, 'G' => 18, 'H' => 14,
        'I' => 16, 'J' => 14, 'K' => 14, 'L' => 18, 'M' => 18, 'N' => 14, 'O' => 16, 'P' => 16,
        'Q' => 18, 'R' => 18, 'S' => 18, 'T' => 14, 'U' => 16, 'V' => 12,
    ],
]);

kodus_export_stream_xlsx($spreadsheet, 'Summary_of_Partner-Beneficiaries_per_Sector_' . $year . '.xlsx');
