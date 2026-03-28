<?php
require '../vendor/autoload.php';
include('../config.php');
require_once __DIR__ . '/../export_style_helpers.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Payout Details');

$headers = [
    'Province', 'City or Municipality', 'Barangay',
    'No. of Partner-Beneficiaries', 'Amount', 'Payout Date',
    'Paid', 'Amount Paid', 'Unpaid', 'Amount Unpaid'
];

$query = "
    SELECT province, lgu, barangay, benesNumber, amount, paid, payoutDate 
    FROM breakdown 
    ORDER BY province, lgu, barangay;
";
$result = mysqli_query($conn, $query);

$totalBenes = $totalAmount = $totalPaid = $totalUnpaid = 0;
$dataRows = [];

while ($row = mysqli_fetch_assoc($result)) {
    $unpaid = $row['benesNumber'] - $row['paid'];
    $amountPaid = $row['paid'] * 7700;
    $amountUnpaid = $unpaid * 7700;

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

$totalAmountPaid = $totalPaid * 7700;
$totalAmountUnpaid = $totalUnpaid * 7700;

$sheet->setCellValue('A1', 'Payout Details');
$sheet->mergeCells('A1:J1');
$sheet->setCellValue('A2', 'Generated on ' . date('F d, Y h:i A'));
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
