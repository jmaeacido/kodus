<?php
require '../vendor/autoload.php';
include('../config.php');

session_start();

if (!isset($_SESSION['selected_year'])) {
    echo "<p style='color: red;'>Fiscal year not selected. Please go back and select a year.</p>";
    exit;
}

$year = (int) $_SESSION['selected_year'];

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
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

function export_profile_sex_value($value, string $target): string
{
    $normalized = strtoupper(trim((string) $value));
    $target = strtoupper($target);

    return $normalized === $target ? 'TRUE' : 'FALSE';
}

function export_profile_pwd_value($value): string
{
    return trim((string) $value) !== '' ? 'TRUE' : '';
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
$sheet->setCellValue('M1', 'INFORMAL SECTORS');
$sheet->setCellValue('P1', 'VULNERABLE SECTORS');
$sheet->setCellValue('T1', 'OTHERS');

$sheet->mergeCells('G1:H1');
$sheet->mergeCells('I1:L1');
$sheet->mergeCells('M1:O1');
$sheet->mergeCells('P1:S1');
$sheet->mergeCells('T1:Y1');

$sheet->setCellValue('G2', 'Male');
$sheet->setCellValue('H2', 'Female');
$sheet->setCellValue('I2', 'LISTAHAN POOR 3');
$sheet->setCellValue('J2', 'NON-LISTAHAN POOR 3');
$sheet->setCellValue('K2', " 4P'S BENEFICIARY ");
$sheet->setCellValue('L2', 'NOT ENLISTED BUT WITH MSWDO CERTIFICATION');
$sheet->setCellValue('M2', 'FARMER');
$sheet->setCellValue('N2', 'FISHERFOLK');
$sheet->setCellValue('O2', 'OTHERS');
$sheet->setCellValue('P2', 'WOMEN');
$sheet->setCellValue('Q2', 'PWD');
$sheet->setCellValue('R2', 'ELDERLY');
$sheet->setCellValue('S2', "IP'S");
$sheet->setCellValue('T2', 'SOLO PARENT');
$sheet->setCellValue('V2', 'OUT OF SCHOOL YOUTH');
$sheet->setCellValue('W2', 'YAKAP BAYAN/PWUDS');
$sheet->setCellValue('X2', 'DECOMMISIONED COMBATANT/ FORMER REBEL');
$sheet->setCellValue('Y2', 'LGBTQIA+');

$sheet->mergeCells('A1:A3');
$sheet->mergeCells('B1:B3');
$sheet->mergeCells('C1:C3');
$sheet->mergeCells('D1:D3');
$sheet->mergeCells('E1:E3');
$sheet->mergeCells('F1:F3');
$sheet->mergeCells('G2:G3');
$sheet->mergeCells('H2:H3');
$sheet->mergeCells('I2:I3');
$sheet->mergeCells('J2:J3');
$sheet->mergeCells('K2:K3');
$sheet->mergeCells('L2:L3');
$sheet->mergeCells('M2:M3');
$sheet->mergeCells('N2:N3');
$sheet->mergeCells('O2:O3');
$sheet->mergeCells('P2:P3');
$sheet->mergeCells('Q2:Q3');
$sheet->mergeCells('R2:R3');
$sheet->mergeCells('S2:S3');
$sheet->mergeCells('T2:U2');
$sheet->mergeCells('V2:V3');
$sheet->mergeCells('W2:W3');
$sheet->mergeCells('X2:X3');
$sheet->mergeCells('Y2:Y3');

$sheet->setCellValue('T3', 'MALE');
$sheet->setCellValue('U3', 'FEMALE');

$query = "SELECT * FROM meb WHERE YEAR(time_stamp) = $year ORDER BY province ASC, lgu ASC, barangay ASC, lastName ASC, firstName ASC, middleName ASC, ext ASC";
$result = mysqli_query($conn, $query);

if (!$result) {
    die('Query failed: ' . mysqli_error($conn));
}

$rows = [];
$municipalities = [];
$barangays = [];
$projectSites = [];

while ($row = mysqli_fetch_assoc($result)) {
    $province = trim((string) ($row['province'] ?? ''));
    $municipality = trim((string) ($row['lgu'] ?? ''));
    $barangay = trim((string) ($row['barangay'] ?? ''));
    $projectSite = $barangay;
    $sex = strtoupper(trim((string) ($row['sex'] ?? '')));
    $listahanPoor3 = export_profile_checkbox_boolean_text($row['nhts1'] ?? '');
    $nonListahanPoor3 = export_profile_checkbox_boolean_text($row['nhts2'] ?? '');
    $fourPsBeneficiary = export_profile_checkbox_boolean_text($row['fourPs'] ?? '');
    $mswdoCertification = export_profile_checkbox_boolean_text($row['nhts2'] ?? '');
    $farmer = export_profile_checkbox_boolean_text($row['F'] ?? '');
    $fisherfolk = export_profile_checkbox_boolean_text($row['FF'] ?? '');
    $informalSectorOthers = export_profile_checkbox_boolean_text($row['IS'] ?? '');
    $women = $sex === 'FEMALE' ? 'TRUE' : 'FALSE';
    $pwd = export_profile_pwd_boolean_text($row['PWD'] ?? '');
    $elderly = export_profile_checkbox_boolean_text($row['SC'] ?? '');
    $ips = export_profile_checkbox_boolean_text($row['IP'] ?? '');
    $soloParent = export_profile_checkbox_boolean_text($row['SP'] ?? '');
    $soloParentMale = ($soloParent === 'TRUE' && $sex === 'MALE') ? 'TRUE' : 'FALSE';
    $soloParentFemale = ($soloParent === 'TRUE' && $sex === 'FEMALE') ? 'TRUE' : 'FALSE';
    $osy = export_profile_checkbox_boolean_text($row['OSY'] ?? '');
    $yakapBayanPwuds = export_profile_checkbox_boolean_text($row['ybDs'] ?? '');
    $formerRebel = export_profile_checkbox_boolean_text($row['FR'] ?? '');
    $lgbtqia = export_profile_checkbox_boolean_text($row['lgbtqia'] ?? '');

    $rows[] = [
        $province,
        $municipality,
        $barangay,
        $projectSite,
        export_profile_name($row),
        $row['age'] ?? '',
        export_profile_sex_value($sex, 'MALE'),
        export_profile_sex_value($sex, 'FEMALE'),
        $listahanPoor3,
        $nonListahanPoor3,
        $fourPsBeneficiary,
        $mswdoCertification,
        $farmer,
        $fisherfolk,
        $informalSectorOthers,
        $women,
        $pwd,
        $elderly,
        $ips,
        $soloParentMale,
        $soloParentFemale,
        $osy,
        $yakapBayanPwuds,
        $formerRebel,
        $lgbtqia,
    ];

    if ($municipality !== '') {
        $municipalities[strtoupper($municipality)] = true;
    }
    if ($barangay !== '') {
        $barangays[strtoupper($province . '|' . $municipality . '|' . $barangay)] = true;
    }
    if ($projectSite !== '') {
        $projectSites[strtoupper($province . '|' . $municipality . '|' . $projectSite)] = true;
    }
}

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
$sheet->setCellValue(
    'C4',
    '=COUNTA(UNIQUE(FILTER(A5:A' . $formulaLastRow . '&"|"&B5:B' . $formulaLastRow . '&"|"&C5:C' . $formulaLastRow . ',SUBTOTAL(103,OFFSET(A5,ROW(A5:A' . $formulaLastRow . ')-ROW(A5),0,1)))))'
);
$sheet->setCellValue(
    'D4',
    '=COUNTA(UNIQUE(FILTER(A5:A' . $formulaLastRow . '&"|"&B5:B' . $formulaLastRow . '&"|"&D5:D' . $formulaLastRow . ',SUBTOTAL(103,OFFSET(A5,ROW(A5:A' . $formulaLastRow . ')-ROW(A5),0,1)))))'
);
$sheet->setCellValue('E4', '=SUBTOTAL(3,E5:E' . $formulaLastRow . ')');

$formulaColumns = ['G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y'];
foreach ($formulaColumns as $column) {
    $range = $column . '5:' . $column . $formulaLastRow;
    $sheet->setCellValue(
        $column . '4',
        '=SUMPRODUCT(SUBTOTAL(103, OFFSET(' . $range . ', ROW(' . $range . ')-ROW(' . $column . '5), 0, 1)), --(' . $range . '=TRUE))'
    );
}

$rowIndex = 5;
foreach ($rows as $exportRow) {
    $sheet->fromArray([$exportRow], null, 'A' . $rowIndex);
    $rowIndex++;
}

$headerRange = 'A1:Y3';
$totalRange = 'A4:Y4';
$dataRange = $lastDataRow >= 5 ? 'A5:Y' . $lastDataRow : null;

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
$sheet->setAutoFilter('A4:Y4');

$columnWidths = [
    'A' => 20, 'B' => 20, 'C' => 20, 'D' => 20, 'E' => 28, 'F' => 10,
    'G' => 10, 'H' => 10, 'I' => 16, 'J' => 18, 'K' => 16, 'L' => 20,
    'M' => 14, 'N' => 14, 'O' => 14, 'P' => 12, 'Q' => 12, 'R' => 12,
    'S' => 12, 'T' => 12, 'U' => 12, 'V' => 16, 'W' => 18, 'X' => 20, 'Y' => 14,
];

foreach ($columnWidths as $column => $width) {
    $sheet->getColumnDimension($column)->setWidth($width);
}

$sheet->getRowDimension(1)->setRowHeight(24);
$sheet->getRowDimension(2)->setRowHeight(38);
$sheet->getRowDimension(3)->setRowHeight(24);
$sheet->getRowDimension(4)->setRowHeight(24);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Partner-Beneficiaries Profile ' . $year . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');
exit;
