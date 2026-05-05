<?php

declare(strict_types=1);

require '../vendor/autoload.php';
include('../config.php');
require_once __DIR__ . '/../export_style_helpers.php';
require_once __DIR__ . '/../fund_monitoring_helpers.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

session_start();

if (!isset($_SESSION['selected_year'])) {
    http_response_code(400);
    exit('Fiscal year not selected.');
}

$year = (int) $_SESSION['selected_year'];
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$currentCalendarYear = (int) date('Y');
$monthLabels = fund_monitoring_month_labels();

if ($year === $currentCalendarYear) {
    fund_monitoring_seed_budget_items($conn, $year, $userId);
} else {
    fund_monitoring_seed_object_codes($conn, $year, $userId);
}

function fund_monitoring_export_percent(float $numerator, float $denominator): float
{
    if (abs($denominator) < 0.00001) {
        return 0.0;
    }

    return $numerator / $denominator;
}

$items = fund_monitoring_list_items_with_entries($conn, $year);
$grandTotals = [
    'authorized' => 0.0,
    'realignment' => 0.0,
    'adjusted' => 0.0,
    'monthly' => [],
    'quarterly' => [],
    'total_obligations' => 0.0,
    'total_disbursement' => 0.0,
];

for ($month = 1; $month <= 12; $month++) {
    $grandTotals['monthly'][$month] = ['obligations' => 0.0, 'disbursement' => 0.0];
}

for ($quarter = 1; $quarter <= 4; $quarter++) {
    $grandTotals['quarterly'][$quarter] = ['obligations' => 0.0, 'disbursement' => 0.0];
}

$preparedItems = [];
foreach ($items as $item) {
    $quarterly = [];
    $totalObligations = 0.0;
    $totalDisbursement = 0.0;

    for ($quarter = 1; $quarter <= 4; $quarter++) {
        $quarterly[$quarter] = ['obligations' => 0.0, 'disbursement' => 0.0];
    }

    foreach ($item['monthly'] as $month => $monthlyValues) {
        $obligations = (float) ($monthlyValues['obligations'] ?? 0);
        $disbursement = (float) ($monthlyValues['disbursement'] ?? 0);
        $quarterIndex = (int) ceil($month / 3);

        $quarterly[$quarterIndex]['obligations'] += $obligations;
        $quarterly[$quarterIndex]['disbursement'] += $disbursement;
        $totalObligations += $obligations;
        $totalDisbursement += $disbursement;

        $grandTotals['monthly'][$month]['obligations'] += $obligations;
        $grandTotals['monthly'][$month]['disbursement'] += $disbursement;
    }

    for ($quarter = 1; $quarter <= 4; $quarter++) {
        $grandTotals['quarterly'][$quarter]['obligations'] += $quarterly[$quarter]['obligations'];
        $grandTotals['quarterly'][$quarter]['disbursement'] += $quarterly[$quarter]['disbursement'];
    }

    $item['quarterly'] = $quarterly;
    $item['total_obligations'] = $totalObligations;
    $item['total_disbursement'] = $totalDisbursement;
    $item['variance_obligations'] = $item['adjusted_appropriation'] - $totalObligations;
    $item['variance_disbursement'] = $item['adjusted_appropriation'] - $totalDisbursement;
    $item['utilization_obligations'] = fund_monitoring_export_percent($totalObligations, $item['adjusted_appropriation']);
    $item['utilization_disbursement'] = fund_monitoring_export_percent($totalDisbursement, $item['adjusted_appropriation']);

    $grandTotals['authorized'] += $item['authorized_appropriation'];
    $grandTotals['realignment'] += $item['realignment'];
    $grandTotals['adjusted'] += $item['adjusted_appropriation'];
    $grandTotals['total_obligations'] += $totalObligations;
    $grandTotals['total_disbursement'] += $totalDisbursement;

    $preparedItems[] = $item;
}

$grandTotals['variance_obligations'] = $grandTotals['adjusted'] - $grandTotals['total_obligations'];
$grandTotals['variance_disbursement'] = $grandTotals['adjusted'] - $grandTotals['total_disbursement'];
$grandTotals['utilization_obligations'] = fund_monitoring_export_percent($grandTotals['total_obligations'], $grandTotals['adjusted']);
$grandTotals['utilization_disbursement'] = fund_monitoring_export_percent($grandTotals['total_disbursement'], $grandTotals['adjusted']);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Fund Monitoring');

