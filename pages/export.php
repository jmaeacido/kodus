<?php
require '../vendor/autoload.php';
include('../config.php');
require_once __DIR__ . '/../export_style_helpers.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Sector Summary');
$sheet->insertNewRowBefore(1, 2);
$sheet->setCellValue('A1', 'Summary of Partner-Beneficiaries per Sector');
$sheet->mergeCells('A1:V1');
$sheet->setCellValue('A2', 'Generated on ' . date('F d, Y h:i A'));
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
        SUM(CASE WHEN nhts1 = 'âœ“' THEN 1 ELSE 0 END) AS nhts1_count,
        SUM(CASE WHEN nhts2 = 'âœ“' THEN 1 ELSE 0 END) AS nhts2_count,
        SUM(CASE WHEN fourPs IN ('âœ“', 'M') THEN 1 ELSE 0 END) AS fourPs_member_count,
        SUM(CASE WHEN fourPs = 'G' THEN 1 ELSE 0 END) AS fourPs_graduated_count,
        SUM(CASE WHEN F = 'âœ“' THEN 1 ELSE 0 END) AS farmers_count,
        SUM(CASE WHEN FF = 'âœ“' THEN 1 ELSE 0 END) AS fisherfolks_count,
        SUM(CASE WHEN `IS` = 'âœ“' THEN 1 ELSE 0 END) AS is_count,
        SUM(CASE WHEN IP = 'âœ“' THEN 1 ELSE 0 END) AS ip_count,
        SUM(CASE WHEN SC = 'âœ“' THEN 1 ELSE 0 END) AS sc_count,
        SUM(CASE WHEN SP = 'âœ“' THEN 1 ELSE 0 END) AS sp_count,
        SUM(CASE WHEN LW = 'âœ“' THEN 1 ELSE 0 END) AS lw_count,
        SUM(CASE WHEN PW = 'âœ“' THEN 1 ELSE 0 END) AS pw_count,
        SUM(CASE WHEN PWD REGEXP '^[A-Z]$' THEN 1 ELSE 0 END) AS pwd_count,
        SUM(CASE WHEN OSY = 'âœ“' THEN 1 ELSE 0 END) AS osy_count,
        SUM(CASE WHEN FR = 'âœ“' THEN 1 ELSE 0 END) AS fr_count,
        SUM(CASE WHEN ybDs = 'âœ“' THEN 1 ELSE 0 END) AS ybDs_count,
        SUM(CASE WHEN lgbtqia = 'âœ“' THEN 1 ELSE 0 END) AS lgbtqia_count
    FROM meb
    GROUP BY province, lgu
    ORDER BY province, lgu;
";

$result = mysqli_query($conn, $query);
$rowIndex = 4;

while ($row = mysqli_fetch_assoc($result)) {
    $sheet->fromArray([
        $row['province'], $row['lgu'], $row['beneficiary_count'], $row['male_count'], $row['female_count'],
        $row['nhts1_count'], $row['nhts2_count'], $row['fourPs_member_count'], $row['fourPs_graduated_count'],
        $row['farmers_count'], $row['fisherfolks_count'], $row['is_count'], $row['ip_count'], $row['sc_count'],
        $row['sp_count'], $row['lw_count'], $row['pw_count'], $row['pwd_count'], $row['osy_count'],
        $row['fr_count'], $row['ybDs_count'], $row['lgbtqia_count']
    ], null, 'A' . $rowIndex);
    $rowIndex++;
}

kodus_export_apply_uniform_style($spreadsheet, $sheet, [
    'document_title' => 'Summary of Partner-Beneficiaries per Sector',
    'title_range' => 'A1:V2',
    'header_range' => 'A3:V3',
    'data_range' => $rowIndex > 4 ? 'A4:V' . ($rowIndex - 1) : null,
    'freeze_pane' => 'A4',
    'auto_filter' => 'A3:V3',
    'left_align_ranges' => $rowIndex > 4 ? ['A4:B' . ($rowIndex - 1)] : [],
    'integer_ranges' => $rowIndex > 4 ? ['C4:V' . ($rowIndex - 1)] : [],
    'row_heights' => [1 => 28, 2 => 22, 3 => 34],
    'auto_size_columns' => range('A', 'V'),
]);

kodus_export_stream_xlsx($spreadsheet, 'Summary_of_Partner-Beneficiaries_per_Sector.xlsx');
