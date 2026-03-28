<?php
require '../../../vendor/autoload.php';
include('../../../config.php');
require_once __DIR__ . '/../../../export_style_helpers.php';

session_start();

if (!isset($_SESSION['selected_year'])) {
    echo "<p style='color: red;'>Fiscal year not selected. Please go back and select a year.</p>";
    exit;
}

$year = (int) $_SESSION['selected_year'];

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Sex Disaggregation');

$categories = [
    'Province', 'City or Municipality', 'Persons with Disability',
    'A. Multiple Disabilities', 'B. Intellectual Disability', 'C. Learning Disability',
    'D. Mental Disability', 'E. Physical Disability (Orthopedic)', 'F. Psychosocial Disability',
    'G. Non-apparent Visual Disability', 'H. Non-apparent Speech and Language Impairment',
    'I. Non-apparent Cancer', 'J. Non-apparent Rare Disease', 'K. Deaf/Hard of Hearing Disability'
];

$colIndex = 1;
foreach ($categories as $index => $category) {
    $colLetter = Coordinate::stringFromColumnIndex($colIndex);

    if ($index < 3) {
        $sheet->setCellValue("{$colLetter}3", $category);
        $sheet->mergeCells("{$colLetter}3:{$colLetter}4");
        $colIndex++;
    } else {
        $startLetter = Coordinate::stringFromColumnIndex($colIndex);
        $endLetter = Coordinate::stringFromColumnIndex($colIndex + 1);
        $sheet->setCellValue("{$startLetter}3", $category);
        $sheet->mergeCells("{$startLetter}3:{$endLetter}3");
        $colIndex += 2;
    }
}

$lastColLetter = Coordinate::stringFromColumnIndex($colIndex - 1);
$sheet->setCellValue('A1', 'Persons with Disability (PWD) Sex Disaggregation Summary Report');
$sheet->mergeCells("A1:{$lastColLetter}1");
$sheet->setCellValue('A2', 'Fiscal Year: ' . $year);
$sheet->mergeCells("A2:{$lastColLetter}2");

$subheaders = ['Province', 'City or Municipality', 'Total PWD'];
for ($i = 4; $i < $colIndex; $i += 2) {
    $subheaders[] = 'FEMALE';
    $subheaders[] = 'MALE';
}
$sheet->fromArray($subheaders, null, 'A4');

$sheet->getRowDimension(1)->setRowHeight(30);
$sheet->getRowDimension(2)->setRowHeight(25);
$sheet->getRowDimension(3)->setRowHeight(28);
$sheet->getRowDimension(4)->setRowHeight(55);

$sheet->getColumnDimension('A')->setWidth(18);
$sheet->getColumnDimension('B')->setWidth(22);
$sheet->getColumnDimension('C')->setWidth(14);
for ($i = 4; $i <= ($colIndex - 1); $i++) {
    $letter = Coordinate::stringFromColumnIndex($i);
    $sheet->getColumnDimension($letter)->setWidth(12);
}
foreach (['J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y'] as $column) {
    $sheet->getColumnDimension($column)->setWidth(14);
}

$query = "
    SELECT 
        province, 
        lgu, 
        SUM(CASE WHEN PWD REGEXP '^[A-K]$' THEN 1 ELSE 0 END) AS pwd_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'A' THEN 1 ELSE 0 END) AS female_A_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'A' THEN 1 ELSE 0 END) AS male_A_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'B' THEN 1 ELSE 0 END) AS female_B_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'B' THEN 1 ELSE 0 END) AS male_B_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'C' THEN 1 ELSE 0 END) AS female_C_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'C' THEN 1 ELSE 0 END) AS male_C_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'D' THEN 1 ELSE 0 END) AS female_D_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'D' THEN 1 ELSE 0 END) AS male_D_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'E' THEN 1 ELSE 0 END) AS female_E_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'E' THEN 1 ELSE 0 END) AS male_E_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'F' THEN 1 ELSE 0 END) AS female_F_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'F' THEN 1 ELSE 0 END) AS male_F_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'G' THEN 1 ELSE 0 END) AS female_G_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'G' THEN 1 ELSE 0 END) AS male_G_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'H' THEN 1 ELSE 0 END) AS female_H_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'H' THEN 1 ELSE 0 END) AS male_H_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'I' THEN 1 ELSE 0 END) AS female_I_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'I' THEN 1 ELSE 0 END) AS male_I_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'J' THEN 1 ELSE 0 END) AS female_J_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'J' THEN 1 ELSE 0 END) AS male_J_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'K' THEN 1 ELSE 0 END) AS female_K_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'K' THEN 1 ELSE 0 END) AS male_K_count
    FROM meb
    WHERE YEAR(time_stamp) = $year
    GROUP BY province, lgu
    ORDER BY province, lgu;
";

$result = mysqli_query($conn, $query);
if (!$result) {
    die('Query failed: ' . mysqli_error($conn));
}

