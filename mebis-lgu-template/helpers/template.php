<?php

declare(strict_types=1);

require_once __DIR__ . '/../../mebis-consolidator/helpers/parser.php';

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function mebis_template_output_headers(): array
{
    return [
        'LAST NAME',
        'FIRST NAME',
        'MIDDLE NAME',
        'EXT.',
        'PUROK',
        'BARANGAY',
        'LGU',
        'PROVINCE',
        'BIRTHDATE',
        'AGE',
        'SEX',
        'CIVIL STATUS',
        'POOR BASED ON NATIONAL HOUSEHOLD TARGETING SYSTEM FOR POVERTY REDUCTION (NHTS-PR) Listahanan 3 (P)',
        'IDENTIFIED POOR, MARGINALIZED & DISADVANTAGED BASED ON THE ASSESSMENT OF LSWDO (NON)',
        'Pantawid Pamilyang Pilipino Program (4Ps)',
        'Farmers (F)',
        'Fisher-folks (FF)',
        'Informal Sector (IS)',
        'Indigenous People (IP)',
        'Senior Citizen (SC)',
        'Solo Parent (SP)',
        'Lactating Women (LW)',
        'Pregnant Women (PW)',
        'Persons with Disability (PWD)',
        'Out of School Youth (OSY)',
        'Former Rebel (FR)',
        'YAKAP Bayan/ Person Who Used Drugs (YB/PWUD)',
        'LGBTQIA+',
    ];
}

function mebis_template_header_aliases(): array
{
    return [
        'entry_no' => ['no'],
        'last_name' => ['last name'],
        'first_name' => ['first name'],
        'middle_name' => ['middle name'],
        'ext_name' => ['ext'],
        'purok' => ['purok'],
        'barangay_name' => ['barangay'],
        'birthdate' => ['birthdate dd mm yyyy', 'b day d m y', 'b day d my'],
        'age' => ['age'],
        'sex' => ['sex'],
        'civil_status' => ['civil status'],
        'nhts_poor' => ['p'],
        'nhts_non_poor' => ['non'],
        'pantawid' => ['4ps'],
        'farmers' => ['f'],
        'fisher_folks' => ['ff'],
        'informal_sector' => ['is'],
        'indigenous_people' => ['ip'],
        'senior_citizen' => ['sc'],
        'solo_parent' => ['sp'],
        'lactating_women' => ['lw'],
        'pregnant_women' => ['pw'],
        'pwd' => ['pwd'],
        'osy' => ['osy'],
        'former_rebel' => ['fr'],
        'yb_ds' => ['yb pwud', 'yb ds'],
        'lgbtqia' => ['lgbtqia+'],
    ];
}

function mebis_template_find_header_map($sheet): array
{
    $aliases = mebis_template_header_aliases();
    $required = [
        'entry_no',
        'last_name',
        'first_name',
        'middle_name',
        'ext_name',
        'purok',
        'barangay_name',
        'birthdate',
        'age',
        'sex',
        'civil_status',
    ];

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

    throw new RuntimeException('Unable to detect the LGU template columns in the MEB sheet.');
}

function mebis_template_sheet_title(string $municipality): string
{
    $title = trim($municipality);
    if ($title === '') {
        $title = 'MEB TEMPLATE';
    }

    $title = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]+/', ' ', $title);
    $title = preg_replace('/\s+/u', ' ', (string) $title);
    $title = trim((string) $title);

    if ($title === '') {
        $title = 'MEB TEMPLATE';
    }

    return mb_substr($title, 0, 31);
}

function mebis_template_filename_label(string $municipality): string
{
    $label = function_exists('mb_strtoupper')
        ? mb_strtoupper(trim($municipality), 'UTF-8')
        : strtoupper(trim($municipality));

    $label = preg_replace('/[^A-Z0-9]+/u', '_', (string) $label);
    $label = trim((string) $label, '_');

    return $label !== '' ? $label : 'MUNICIPALITY';
}

