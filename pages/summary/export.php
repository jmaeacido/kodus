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

use PhpOffice\PhpSpreadsheet\Spreadsheet;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Partner Beneficiaries');

$sheet->setCellValue('A1', 'Summary of Partner-Beneficiaries per Sector');
$sheet->mergeCells('A1:Q1');
$sheet->setCellValue('A2', 'Fiscal Year: ' . $year);
$sheet->mergeCells('A2:Q2');

$headers = [
    'Province', 'City or Municipality', 'No. of Partner-Beneficiaries', 'MALE', 'FEMALE',
    'NHTS-PR / LISTAHANAN', 'Identified Poor by LSWDO', 'Farmers', 'Fisherfolks',
    'Indigenous People', 'SC / Elder Persons', 'Solo Parents', 'Youth / OSY',
    'Persons with Disability', '4Ps', 'LGBTQIA+', 'Others (FR/YB/DS)'
];

$sheet->fromArray([$headers], null, 'A3');

$query = "
    SELECT 
        province, 
        lgu, 
        SUM(CASE WHEN sex = 'MALE' THEN 1 ELSE 0 END) AS male_count,
        SUM(CASE WHEN sex = 'FEMALE' THEN 1 ELSE 0 END) AS female_count,
        SUM(CASE WHEN nhts1 = '✓' THEN 1 ELSE 0 END) AS nhts1_count,
        (SUM(CASE WHEN sex = 'MALE' THEN 1 ELSE 0 END) + SUM(CASE WHEN sex = 'FEMALE' THEN 1 ELSE 0 END)) AS beneficiary_count,
        SUM(CASE WHEN nhts2 = '✓' THEN 1 ELSE 0 END) AS nhts2_count,
        SUM(CASE WHEN F = '✓' THEN 1 ELSE 0 END) AS farmers_count,
        SUM(CASE WHEN FF = '✓' THEN 1 ELSE 0 END) AS fisherfolks_count,
        SUM(CASE WHEN IP = '✓' THEN 1 ELSE 0 END) AS ip_count,
        SUM(CASE WHEN SC = '✓' THEN 1 ELSE 0 END) AS sc_count,
        SUM(CASE WHEN SP = '✓' THEN 1 ELSE 0 END) AS sp_count,
        SUM(CASE WHEN OSY = '✓' THEN 1 ELSE 0 END) AS osy_count,
        SUM(CASE WHEN PWD REGEXP '^[A-K]$' THEN 1 ELSE 0 END) AS pwd_count,
        SUM(CASE WHEN fourPs = '✓' THEN 1 ELSE 0 END) AS fourPs_count,
        SUM(CASE WHEN lgbtqia = '✓' THEN 1 ELSE 0 END) AS lgbtqia_count,
        (SUM(CASE WHEN FR = '✓' THEN 1 ELSE 0 END) + SUM(CASE WHEN ybDs = '✓' THEN 1 ELSE 0 END)) AS others_count
    FROM meb
    WHERE YEAR(time_stamp) = $year
    GROUP BY province, lgu
    ORDER BY province, lgu;
";

$result = mysqli_query($conn, $query);
if (!$result) {
    die('Query failed: ' . mysqli_error($conn));
}

$rowIndex = 4;
$total_beneficiary = 0;
$total_male = 0;
$total_female = 0;
$total_nhts1 = 0;
$total_nhts2 = 0;
$total_farmers = 0;
$total_fisherfolks = 0;
$total_ip = 0;
$total_sc = 0;
$total_sp = 0;
$total_osy = 0;
$total_pwd = 0;
$total_fourPs = 0;
$total_lgbtqia = 0;
$total_others = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $sheet->fromArray([
        $row['province'], $row['lgu'], $row['beneficiary_count'], $row['male_count'], $row['female_count'],
        $row['nhts1_count'], $row['nhts2_count'], $row['farmers_count'], $row['fisherfolks_count'],
        $row['ip_count'], $row['sc_count'], $row['sp_count'], $row['osy_count'], $row['pwd_count'],
        $row['fourPs_count'], $row['lgbtqia_count'], $row['others_count']
    ], null, 'A' . $rowIndex);

    $total_beneficiary += $row['beneficiary_count'];
    $total_male += $row['male_count'];
    $total_female += $row['female_count'];
    $total_nhts1 += $row['nhts1_count'];
    $total_nhts2 += $row['nhts2_count'];
    $total_farmers += $row['farmers_count'];
    $total_fisherfolks += $row['fisherfolks_count'];
    $total_ip += $row['ip_count'];
    $total_sc += $row['sc_count'];
    $total_sp += $row['sp_count'];
    $total_osy += $row['osy_count'];
    $total_pwd += $row['pwd_count'];
    $total_fourPs += $row['fourPs_count'];
    $total_lgbtqia += $row['lgbtqia_count'];
    $total_others += $row['others_count'];
    $rowIndex++;
}

$sheet->setCellValue("A{$rowIndex}", 'Total');
$sheet->mergeCells("A{$rowIndex}:B{$rowIndex}");
$sheet->setCellValue("C{$rowIndex}", $total_beneficiary);
$sheet->setCellValue("D{$rowIndex}", $total_male);
$sheet->setCellValue("E{$rowIndex}", $total_female);
$sheet->setCellValue("F{$rowIndex}", $total_nhts1);
$sheet->setCellValue("G{$rowIndex}", $total_nhts2);
$sheet->setCellValue("H{$rowIndex}", $total_farmers);
$sheet->setCellValue("I{$rowIndex}", $total_fisherfolks);
$sheet->setCellValue("J{$rowIndex}", $total_ip);
$sheet->setCellValue("K{$rowIndex}", $total_sc);
$sheet->setCellValue("L{$rowIndex}", $total_sp);
$sheet->setCellValue("M{$rowIndex}", $total_osy);
$sheet->setCellValue("N{$rowIndex}", $total_pwd);
$sheet->setCellValue("O{$rowIndex}", $total_fourPs);
$sheet->setCellValue("P{$rowIndex}", $total_lgbtqia);
$sheet->setCellValue("Q{$rowIndex}", $total_others);

kodus_export_apply_uniform_style($spreadsheet, $sheet, [
    'document_title' => 'Summary of Partner-Beneficiaries per Sector',
    'title_range' => 'A1:Q2',
    'header_range' => 'A3:Q3',
    'data_range' => $rowIndex > 4 ? 'A4:Q' . ($rowIndex - 1) : null,
    'total_range' => "A{$rowIndex}:Q{$rowIndex}",
    'freeze_pane' => 'A4',
    'auto_filter' => 'A3:Q3',
    'left_align_ranges' => ["A4:B{$rowIndex}"],
    'integer_ranges' => ["C4:Q{$rowIndex}"],
    'row_heights' => [1 => 30, 2 => 25, 3 => 42],
    'column_widths' => [
        'A' => 18, 'B' => 22, 'C' => 20, 'D' => 12, 'E' => 12, 'F' => 20, 'G' => 22, 'H' => 14,
        'I' => 14, 'J' => 18, 'K' => 18, 'L' => 14, 'M' => 14, 'N' => 18, 'O' => 10, 'P' => 12, 'Q' => 18,
    ],
]);

kodus_export_stream_xlsx($spreadsheet, 'Summary_of_Partner-Beneficiaries_per_Sector_' . $year . '.xlsx');