$rowIndex = 5;
$total_pwd = 0;
$total_female_A = 0; $total_male_A = 0;
$total_female_B = 0; $total_male_B = 0;
$total_female_C = 0; $total_male_C = 0;
$total_female_D = 0; $total_male_D = 0;
$total_female_E = 0; $total_male_E = 0;
$total_female_F = 0; $total_male_F = 0;
$total_female_G = 0; $total_male_G = 0;
$total_female_H = 0; $total_male_H = 0;
$total_female_I = 0; $total_male_I = 0;
$total_female_J = 0; $total_male_J = 0;
$total_female_K = 0; $total_male_K = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $sheet->fromArray([
        $row['province'], $row['lgu'], $row['pwd_count'],
        $row['female_A_count'], $row['male_A_count'],
        $row['female_B_count'], $row['male_B_count'],
        $row['female_C_count'], $row['male_C_count'],
        $row['female_D_count'], $row['male_D_count'],
        $row['female_E_count'], $row['male_E_count'],
        $row['female_F_count'], $row['male_F_count'],
        $row['female_G_count'], $row['male_G_count'],
        $row['female_H_count'], $row['male_H_count'],
        $row['female_I_count'], $row['male_I_count'],
        $row['female_J_count'], $row['male_J_count'],
        $row['female_K_count'], $row['male_K_count']
    ], null, 'A' . $rowIndex);

    $total_pwd += $row['pwd_count'];
    $total_female_A += $row['female_A_count']; $total_male_A += $row['male_A_count'];
    $total_female_B += $row['female_B_count']; $total_male_B += $row['male_B_count'];
    $total_female_C += $row['female_C_count']; $total_male_C += $row['male_C_count'];
    $total_female_D += $row['female_D_count']; $total_male_D += $row['male_D_count'];
    $total_female_E += $row['female_E_count']; $total_male_E += $row['male_E_count'];
    $total_female_F += $row['female_F_count']; $total_male_F += $row['male_F_count'];
    $total_female_G += $row['female_G_count']; $total_male_G += $row['male_G_count'];
    $total_female_H += $row['female_H_count']; $total_male_H += $row['male_H_count'];
    $total_female_I += $row['female_I_count']; $total_male_I += $row['male_I_count'];
    $total_female_J += $row['female_J_count']; $total_male_J += $row['male_J_count'];
    $total_female_K += $row['female_K_count']; $total_male_K += $row['male_K_count'];
    $rowIndex++;
}

$sheet->setCellValue("A{$rowIndex}", 'Total');
$sheet->mergeCells("A{$rowIndex}:B{$rowIndex}");
$sheet->setCellValue("C{$rowIndex}", $total_pwd);
$sheet->setCellValue("D{$rowIndex}", $total_female_A);
$sheet->setCellValue("E{$rowIndex}", $total_male_A);
$sheet->setCellValue("F{$rowIndex}", $total_female_B);
$sheet->setCellValue("G{$rowIndex}", $total_male_B);
$sheet->setCellValue("H{$rowIndex}", $total_female_C);
$sheet->setCellValue("I{$rowIndex}", $total_male_C);
$sheet->setCellValue("J{$rowIndex}", $total_female_D);
$sheet->setCellValue("K{$rowIndex}", $total_male_D);
$sheet->setCellValue("L{$rowIndex}", $total_female_E);
$sheet->setCellValue("M{$rowIndex}", $total_male_E);
$sheet->setCellValue("N{$rowIndex}", $total_female_F);
$sheet->setCellValue("O{$rowIndex}", $total_male_F);
$sheet->setCellValue("P{$rowIndex}", $total_female_G);
$sheet->setCellValue("Q{$rowIndex}", $total_male_G);
$sheet->setCellValue("R{$rowIndex}", $total_female_H);
$sheet->setCellValue("S{$rowIndex}", $total_male_H);
$sheet->setCellValue("T{$rowIndex}", $total_female_I);
$sheet->setCellValue("U{$rowIndex}", $total_male_I);
$sheet->setCellValue("V{$rowIndex}", $total_female_J);
$sheet->setCellValue("W{$rowIndex}", $total_male_J);
$sheet->setCellValue("X{$rowIndex}", $total_female_K);
$sheet->setCellValue("Y{$rowIndex}", $total_male_K);

kodus_export_apply_uniform_style($spreadsheet, $sheet, [
    'document_title' => 'Persons with Disability (PWD) Sex Disaggregation Summary Report',
    'title_range' => "A1:{$lastColLetter}2",
    'header_range' => "A3:{$lastColLetter}4",
    'data_range' => $rowIndex > 5 ? "A5:{$lastColLetter}" . ($rowIndex - 1) : null,
    'total_range' => "A{$rowIndex}:{$lastColLetter}{$rowIndex}",
    'freeze_pane' => 'A5',
    'left_align_ranges' => ["A5:B{$rowIndex}"],
    'integer_ranges' => ["C5:{$lastColLetter}{$rowIndex}"],
    'row_heights' => [1 => 30, 2 => 25, 3 => 28, 4 => 55],
    'column_widths' => [
        'A' => 18, 'B' => 22, 'C' => 14, 'D' => 12, 'E' => 12, 'F' => 12, 'G' => 12, 'H' => 12,
        'I' => 12, 'J' => 14, 'K' => 14, 'L' => 14, 'M' => 14, 'N' => 14, 'O' => 14, 'P' => 14,
        'Q' => 14, 'R' => 14, 'S' => 14, 'T' => 14, 'U' => 14, 'V' => 14, 'W' => 14, 'X' => 14, 'Y' => 14,
    ],
]);

kodus_export_stream_xlsx($spreadsheet, 'Sex_Disaggregated_PWD_Summary_' . $year . '.xlsx');
