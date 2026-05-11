<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function export_profile_checkbox_value($value): string
{
    $normalized = strtoupper(trim((string) $value));
    if ($normalized === '') {
        return '';
    }

    if (in_array($normalized, ['FALSE', '0', 'NO', 'N'], true)) {
        return '';
    }

    return 'TRUE';
}

function export_profile_checkbox_boolean_text($value): string
{
    return export_profile_checkbox_value($value) === 'TRUE' ? 'TRUE' : 'FALSE';
}

function export_profile_fourps_status_value($value, string $target): string
{
    $normalized = strtoupper(trim((string) $value));
    $target = strtoupper($target);
    $graduatedValues = ['G', 'GRADUATED', 'GRADUATE'];
    $memberValues = ['M', 'MEMBER', 'ACTIVE', 'BENEFICIARY'];

    if ($target === 'G' && in_array($normalized, $graduatedValues, true)) {
        return 'TRUE';
    }

    if ($target === 'M' && in_array($normalized, $memberValues, true)) {
        return 'TRUE';
    }

    if ($target === 'G' && str_contains($normalized, 'GRADUATED')) {
        return 'TRUE';
    }

    if ($target === 'M' && str_contains($normalized, 'MEMBER')) {
        return 'TRUE';
    }

    if ($target === 'M' && export_profile_checkbox_value($value) === 'TRUE' && !in_array($normalized, $graduatedValues, true)) {
        return 'TRUE';
    }

    return 'FALSE';
}

function export_profile_sex_value($value, string $target): string
{
    $normalized = strtoupper(trim((string) $value));
    $target = strtoupper($target);

    return $normalized === $target ? 'TRUE' : 'FALSE';
}

function export_profile_pwd_boolean_text($value): string
{
    return trim((string) $value) !== '' ? 'TRUE' : 'FALSE';
}

function export_profile_name(array $row): string
{
    $firstName = trim((string) ($row['firstName'] ?? ''));
    $middleName = trim((string) ($row['middleName'] ?? ''));
    $lastName = trim((string) ($row['lastName'] ?? ''));
    $ext = trim((string) ($row['ext'] ?? ''));

    $parts = [];
    if ($firstName !== '') {
        $parts[] = strtoupper($firstName);
    }

    if ($middleName !== '') {
        $parts[] = strtoupper(substr($middleName, 0, 1)) . '.';
    }

    if ($lastName !== '') {
        $parts[] = strtoupper($lastName);
    }

    if ($ext !== '') {
        $parts[] = strtoupper($ext);
    }

    return trim(implode(' ', $parts));
}

