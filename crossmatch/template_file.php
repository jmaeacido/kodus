<?php

declare(strict_types=1);

require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

security_bootstrap_session();
security_require_method(['GET']);

$path = __DIR__ . '/helpers/Beneficiaries_Template.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Beneficiaries_Template.xlsx"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

while (ob_get_level() > 0) {
    ob_end_clean();
}

if (is_file($path)) {
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
    exit;
}

$spreadsheet = new Spreadsheet();

try {
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Beneficiaries');
    $sheet->fromArray([
        'lastName',
        'firstName',
        'middleName',
        'ext',
        'birthDate',
        'barangay',
        'lgu',
        'province',
    ], null, 'A1');

    $sheet->fromArray([
        'DELA CRUZ',
        'JUAN',
        'SANTOS',
        'JR',
        '1990-01-31',
        'BARANGAY 1',
        'BUTUAN CITY',
        'AGUSAN DEL NORTE',
    ], null, 'A2');

    $sheet->freezePane('A2');
    $sheet->setAutoFilter('A1:H2');
    $sheet->getStyle('A1:H1')->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '1F4E78'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'D9E2F3'],
            ],
        ],
    ]);

    foreach (range('A', 'H') as $column) {
        $sheet->getColumnDimension($column)->setWidth($column === 'E' ? 14 : 20);
    }

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
} finally {
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);
}

exit;