$headers = ['SARO No.', 'Object Code', 'Authorized', 'Realignment', 'Adjusted'];
foreach ($monthLabels as $monthLabel) {
    $headers[] = $monthLabel . ' Obligations';
    $headers[] = $monthLabel . ' Disbursement';
}
for ($quarter = 1; $quarter <= 4; $quarter++) {
    $headers[] = 'Q' . $quarter . ' Obligations';
    $headers[] = 'Q' . $quarter . ' Disbursement';
}
$headers = array_merge($headers, [
    'Total Obligations',
    'Total Disbursement',
    'Variance Obligations',
    'Variance Disbursement',
    '% Utilization Obligations',
    '% Utilization Disbursement',
    'Reason for Variance - Obligations',
    'Reason for Variance - Disbursement',
]);

$sheet->setCellValue('A1', 'Fund Monitoring Matrix');
$sheet->mergeCells('A1:AS1');
$sheet->setCellValue('A2', 'Fiscal Year ' . $year . ' | Generated on ' . date('F d, Y h:i A'));
$sheet->mergeCells('A2:AS2');

$totalRow = ['', 'TOTAL', $grandTotals['authorized'], $grandTotals['realignment'], $grandTotals['adjusted']];
foreach ($monthLabels as $monthNumber => $monthLabel) {
    $totalRow[] = $grandTotals['monthly'][$monthNumber]['obligations'];
    $totalRow[] = $grandTotals['monthly'][$monthNumber]['disbursement'];
}
for ($quarter = 1; $quarter <= 4; $quarter++) {
    $totalRow[] = $grandTotals['quarterly'][$quarter]['obligations'];
    $totalRow[] = $grandTotals['quarterly'][$quarter]['disbursement'];
}
$totalRow = array_merge($totalRow, [
    $grandTotals['total_obligations'],
    $grandTotals['total_disbursement'],
    $grandTotals['variance_obligations'],
    $grandTotals['variance_disbursement'],
    $grandTotals['utilization_obligations'],
    $grandTotals['utilization_disbursement'],
    '-',
    '-',
]);

$sheet->fromArray([$totalRow], null, 'A3');
$sheet->fromArray([$headers], null, 'A4');

$rowIndex = 5;
foreach ($preparedItems as $item) {
    $row = [
        $item['saro_number'],
        $item['object_code_name'],
        $item['authorized_appropriation'],
        $item['realignment'],
        $item['adjusted_appropriation'],
    ];

    foreach ($monthLabels as $monthNumber => $monthLabel) {
        $row[] = $item['monthly'][$monthNumber]['obligations'];
        $row[] = $item['monthly'][$monthNumber]['disbursement'];
    }

    for ($quarter = 1; $quarter <= 4; $quarter++) {
        $row[] = $item['quarterly'][$quarter]['obligations'];
        $row[] = $item['quarterly'][$quarter]['disbursement'];
    }

    $row = array_merge($row, [
        $item['total_obligations'],
        $item['total_disbursement'],
        $item['variance_obligations'],
        $item['variance_disbursement'],
        $item['utilization_obligations'],
        $item['utilization_disbursement'],
        $item['reason_obligation'] !== '' ? $item['reason_obligation'] : '-',
        $item['reason_disbursement'] !== '' ? $item['reason_disbursement'] : '-',
    ]);

    $sheet->fromArray([$row], null, 'A' . $rowIndex);
    $rowIndex++;
}

$lastDataRow = $rowIndex - 1;
$lastValueRow = max(3, $lastDataRow);

kodus_export_apply_uniform_style($spreadsheet, $sheet, [
    'document_title' => 'Fund Monitoring Matrix',
    'document_subject' => 'Fund Monitoring',
    'title_range' => 'A1:AS2',
    'header_range' => 'A4:AS4',
    'data_range' => $lastDataRow >= 5 ? "A5:AS{$lastDataRow}" : null,
    'total_range' => 'A3:AS3',
    'freeze_pane' => 'C5',
    'auto_filter' => 'A4:AS4',
    'left_align_ranges' => $lastDataRow >= 5 ? ["A5:B{$lastDataRow}", "AR5:AS{$lastDataRow}"] : [],
    'currency_ranges' => ["C3:AO{$lastValueRow}"],
    'row_heights' => [1 => 28, 2 => 22, 3 => 24, 4 => 36],
    'column_widths' => [
        'A' => 24,
        'B' => 34,
        'AR' => 34,
        'AS' => 34,
    ],
]);

$sheet->getStyle("AP3:AQ{$lastValueRow}")->getNumberFormat()->setFormatCode('0.00%');

kodus_export_stream_xlsx($spreadsheet, 'Fund_Monitoring_' . $year . '.xlsx');