function export_profile_build_workbook(mysqli $conn, int $year): Spreadsheet
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('PBs Profile ' . $year);

    $sheet->setCellValue('A1', 'Province');
    $sheet->setCellValue('B1', 'City/Municipality');
    $sheet->setCellValue('C1', 'Barangay');
    $sheet->setCellValue('D1', 'Project Site');
    $sheet->setCellValue('E1', "Full Name\n(First, MI, Surname)");
    $sheet->setCellValue('F1', 'Age');
    $sheet->setCellValue('G1', 'SEX');
    $sheet->setCellValue('I1', 'ELIGIBILITY CRITERIA');
    $sheet->setCellValue('N1', 'INFORMAL SECTORS');
    $sheet->setCellValue('Q1', 'VULNERABLE SECTORS');
    $sheet->setCellValue('U1', 'OTHERS');

    $sheet->mergeCells('G1:H1');
    $sheet->mergeCells('I1:M1');
    $sheet->mergeCells('N1:P1');
    $sheet->mergeCells('Q1:T1');
    $sheet->mergeCells('U1:Z1');

    $sheet->setCellValue('G2', 'Male');
    $sheet->setCellValue('H2', 'Female');
    $sheet->setCellValue('I2', 'LISTAHAN POOR 3');
    $sheet->setCellValue('J2', 'NON-LISTAHAN POOR 3');
    $sheet->setCellValue('K2', "4P'S BENEFICIARY");
    $sheet->setCellValue('M2', 'NOT ENLISTED BUT WITH MSWDO CERTIFICATION');
    $sheet->setCellValue('N2', 'FARMER');
    $sheet->setCellValue('O2', 'FISHERFOLK');
    $sheet->setCellValue('P2', 'OTHERS');
    $sheet->setCellValue('Q2', 'WOMEN');
    $sheet->setCellValue('R2', 'PWD');
    $sheet->setCellValue('S2', 'ELDERLY');
    $sheet->setCellValue('T2', "IP'S");
    $sheet->setCellValue('U2', 'SOLO PARENT');
    $sheet->setCellValue('W2', 'OUT OF SCHOOL YOUTH');
    $sheet->setCellValue('X2', 'YAKAP BAYAN/PWUDS');
    $sheet->setCellValue('Y2', 'DECOMMISIONED COMBATANT/ FORMER REBEL');
    $sheet->setCellValue('Z2', 'LGBTQIA+');

    foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $column) {
        $sheet->mergeCells($column . '1:' . $column . '3');
    }
    foreach (['G', 'H', 'I', 'J', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'W', 'X', 'Y', 'Z'] as $column) {
        $sheet->mergeCells($column . '2:' . $column . '3');
    }
    $sheet->mergeCells('K2:L2');
    $sheet->mergeCells('U2:V2');

    $sheet->setCellValue('K3', 'Member (M)');
    $sheet->setCellValue('L3', 'Graduated (G)');
    $sheet->setCellValue('U3', 'MALE');
    $sheet->setCellValue('V3', 'FEMALE');

    $stmt = $conn->prepare(
        'SELECT * FROM meb WHERE YEAR(time_stamp) = ? ORDER BY province ASC, lgu ASC, barangay ASC, lastName ASC, firstName ASC, middleName ASC, ext ASC'
    );
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare profile export query.');
    }

    $stmt->bind_param('i', $year);
    $stmt->execute();

    $rows = [];
    foreach (db_stmt_fetch_all_assoc($stmt) as $row) {
        $province = trim((string) ($row['province'] ?? ''));
        $municipality = trim((string) ($row['lgu'] ?? ''));
        $barangay = trim((string) ($row['barangay'] ?? ''));
        $projectSite = $barangay;
        $sex = strtoupper(trim((string) ($row['sex'] ?? '')));
        $soloParent = export_profile_checkbox_boolean_text($row['SP'] ?? '');

        $rows[] = [
            $province,
            $municipality,
            $barangay,
            $projectSite,
            export_profile_name($row),
            $row['age'] ?? '',
            export_profile_sex_value($sex, 'MALE'),
            export_profile_sex_value($sex, 'FEMALE'),
            export_profile_checkbox_boolean_text($row['nhts1'] ?? ''),
            export_profile_checkbox_boolean_text($row['nhts2'] ?? ''),
            export_profile_fourps_status_value($row['fourPs'] ?? '', 'M'),
            export_profile_fourps_status_value($row['fourPs'] ?? '', 'G'),
            export_profile_checkbox_boolean_text($row['nhts2'] ?? ''),
            export_profile_checkbox_boolean_text($row['F'] ?? ''),
            export_profile_checkbox_boolean_text($row['FF'] ?? ''),
            export_profile_checkbox_boolean_text($row['IS'] ?? ''),
            $sex === 'FEMALE' ? 'TRUE' : 'FALSE',
            export_profile_pwd_boolean_text($row['PWD'] ?? ''),
            export_profile_checkbox_boolean_text($row['SC'] ?? ''),
            export_profile_checkbox_boolean_text($row['IP'] ?? ''),
            ($soloParent === 'TRUE' && $sex === 'MALE') ? 'TRUE' : 'FALSE',
            ($soloParent === 'TRUE' && $sex === 'FEMALE') ? 'TRUE' : 'FALSE',
            export_profile_checkbox_boolean_text($row['OSY'] ?? ''),
            export_profile_checkbox_boolean_text($row['ybDs'] ?? ''),
            export_profile_checkbox_boolean_text($row['FR'] ?? ''),
            export_profile_checkbox_boolean_text($row['lgbtqia'] ?? ''),
        ];
    }
    $stmt->close();

    $lastDataRow = 4 + count($rows);
    $formulaLastRow = max($lastDataRow, 5);
    $b4Formula = '=SUM(--(FREQUENCY('
        . 'IF(SUBTOTAL(103,OFFSET(B5,ROW(B5:B' . $formulaLastRow . ')-ROW(B5),0)),'
        . 'MATCH(B5:B' . $formulaLastRow . ',B5:B' . $formulaLastRow . ',0)'
        . '),'
        . 'MATCH(B5:B' . $formulaLastRow . ',B5:B' . $formulaLastRow . ',0)'
        . ')>0))';

    $sheet->setCellValue('A4', 'GRAND TOTAL');
    $sheet->getCell('B4')->setValueExplicit($b4Formula, DataType::TYPE_FORMULA);
    $sheet->getCell('B4')->setFormulaAttributes(['t' => 'array', 'ref' => 'B4']);
    $sheet->setCellValue('C4', '=COUNTA(UNIQUE(FILTER(A5:A' . $formulaLastRow . '&"|"&B5:B' . $formulaLastRow . '&"|"&C5:C' . $formulaLastRow . ',SUBTOTAL(103,OFFSET(A5,ROW(A5:A' . $formulaLastRow . ')-ROW(A5),0,1)))))');
    $sheet->setCellValue('D4', '=COUNTA(UNIQUE(FILTER(A5:A' . $formulaLastRow . '&"|"&B5:B' . $formulaLastRow . '&"|"&D5:D' . $formulaLastRow . ',SUBTOTAL(103,OFFSET(A5,ROW(A5:A' . $formulaLastRow . ')-ROW(A5),0,1)))))');
    $sheet->setCellValue('E4', '=SUBTOTAL(3,E5:E' . $formulaLastRow . ')');

    foreach (['G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'] as $column) {
        $range = $column . '5:' . $column . $formulaLastRow;
        $sheet->setCellValue($column . '4', '=SUMPRODUCT(SUBTOTAL(103, OFFSET(' . $range . ', ROW(' . $range . ')-ROW(' . $column . '5), 0, 1)), --(' . $range . '=TRUE))');
    }

    $rowIndex = 5;
    foreach ($rows as $exportRow) {
        $sheet->fromArray([$exportRow], null, 'A' . $rowIndex);
        $rowIndex++;
    }

    $headerRange = 'A1:Z3';
    $totalRange = 'A4:Z4';
    $dataRange = $lastDataRow >= 5 ? 'A5:Z' . $lastDataRow : null;

    $sheet->getStyle($headerRange)->applyFromArray([
        'font' => ['bold' => true],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'D9EAF7'],
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '7A8CA5'],
            ],
        ],
    ]);

    $sheet->getStyle($totalRange)->applyFromArray([
        'font' => ['bold' => true],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FFF2CC'],
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '7A8CA5'],
            ],
        ],
    ]);

    if ($dataRange !== null) {
        $sheet->getStyle($dataRange)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'C9D2DC'],
                ],
            ],
        ]);

        $sheet->getStyle('A5:E' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    $sheet->freezePane('A5');
    $sheet->setAutoFilter('A4:Z4');

    $columnWidths = [
        'A' => 20, 'B' => 20, 'C' => 20, 'D' => 20, 'E' => 28, 'F' => 10,
        'G' => 10, 'H' => 10, 'I' => 16, 'J' => 18, 'K' => 14, 'L' => 14, 'M' => 20,
        'N' => 14, 'O' => 14, 'P' => 14, 'Q' => 12, 'R' => 12, 'S' => 12,
        'T' => 12, 'U' => 12, 'V' => 12, 'W' => 16, 'X' => 18, 'Y' => 20, 'Z' => 14,
    ];

    foreach ($columnWidths as $column => $width) {
        $sheet->getColumnDimension($column)->setWidth($width);
    }

    $sheet->getRowDimension(1)->setRowHeight(24);
    $sheet->getRowDimension(2)->setRowHeight(38);
    $sheet->getRowDimension(3)->setRowHeight(24);
    $sheet->getRowDimension(4)->setRowHeight(24);

    return $spreadsheet;
}

function export_profile_save_workbook(mysqli $conn, int $year, string $path): void
{
    $spreadsheet = export_profile_build_workbook($conn, $year);
    $writer = new Xlsx($spreadsheet);
    $writer->setPreCalculateFormulas(false);
    $writer->save($path);
    $spreadsheet->disconnectWorksheets();
}
