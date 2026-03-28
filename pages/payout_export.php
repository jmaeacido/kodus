<?php
require '../vendor/autoload.php';
include('../config.php');
require_once __DIR__ . '/../export_style_helpers.php';
require_once __DIR__ . '/../project_variable_helpers.php';
session_start();

use PhpOffice\PhpSpreadsheet\Spreadsheet;

if (!isset($_SESSION['selected_year'])) {
    http_response_code(400);
    exit('Fiscal year not selected.');
}

$year = (int) $_SESSION['selected_year'];
$dailyWageRate = project_variable_get_number($conn, 'daily_wage_rate', $year, 0);
$payoutDays = (int) round(project_variable_get_number($conn, 'working_days', $year, 20));
$payoutDays = $payoutDays > 0 ? $payoutDays : 20;
$beneficiaryPayoutRate = $dailyWageRate * $payoutDays;

if ($dailyWageRate <= 0) {
    http_response_code(400);
    exit('Missing project variable for daily wage rate in the selected fiscal year.');
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Payout Details');

$headers = [
    'Province', 'City or Municipality', 'Barangay',
    'No. of Partner-Beneficiaries', 'Amount', 'Payout Date',
    'Paid', 'Amount Paid', 'Unpaid', 'Amount Unpaid'
];

$stmt = $conn->prepare("
    SELECT province, lgu, barangay, benesNumber, amount, paid, payoutDate 
    FROM breakdown 
    WHERE YEAR(payoutDate) = ?
    ORDER BY province, lgu, barangay;
");
$stmt->bind_param('i', $year);
$stmt->execute();
$result = $stmt->get_result();

$totalBenes = $totalAmount = $totalPaid = $totalUnpaid = 0;
$dataRows = [];

while ($row = mysqli_fetch_assoc($result)) {
    $unpaid = $row['benesNumber'] - $row['paid'];
    $amountPaid = $row['paid'] * $beneficiaryPayoutRate;
    $amountUnpaid = $unpaid * $beneficiaryPayoutRate;

    $totalBenes += $row['benesNumber'];
    $totalAmount += $row['amount'];
    $totalPaid += $row['paid'];
    $totalUnpaid += $unpaid;

    $dataRows[] = [
        $row['province'],
        $row['lgu'],
        $row['barangay'],
        $row['benesNumber'],
        $row['amount'],
        !empty($row['payoutDate']) ? \PhpOffice\PhpSpreadsheet\Shared\Date::stringToExcel($row['payoutDate']) : '',
        $row['paid'],
        $amountPaid,
        $unpaid,
        $amountUnpaid
    ];
}

$totalAmountPaid = $totalPaid * $beneficiaryPayoutRate;
$totalAmountUnpaid = $totalUnpaid * $beneficiaryPayoutRate;

$sheet->setCellValue('A1', 'Payout Details');
$sheet->mergeCells('A1:J1');
$sheet->setCellValue('A2', 'Generated on ' . date('F d, Y h:i A') . ' | Fiscal Year ' . $year . ' | Daily Wage Rate PHP ' . number_format($dailyWageRate, 2) . ' | Payout Days ' . $payoutDays);
$sheet->mergeCells('A2:J2');
$sheet->fromArray([
    'Total', '', '', $totalBenes, $totalAmount, '', $totalPaid, $totalAmountPaid, $totalUnpaid, $totalAmountUnpaid
], null, 'A3');
$sheet->mergeCells('A3:C3');
$sheet->fromArray([$headers], null, 'A4');

$rowIndex = 5;
foreach ($dataRows as $rowData) {
    $sheet->fromArray($rowData, null, 'A' . $rowIndex);
    $rowIndex++;
}

$lastDataRow = $rowIndex - 1;

kodus_export_apply_uniform_style($spreadsheet, $sheet, [
    'document_title' => 'Payout Details',
    'title_range' => 'A1:J2',
    'header_range' => 'A4:J4',
    'data_range' => $lastDataRow >= 5 ? "A5:J{$lastDataRow}" : null,
    'total_range' => 'A3:J3',
    'freeze_pane' => 'A5',
    'auto_filter' => 'A4:J4',
    'left_align_ranges' => $lastDataRow >= 5 ? ["A5:C{$lastDataRow}"] : [],
    'integer_ranges' => ["D3:D{$lastDataRow}", "G3:G{$lastDataRow}", "I3:I{$lastDataRow}"],
    'currency_ranges' => ["E3:E{$lastDataRow}", "H3:H{$lastDataRow}", "J3:J{$lastDataRow}"],
    'date_ranges' => $lastDataRow >= 5 ? ["F5:F{$lastDataRow}"] : [],
    'row_heights' => [1 => 28, 2 => 22, 3 => 24, 4 => 32],
    'auto_size_columns' => range('A', 'J'),
]);

kodus_export_stream_xlsx($spreadsheet, 'Payout_Details.xlsx');