function mebis_template_next_batch_number(string $municipality, array &$requestBatchState): int
{
    $key = mebis_template_filename_label($municipality);
    if (isset($requestBatchState[$key])) {
        $requestBatchState[$key]++;
        return $requestBatchState[$key];
    }

    mebis_template_ensure_outputs_dir();
    $highest = 0;
    foreach (glob(mebis_template_outputs_dir() . '/*_' . $key . ' batch *.xlsx') ?: [] as $path) {
        $name = basename($path);
        if (preg_match('/ batch (\d+)\.xlsx$/i', $name, $matches) === 1) {
            $highest = max($highest, (int) $matches[1]);
        }
    }

    $requestBatchState[$key] = $highest + 1;
    return $requestBatchState[$key];
}

function mebis_template_clean_purok(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/^\s*PUROK\s*/i', '', $value);
    $value = preg_replace('/\s*-\s*/', '-', (string) $value);
    $value = preg_replace('/\s+/u', ' ', (string) $value);
    $value = trim((string) $value, " \t\n\r\0\x0B-");

    return trim((string) $value);
}

function mebis_template_normalize_fourps(string $value): string
{
    $normalized = strtoupper(trim($value));

    if ($normalized === 'G') {
        return 'G';
    }

    if ($normalized === 'M' || in_array($normalized, ['✓', 'ÂŒ“', 'Ã¢Å“â€œ', 'YES', 'Y', 'TRUE', '1'], true)) {
        return 'M';
    }

    return '';
}

function mebis_template_birthdate_display(Cell $cell): string
{
    $normalized = mebis_date_value($cell);
    if ($normalized === '') {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $normalized);
    if ($date instanceof DateTimeImmutable) {
        return $date->format('Y-m-d');
    }

    return $normalized;
}

function mebis_template_is_marked_value(string $value): bool
{
    $normalized = strtolower(trim($value));
    if ($normalized === '') {
        return false;
    }

    return in_array($normalized, ['✓', 'yes', 'y', 'true', '1', 'm', 'g'], true);
}

function mebis_template_build_row(array $rowValues, array $headerMap, $sheet, int $rowNumber, string $province, string $municipality): array
{
    $birthdateIndex = (int) ($headerMap['birthdate'] ?? 0);
    $birthdateCell = $sheet->getCell(Coordinate::stringFromColumnIndex($birthdateIndex + 1) . $rowNumber);
    $farmers = mebis_row_value($rowValues, $headerMap, 'farmers');
    $fisherFolks = mebis_row_value($rowValues, $headerMap, 'fisher_folks');
    $informalSector = mebis_row_value($rowValues, $headerMap, 'informal_sector');

    if (trim($farmers) === '' && trim($fisherFolks) === '' && trim($informalSector) === '') {
        $informalSector = '✓';
    }

    if (mebis_template_is_marked_value($farmers) || mebis_template_is_marked_value($fisherFolks)) {
        $informalSector = '';
    }

    $row = [
        'last_name' => mebis_row_value($rowValues, $headerMap, 'last_name'),
        'first_name' => mebis_row_value($rowValues, $headerMap, 'first_name'),
        'middle_name' => mebis_row_value($rowValues, $headerMap, 'middle_name'),
        'ext_name' => mebis_row_value($rowValues, $headerMap, 'ext_name'),
        'purok' => mebis_template_clean_purok(mebis_row_value($rowValues, $headerMap, 'purok')),
        'barangay_name' => mebis_row_value($rowValues, $headerMap, 'barangay_name'),
        'municipality_name' => $municipality,
        'province_name' => $province,
        'birthdate' => mebis_template_birthdate_display($birthdateCell),
        'age' => mebis_row_value($rowValues, $headerMap, 'age'),
        'sex' => mebis_row_value($rowValues, $headerMap, 'sex'),
        'civil_status' => mebis_row_value($rowValues, $headerMap, 'civil_status'),
        'nhts_poor' => mebis_row_value($rowValues, $headerMap, 'nhts_poor'),
        'nhts_non_poor' => mebis_row_value($rowValues, $headerMap, 'nhts_non_poor'),
        'pantawid' => mebis_template_normalize_fourps(mebis_row_value($rowValues, $headerMap, 'pantawid')),
        'farmers' => $farmers,
        'fisher_folks' => $fisherFolks,
        'informal_sector' => $informalSector,
        'indigenous_people' => mebis_row_value($rowValues, $headerMap, 'indigenous_people'),
        'senior_citizen' => mebis_row_value($rowValues, $headerMap, 'senior_citizen'),
        'solo_parent' => mebis_row_value($rowValues, $headerMap, 'solo_parent'),
        'lactating_women' => mebis_row_value($rowValues, $headerMap, 'lactating_women'),
        'pregnant_women' => mebis_row_value($rowValues, $headerMap, 'pregnant_women'),
        'pwd' => mebis_row_value($rowValues, $headerMap, 'pwd'),
        'osy' => mebis_row_value($rowValues, $headerMap, 'osy'),
        'former_rebel' => mebis_row_value($rowValues, $headerMap, 'former_rebel'),
        'yb_ds' => mebis_row_value($rowValues, $headerMap, 'yb_ds'),
        'lgbtqia' => mebis_row_value($rowValues, $headerMap, 'lgbtqia'),
    ];

    return $row;
}

