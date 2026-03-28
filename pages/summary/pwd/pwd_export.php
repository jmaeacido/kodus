<?php
require '../../../vendor/autoload.php';
include('../../../config.php');
require_once __DIR__ . '/../../../export_style_helpers.php';

session_start();

use PhpOffice\PhpSpreadsheet\Spreadsheet;

if (!isset($_SESSION['selected_year'])) {
    echo "<p style='color:red;'>Fiscal year not selected. Please go back and select a year.</p>";
    exit;
}

$year = $_SESSION['selected_year'];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('PWD Summary');

$sheet->setCellValue('A1', 'Persons with Disability (PWD) Disabilities Summary Report');
$sheet->mergeCells('A1:P1');
$sheet->setCellValue('A2', 'Fiscal Year: ' . $year);
$sheet->mergeCells('A2:P2');

$sheet->setCellValue('A3', 'Province');
$sheet->mergeCells('A3:A4');
$sheet->setCellValue('B3', 'City or Municipality');
$sheet->mergeCells('B3:B4');
$sheet->setCellValue('C3', 'Persons with Disability');
$sheet->mergeCells('C3:C4');
$sheet->setCellValue('D3', 'Sex');
$sheet->mergeCells('D3:E3');
$sheet->setCellValue('F3', 'PWD CLASSIFICATIONS');
$sheet->mergeCells('F3:P3');

$sheet->setCellValue('D4', 'MALE');
$sheet->setCellValue('E4', 'FEMALE');
$sheet->setCellValue('F4', 'A. Multiple Disabilities');
$sheet->setCellValue('G4', 'B. Intellectual Disability');
$sheet->setCellValue('H4', 'C. Learning Disability');
$sheet->setCellValue('I4', 'D. Mental Disability');
$sheet->setCellValue('J4', 'E. Physical Disability (Orthopedic)');
$sheet->setCellValue('K4', 'F. Psychosocial Disability');
$sheet->setCellValue('L4', 'G. Non-apparent Visual Disability');
$sheet->setCellValue('M4', 'H. Non-apparent Speech and Language Impairment');
$sheet->setCellValue('N4', 'I. Non-apparent Cancer');
$sheet->setCellValue('O4', 'J. Non-apparent Rare Disease');
$sheet->setCellValue('P4', 'K. Deaf/Hard of Hearing Disability');

$query = "
    SELECT 
        province, 
        lgu, 
        SUM(CASE WHEN sex = 'MALE' AND PWD REGEXP '^[A-K]$' THEN 1 ELSE 0 END) AS male_pwd_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD REGEXP '^[A-K]$' THEN 1 ELSE 0 END) AS female_pwd_count,
        SUM(CASE WHEN PWD REGEXP '^[A-K]$' THEN 1 ELSE 0 END) AS pwd_count,
        SUM(CASE WHEN PWD = 'A' THEN 1 ELSE 0 END) AS A_count,
        SUM(CASE WHEN PWD = 'B' THEN 1 ELSE 0 END) AS B_count,
        SUM(CASE WHEN PWD = 'C' THEN 1 ELSE 0 END) AS C_count,
        SUM(CASE WHEN PWD = 'D' THEN 1 ELSE 0 END) AS D_count,
        SUM(CASE WHEN PWD = 'E' THEN 1 ELSE 0 END) AS E_count,
        SUM(CASE WHEN PWD = 'F' THEN 1 ELSE 0 END) AS F_count,
        SUM(CASE WHEN PWD = 'G' THEN 1 ELSE 0 END) AS G_count,
        SUM(CASE WHEN PWD = 'H' THEN 1 ELSE 0 END) AS H_count,
        SUM(CASE WHEN PWD = 'I' THEN 1 ELSE 0 END) AS I_count,
        SUM(CASE WHEN PWD = 'J' THEN 1 ELSE 0 END) AS J_count,
        SUM(CASE WHEN PWD = 'K' THEN 1 ELSE 0 END) AS K_count
    FROM meb
    WHERE YEAR(time_stamp) = '$year'
    GROUP BY province, lgu
    ORDER BY province, lgu
