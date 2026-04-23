<?php
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$path = __DIR__ . '/Program_Targets_Template.xlsx';

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Baseline Targets');
$headers = ['PROVINCE', 'MUNICIPALITY', 'BARANGAY', 'PUROK', 'PROJECT NAME', 'PROJECT CLASSIFICATION', 'LAWA TARGET', 'BINHI TARGET', 'CAPBUILD TARGET', 'COMMUNITY ACTION PLAN TARGET', 'TARGET PARTNER-BENEFICIARIES'];
$sheet->fromArray([$headers], null, 'A1');
$sheet->fromArray([['SURIGAO DEL SUR', 'MADRID', 'SAN JUAN', 'PUROK 1||PUROK 2', 'SFR REHAB||WATER SYSTEM', 'LAWA||BINHI', 25, 25, 10, 15, 60]], null, 'A2');

foreach (range('A', 'K') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

$writer = new Xlsx($spreadsheet);
$writer->save($path);

echo $path;