function mebis_template_parse_workbook(string $path, string $originalName): array
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

        $headerMap = mebis_template_find_header_map($sheet);
        $rows = [];
        $highestRow = $sheet->getHighestDataRow();
        $dataStartRow = ((int) $headerMap['header_row']) + 1;

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

            if (!is_numeric($entryNumber)) {
                continue;
            }

            $rows[] = mebis_template_build_row($rowValues, $headerMap, $sheet, $row, $province, $municipality);
        }

        if ($rows === []) {
            throw new RuntimeException(sprintf('The workbook "%s" did not produce any beneficiary rows.', $originalName));
        }

        return [
            'province_name' => $province,
            'municipality_name' => $municipality,
            'sheet_title' => mebis_template_sheet_title($municipality),
            'rows' => $rows,
        ];
    } finally {
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }
}

function mebis_template_write_workbook(array $dataset, string $outputPath): void
{
    $spreadsheet = new Spreadsheet();

    try {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle((string) ($dataset['sheet_title'] ?? 'MEB TEMPLATE'));
        $headers = mebis_template_output_headers();
        $sheet->fromArray($headers, null, 'A1');

        $rowNumber = 2;
        foreach (($dataset['rows'] ?? []) as $row) {
            $sheet->fromArray([
                $row['last_name'] ?? '',
                $row['first_name'] ?? '',
                $row['middle_name'] ?? '',
                $row['ext_name'] ?? '',
                $row['purok'] ?? '',
                $row['barangay_name'] ?? '',
                $row['municipality_name'] ?? '',
                $row['province_name'] ?? '',
                $row['birthdate'] ?? '',
                $row['age'] ?? '',
                $row['sex'] ?? '',
                $row['civil_status'] ?? '',
                $row['nhts_poor'] ?? '',
                $row['nhts_non_poor'] ?? '',
                $row['pantawid'] ?? '',
                $row['farmers'] ?? '',
                $row['fisher_folks'] ?? '',
                $row['informal_sector'] ?? '',
                $row['indigenous_people'] ?? '',
                $row['senior_citizen'] ?? '',
                $row['solo_parent'] ?? '',
                $row['lactating_women'] ?? '',
                $row['pregnant_women'] ?? '',
                $row['pwd'] ?? '',
                $row['osy'] ?? '',
                $row['former_rebel'] ?? '',
                $row['yb_ds'] ?? '',
                $row['lgbtqia'] ?? '',
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

        $sheet->getRowDimension(1)->setRowHeight(42);

        $widths = [
            'A' => 22,
            'B' => 22,
            'C' => 22,
            'D' => 12,
            'E' => 14,
            'F' => 24,
            'G' => 18,
            'H' => 22,
            'I' => 14,
            'J' => 8,
            'K' => 12,
            'L' => 16,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        for ($column = Coordinate::columnIndexFromString('M'); $column <= Coordinate::columnIndexFromString($highestColumn); $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(16);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
    } finally {
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }
}
