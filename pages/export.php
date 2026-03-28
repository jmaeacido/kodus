<?php
require '../vendor/autoload.php';
include('../config.php');
require_once __DIR__ . '/../export_style_helpers.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Create a new spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Sector Summary');
$sheet->insertNewRowBefore(1, 2);
$sheet->setCellValue('A1', 'Summary of Partner-Beneficiaries per Sector');
$sheet->mergeCells('A1:Q1');
$sheet->setCellValue('A2', 'Generated on ' . date('F d, Y h:i A'));
$sheet->mergeCells('A2:Q2');

// Set column headers
$headers = [
    'Province', 'City or Municipality', 'No. of Partner-Beneficiaries', 'MALE', 'FEMALE',
    'NHTS-PR / LISTAHANAN', 'Identified Poor by LSWDO', 'Farmers', 'Fisherfolks',
    'Indigenous People', 'SC / Elder Persons', 'Solo Parents', 'Youth / OSY',
    'Persons with Disability', '4Ps', 'LGBTQIA+', 'Others (FR/YB/DS)'
];

$sheet->fromArray([$headers], NULL, 'A3');

// Fetch data from database
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
        SUM(CASE WHEN PWD REGEXP '^[A-Z]$' THEN 1 ELSE 0 END) AS pwd_count,
        SUM(CASE WHEN fourPs = '✓' THEN 1 ELSE 0 END) AS fourPs_count,
        SUM(CASE WHEN lgbtqia = '✓' THEN 1 ELSE 0 END) AS lgbtqia_count,
        (SUM(CASE WHEN FR = '✓' THEN 1 ELSE 0 END) + SUM(CASE WHEN ybDs = '✓' THEN 1 ELSE 0 END)) AS others_count
    FROM meb
    GROUP BY province, lgu
    ORDER BY province, lgu;
";

$result = mysqli_query($conn, $query);

// Populate the Excel file with data
$rowIndex = 4; // Start below title + header rows
while ($row = mysqli_fetch_assoc($result)) {
    $sheet->fromArray([
        $row['province'], $row['lgu'], $row['beneficiary_count'], $row['male_count'], 
        $row['female_count'], $row['nhts1_count'], $row['nhts2_count'], $row['farmers_count'], 
        $row['fisherfolks_count'], $row['ip_count'], $row['sc_count'], $row['sp_count'], 
        $row['osy_count'], $row['pwd_count'], $row['fourPs_count'], $row['lgbtqia_count'], 
        $row['others_count']
    ], NULL, 'A' . $rowIndex);
    $rowIndex++;
}

kodus_export_apply_uniform_style($spreadsheet, $sheet, [
    'document_title' => 'Summary of Partner-Beneficiaries per Sector',
    'title_range' => 'A1:Q2',
    'header_range' => 'A3:Q3',
    'data_range' => $rowIndex > 4 ? 'A4:Q' . ($rowIndex - 1) : null,
    'freeze_pane' => 'A4',
    'auto_filter' => 'A3:Q3',
    'left_align_ranges' => $rowIndex > 4 ? ['A4:B' . ($rowIndex - 1)] : [],
    'integer_ranges' => $rowIndex > 4 ? ['C4:Q' . ($rowIndex - 1)] : [],
    'row_heights' => [1 => 28, 2 => 22, 3 => 34],
    'auto_size_columns' => range('A', 'Q'),
]);

kodus_export_stream_xlsx($spreadsheet, 'Summary_of_Partner-Beneficiaries_per_Sector.xlsx');
?>
