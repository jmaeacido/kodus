<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

function kodus_export_apply_uniform_style(Spreadsheet $spreadsheet, Worksheet $sheet, array $options = []): void
{
    $spreadsheet->getProperties()
        ->setCreator('KODUS')
        ->setLastModifiedBy('KODUS')
        ->setCompany('KODUS')
        ->setTitle((string) ($options['document_title'] ?? 'KODUS Export'))
        ->setSubject((string) ($options['document_subject'] ?? 'KODUS Data Export'))
        ->setDescription((string) ($options['document_description'] ?? 'Generated from the KODUS web application.'));

    $titleRange = $options['title_range'] ?? null;
    $headerRange = $options['header_range'] ?? null;
    $dataRange = $options['data_range'] ?? null;
    $totalRange = $options['total_range'] ?? null;

    if (!empty($titleRange)) {
        $sheet->getStyle($titleRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '0F172A'],
                'size' => 14,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E8F1FB'],
            ],
        ]);
    }

    if (!empty($headerRange)) {
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1D4ED8'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'BFCCE1'],
                ],
            ],
        ]);
    }

    if (!empty($dataRange)) {
        $sheet->getStyle($dataRange)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D9E6'],
                ],
            ],
        ]);
    }

    if (!empty($totalRange)) {
        $sheet->getStyle($totalRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F766E'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '0F5E59'],
                ],
            ],
        ]);
    }

    foreach (($options['left_align_ranges'] ?? []) as $range) {
        if ($range !== '') {
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
    }

    foreach (($options['right_align_ranges'] ?? []) as $range) {
        if ($range !== '') {
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }

    foreach (($options['integer_ranges'] ?? []) as $range) {
        if ($range !== '') {
            $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0');
        }
    }

    foreach (($options['currency_ranges'] ?? []) as $range) {
        if ($range !== '') {
            $sheet->getStyle($range)->getNumberFormat()->setFormatCode('"PHP " #,##0.00');
        }
    }

    foreach (($options['date_ranges'] ?? []) as $range) {
        if ($range !== '') {
            $sheet->getStyle($range)->getNumberFormat()->setFormatCode('mmmm d, yyyy');
        }
    }

    foreach (($options['column_widths'] ?? []) as $column => $width) {
        $sheet->getColumnDimension((string) $column)->setWidth((float) $width);
    }

    if (!empty($options['auto_size_columns'])) {
        foreach ($options['auto_size_columns'] as $column) {
            $sheet->getColumnDimension((string) $column)->setAutoSize(true);
        }
    }

    foreach (($options['row_heights'] ?? []) as $row => $height) {
        $sheet->getRowDimension((int) $row)->setRowHeight((float) $height);
    }

    if (!empty($options['freeze_pane'])) {
        $sheet->freezePane((string) $options['freeze_pane']);
    }

    if (!empty($options['auto_filter'])) {
        $sheet->setAutoFilter((string) $options['auto_filter']);
    }
}

function kodus_export_stream_xlsx(Spreadsheet $spreadsheet, string $filename): void
{
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: no-cache');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    if (ob_get_length()) {
        ob_end_clean();
    }
    $writer->save('php://output');
    exit;
}