";

$result = mysqli_query($conn, $query);
if (!$result) {
    die('Query failed: ' . mysqli_error($conn));
}

$rowIndex = 5;
$total_pwd = 0;
$total_male = 0;
$total_female = 0;
$total_A = 0;
$total_B = 0;
$total_C = 0;
$total_D = 0;
$total_E = 0;
$total_F = 0;
$total_G = 0;
$total_H = 0;
$total_I = 0;
$total_J = 0;
$total_K = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $sheet->fromArray([
        $row['province'], $row['lgu'], $row['pwd_count'], $row['male_pwd_count'], $row['female_pwd_count'],
        $row['A_count'], $row['B_count'], $row['C_count'], $row['D_count'], $row['E_count'],
        $row['F_count'], $row['G_count'], $row['H_count'], $row['I_count'], $row['J_count'], $row['K_count']
    ], null, 'A' . $rowIndex);

    $total_pwd += $row['pwd_count'];
    $total_male += $row['male_pwd_count'];
    $total_female += $row['female_pwd_count'];
    $total_A += $row['A_count'];
    $total_B += $row['B_count'];
    $total_C += $row['C_count'];
    $total_D += $row['D_count'];
    $total_E += $row['E_count'];
    $total_F += $row['F_count'];
    $total_G += $row['G_count'];
    $total_H += $row['H_count'];
    $total_I += $row['I_count'];
    $total_J += $row['J_count'];
    $total_K += $row['K_count'];
    $rowIndex++;
}

$sheet->setCellValue("A{$rowIndex}", 'Total');
$sheet->mergeCells("A{$rowIndex}:B{$rowIndex}");
$sheet->setCellValue("C{$rowIndex}", $total_pwd);
$sheet->setCellValue("D{$rowIndex}", $total_male);
$sheet->setCellValue("E{$rowIndex}", $total_female);
$sheet->setCellValue("F{$rowIndex}", $total_A);
$sheet->setCellValue("G{$rowIndex}", $total_B);
$sheet->setCellValue("H{$rowIndex}", $total_C);
$sheet->setCellValue("I{$rowIndex}", $total_D);
$sheet->setCellValue("J{$rowIndex}", $total_E);
$sheet->setCellValue("K{$rowIndex}", $total_F);
$sheet->setCellValue("L{$rowIndex}", $total_G);
$sheet->setCellValue("M{$rowIndex}", $total_H);
$sheet->setCellValue("N{$rowIndex}", $total_I);
$sheet->setCellValue("O{$rowIndex}", $total_J);
$sheet->setCellValue("P{$rowIndex}", $total_K);

kodus_export_apply_uniform_style($spreadsheet, $sheet, [
    'document_title' => 'Persons with Disability (PWD) Disabilities Summary Report',
    'title_range' => 'A1:P2',
    'header_range' => 'A3:P4',
    'data_range' => $rowIndex > 5 ? 'A5:P' . ($rowIndex - 1) : null,
    'total_range' => "A{$rowIndex}:P{$rowIndex}",
    'freeze_pane' => 'A5',
    'left_align_ranges' => ["A5:B{$rowIndex}"],
    'integer_ranges' => ["C5:P{$rowIndex}"],
    'row_heights' => [1 => 30, 2 => 25, 3 => 28, 4 => 55],
    'column_widths' => [
        'A' => 18, 'B' => 22, 'C' => 18, 'D' => 12, 'E' => 12, 'F' => 20, 'G' => 20, 'H' => 20,
        'I' => 20, 'J' => 24, 'K' => 20, 'L' => 24, 'M' => 28, 'N' => 18, 'O' => 22, 'P' => 22,
    ],
]);

kodus_export_stream_xlsx($spreadsheet, 'Disabilities_Summary_' . $year . '.xlsx');
