<?php

declare(strict_types=1);

require_once __DIR__ . '/generator_history.php';
require_once __DIR__ . '/../../mebis-consolidator/helpers/parser.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function dedup_template_output_headers(): array
{
    return [
        'rowNumber',
        'lastName',
        'firstName',
        'middleName',
        'ext',
        'birthDate',
        'barangay',
        'lgu',
        'province',
    ];
}

function dedup_template_header_aliases(): array
{
    return [
        'entry_no' => ['no'],
        'last_name' => ['last name'],
        'first_name' => ['first name'],
        'middle_name' => ['middle name'],
        'ext_name' => ['ext'],
        'barangay_name' => ['barangay'],
        'birthdate' => ['birthdate dd mm yyyy', 'b day d m y', 'b day d my'],
    ];
}

function dedup_template_find_header_map($sheet): array
{
    $aliases = dedup_template_header_aliases();
    $required = ['entry_no', 'last_name', 'first_name', 'middle_name', 'ext_name', 'barangay_name', 'birthdate'];

    for ($row = 8; $row <= 15; $row++) {
        $highestColumn = $sheet->getHighestDataColumn($row);
        $values = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, false)[0] ?? [];
        $map = [];

        foreach ($values as $index => $value) {
            $normalized = mebis_normalize_header_label((string) $value);
            if ($normalized === '') {
                continue;
            }

            foreach ($aliases as $field => $labels) {
                if (in_array($normalized, $labels, true) && !array_key_exists($field, $map)) {
                    $map[$field] = $index;
                }
            }
        }

        $complete = true;
        foreach ($required as $field) {
            if (!array_key_exists($field, $map)) {
                $complete = false;
                break;
            }
        }

        if ($complete) {
            $map['header_row'] = $row;
            return $map;
        }
    }

    throw new RuntimeException('Unable to detect the required beneficiary columns in the uploaded MEB workbook.');
}

function dedup_template_filename_label(string $municipality): string
{
    $label = function_exists('mb_strtoupper')
        ? mb_strtoupper(trim($municipality), 'UTF-8')
        : strtoupper(trim($municipality));

    $label = preg_replace('/[^A-Z0-9]+/u', '_', (string) $label);
    $label = trim((string) $label, '_');

    return $label !== '' ? $label : 'MUNICIPALITY';
}

function dedup_template_next_batch_number(string $municipality, array &$requestBatchState): int
{
    $key = dedup_template_filename_label($municipality);
    if (isset($requestBatchState[$key])) {
        $requestBatchState[$key]++;
        return $requestBatchState[$key];
    }

    dedup_template_ensure_outputs_dir();
    $highest = 0;
    foreach (glob(dedup_template_outputs_dir() . '/*_' . $key . '_dedup batch *.xlsx') ?: [] as $path) {
        $name = basename($path);
        if (preg_match('/_dedup batch (\d+)\.xlsx$/i', $name, $matches) === 1) {
            $highest = max($highest, (int) $matches[1]);
        }
    }

    $requestBatchState[$key] = $highest + 1;
    return $requestBatchState[$key];
}

function dedup_template_birthdate_display($sheet, int $rowNumber, array $headerMap): string
{
    $birthdateIndex = (int) ($headerMap['birthdate'] ?? 0);
    $birthdateCell = $sheet->getCell(Coordinate::stringFromColumnIndex($birthdateIndex + 1) . $rowNumber);
    return mebis_date_value($birthdateCell);
}

function dedup_template_build_row(array $rowValues, array $headerMap, $sheet, int $rowNumber, int $outputRowNumber, string $province, string $municipality): array
{
    return [
        'row_number' => (string) $outputRowNumber,
        'last_name' => mebis_row_value($rowValues, $headerMap, 'last_name'),
        'first_name' => mebis_row_value($rowValues, $headerMap, 'first_name'),
        'middle_name' => mebis_row_value($rowValues, $headerMap, 'middle_name'),
        'ext_name' => mebis_row_value($rowValues, $headerMap, 'ext_name'),
        'birthdate' => dedup_template_birthdate_display($sheet, $rowNumber, $headerMap),
        'barangay_name' => mebis_row_value($rowValues, $headerMap, 'barangay_name'),
        'municipality_name' => $municipality,
        'province_name' => $province,
    ];
}

