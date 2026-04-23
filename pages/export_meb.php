<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../export_style_helpers.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

auth_handle_page_access($conn);
auth_apply_security_headers();

if (!isset($_SESSION['selected_year'])) {
    http_response_code(400);
    echo "<p style='color: red;'>Fiscal year not selected. Please go back and select.</p>";
    exit;
}

$year = (int) $_SESSION['selected_year'];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Master List');

$sheet->setCellValue('A1', 'Master List of Eligible Beneficiaries');
$sheet->mergeCells('A1:AB1');
$sheet->setCellValue('A2', 'Fiscal Year: ' . $year);
$sheet->mergeCells('A2:AB2');

$headers = [
    'LAST NAME',
    'FIRST NAME',
    'MIDDLE NAME',
    'EXT.',
    'PUROK',
    'BARANGAY',
    'CITY / MUNICIPALITY',
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
    'LGBTQIA+'
];

$sheet->fromArray([$headers], null, 'A3');

$stmt = $conn->prepare(
    "SELECT
        lastName,
        firstName,
        middleName,
        ext,
        purok,
        barangay,
        lgu,
        province,
        birthDate,
        age,
        sex,
        civilStatus,
        nhts1,
        nhts2,
        fourPs,
        F,
        FF,
        `IS`,
        IP,
        SC,
        SP,
        LW,
        PW,
        PWD,
        OSY,
        FR,
        ybDs,
        lgbtqia
     FROM meb
     WHERE YEAR(time_stamp) = ?
     ORDER BY province ASC, lgu ASC, barangay ASC, lastName ASC, firstName ASC, middleName ASC, ext ASC, id ASC"
);

if (!$stmt) {
    throw new RuntimeException('Unable to prepare MEB export query: ' . $conn->error);
}

$stmt->bind_param('i', $year);
$stmt->execute();
$result = db_stmt_fetch_all_assoc($stmt);
$stmt->close();

$rowIndex = 4;
foreach ($result as $row) {
    $sheet->fromArray(
        [[
            $row['lastName'] ?? '',
            $row['firstName'] ?? '',
            $row['middleName'] ?? '',
            $row['ext'] ?? '',
            $row['purok'] ?? '',
            $row['barangay'] ?? '',
            $row['lgu'] ?? '',
            $row['province'] ?? '',
            $row['birthDate'] ?? '',
            $row['age'] ?? '',
            $row['sex'] ?? '',
            $row['civilStatus'] ?? '',
            $row['nhts1'] ?? '',
            $row['nhts2'] ?? '',
            $row['fourPs'] ?? '',
            $row['F'] ?? '',
            $row['FF'] ?? '',
            $row['IS'] ?? '',
            $row['IP'] ?? '',
            $row['SC'] ?? '',
            $row['SP'] ?? '',
            $row['LW'] ?? '',
            $row['PW'] ?? '',
            $row['PWD'] ?? '',
            $row['OSY'] ?? '',
            $row['FR'] ?? '',
            $row['ybDs'] ?? '',
            $row['lgbtqia'] ?? '',
        ]],
        null,
        'A' . $rowIndex
    );
    $rowIndex++;
}

$lastDataRow = $rowIndex - 1;

kodus_export_apply_uniform_style($spreadsheet, $sheet, [
    'document_title' => 'Master List of Eligible Beneficiaries',
    'title_range' => 'A1:AB2',
    'header_range' => 'A3:AB3',
    'data_range' => $lastDataRow >= 4 ? "A4:AB{$lastDataRow}" : null,
    'freeze_pane' => 'A4',
    'auto_filter' => 'A3:AB3',
    'left_align_ranges' => $lastDataRow >= 4 ? ["A4:H{$lastDataRow}", "L4:L{$lastDataRow}"] : [],
    'row_heights' => [1 => 30, 2 => 25, 3 => 55],
    'column_widths' => [
        'A' => 18, 'B' => 18, 'C' => 18, 'D' => 8, 'E' => 12, 'F' => 18, 'G' => 22, 'H' => 18,
        'I' => 14, 'J' => 8, 'K' => 10, 'L' => 14, 'M' => 24, 'N' => 28, 'O' => 18, 'P' => 12,
        'Q' => 14, 'R' => 14, 'S' => 14, 'T' => 14, 'U' => 14, 'V' => 14, 'W' => 14, 'X' => 20,
        'Y' => 18, 'Z' => 14, 'AA' => 22, 'AB' => 12,
    ],
    'integer_ranges' => $lastDataRow >= 4 ? ["J4:J{$lastDataRow}"] : [],
]);

kodus_export_stream_xlsx($spreadsheet, 'Master_list_of_Eligible_Beneficiaries_' . $year . '.xlsx');
