<?php

declare(strict_types=1);

require_once __DIR__ . '/../../deduplication/helpers/template_generator.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function crossmatch_template_outputs_dir(): string
{
    $dir = dirname(__DIR__) . '/outputs';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

function crossmatch_template_output_headers(): array
{
    return ['lastName', 'firstName', 'middleName', 'ext', 'birthDate', 'barangay', 'lgu', 'province'];
}

function crossmatch_template_write_workbook(array $dataset, string $outputPath): void
{
    $spreadsheet = new Spreadsheet();
    try {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Beneficiaries');
        $sheet->fromArray(crossmatch_template_output_headers(), null, 'A1');

        $rowNumber = 2;
        foreach (($dataset['rows'] ?? []) as $row) {
            $sheet->fromArray([
                (string) ($row['last_name'] ?? ''),
                (string) ($row['first_name'] ?? ''),
                (string) ($row['middle_name'] ?? ''),
                (string) ($row['ext_name'] ?? ''),
                (string) ($row['birthdate'] ?? ''),
                (string) ($row['barangay_name'] ?? ''),
                (string) ($row['municipality_name'] ?? ''),
                (string) ($row['province_name'] ?? ''),
            ], null, 'A' . $rowNumber);
            $rowNumber++;
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:H' . max(2, $rowNumber - 1));
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setWidth($column === 'E' ? 14 : 20);
        }

        (new Xlsx($spreadsheet))->save($outputPath);
    } finally {
        $spreadsheet->disconnectWorksheets();
    }
}