function dedup_template_parse_workbook(string $path, string $originalName): array
{
    $targetSheetName = mebis_choose_data_sheet_name($path, $originalName);
    if ($targetSheetName === null) {
        $targetSheetName = mebis_detect_uploaded_meb_sheet($path, $originalName);
    }

    if ($targetSheetName === null) {
        throw new RuntimeException(sprintf('The workbook "%s" does not contain an MEB sheet.', $originalName));
    }

    $spreadsheet = mebis_load_spreadsheet($path, $originalName, [$targetSheetName], true);

    try {
        $sheet = mebis_find_sheet_by_name($spreadsheet, $targetSheetName);
        if ($sheet === null) {
            throw new RuntimeException(sprintf('The workbook "%s" does not contain an MEB sheet.', $originalName));
        }

        $province = function_exists('mb_strtoupper')
            ? mb_strtoupper(mebis_location_from_row($sheet, 2, ['province']), 'UTF-8')
            : strtoupper(mebis_location_from_row($sheet, 2, ['province']));
        $municipality = function_exists('mb_strtoupper')
            ? mb_strtoupper(mebis_location_from_row($sheet, 3, ['municipality', 'city']), 'UTF-8')
            : strtoupper(mebis_location_from_row($sheet, 3, ['municipality', 'city']));

        if ($province === '' || $municipality === '') {
            throw new RuntimeException(sprintf('The workbook "%s" is missing province or municipality labels.', $originalName));
        }

        $headerMap = dedup_template_find_header_map($sheet);
        $rows = [];
        $highestRow = $sheet->getHighestDataRow();
        $dataStartRow = ((int) $headerMap['header_row']) + 1;
        $outputRowNumber = 1;

        for ($row = $dataStartRow; $row <= $highestRow; $row++) {
            if (mebis_is_hidden_row($sheet, $row)) {
                continue;
            }

            $highestColumn = $sheet->getHighestDataColumn($row);
            $rowValues = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, false)[0] ?? [];
            $entryNumber = mebis_row_value($rowValues, $headerMap, 'entry_no');
            $lastName = mebis_row_value($rowValues, $headerMap, 'last_name');
            $firstName = mebis_row_value($rowValues, $headerMap, 'first_name');

            if ($entryNumber === '' && $lastName === '' && $firstName === '') {
                continue;
            }

            if (!is_numeric($entryNumber) || $lastName === '' || $firstName === '') {
                continue;
            }

            $rows[] = dedup_template_build_row($rowValues, $headerMap, $sheet, $row, $outputRowNumber, $province, $municipality);
            $outputRowNumber++;
        }

        if ($rows === []) {
            throw new RuntimeException(sprintf('The workbook "%s" did not produce any beneficiary rows.', $originalName));
        }

        return [
            'province_name' => $province,
            'municipality_name' => $municipality,
            'sheet_title' => 'Beneficiaries',
            'rows' => $rows,
        ];
    } finally {
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }
}

function dedup_template_write_workbook(array $dataset, string $outputPath): void
{
    $spreadsheet = new Spreadsheet();

    try {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle((string) ($dataset['sheet_title'] ?? 'Beneficiaries'));
        $sheet->fromArray(dedup_template_output_headers(), null, 'A1');

        $rowNumber = 2;
        foreach (($dataset['rows'] ?? []) as $row) {
            $sheet->fromArray([
                $row['row_number'] ?? '',
                $row['last_name'] ?? '',
                $row['first_name'] ?? '',
                $row['middle_name'] ?? '',
                $row['ext_name'] ?? '',
                $row['birthdate'] ?? '',
                $row['barangay_name'] ?? '',
                $row['municipality_name'] ?? '',
                $row['province_name'] ?? '',
            ], null, 'A' . $rowNumber);
            $rowNumber++;
        }

        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:' . $highestColumn . $highestRow);
        $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
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
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9E2F3'],
                ],
            ],
        ]);

        if ($highestRow >= 2) {
            $sheet->getStyle('A2:' . $highestColumn . $highestRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        $sheet->getRowDimension(1)->setRowHeight(24);

        $widths = [
            'A' => 12,
            'B' => 24,
            'C' => 24,
            'D' => 22,
            'E' => 12,
            'F' => 14,
            'G' => 24,
            'H' => 24,
            'I' => 24,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
    } finally {
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }
}
