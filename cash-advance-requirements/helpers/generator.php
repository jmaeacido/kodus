<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/project_variable_helpers.php';
require_once dirname(__DIR__, 2) . '/mebis-consolidator/helpers/parser.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function cash_advance_template_dir(): string
{
    return dirname(__DIR__, 2) . '/docs/Cash Advance Requirements';
}

function cash_advance_templates(): array
{
    return [
        'request_for_cash_advance' => [
            'label' => 'Request for Cash Advance',
            'filename' => 'Request for Cash Advance.xlsx',
            'output' => 'Request for Cash Advance.xlsx',
        ],
        'obligation_status_request' => [
            'label' => 'Obligation Status and Request',
            'filename' => 'Obligation Request and Status.xlsx',
            'output' => 'Obligation Status and Request.xlsx',
        ],
        'disbursement_voucher' => [
            'label' => 'Disbursement Voucher',
            'filename' => 'Disbursement Voucher.xlsx',
            'output' => 'Disbursement Voucher.xlsx',
        ],
        'payroll' => [
            'label' => 'Payroll',
            'filename' => 'Payroll.xlsx',
            'output' => 'Payroll.xlsx',
        ],
        'authority_to_pay' => [
            'label' => 'Authority to Pay',
            'filename' => 'AUTHORITY TO PAY.xlsx',
            'output' => 'Authority to Pay.xlsx',
        ],
        'time_tally_sheet' => [
            'label' => 'Time Tally Sheet',
            'filename' => 'Time Tally Sheet.xlsx',
            'output' => 'Time Tally Sheet.xlsx',
        ],
    ];
}

function cash_advance_default_particular_text(array $context): string
{
    return sprintf(
        'CA re: Risk Resiliency Program thru Cash for Training and Work (RRP-CFTW) in the Municipality of %s, %s',
        (string) ($context['municipality'] ?? ''),
        (string) ($context['province'] ?? '')
    );
}

function cash_advance_default_atp_statement(): string
{
    return 'The following eligible beneficiaries are hereby authorized to receive Cash Assistance through Risk Resiliency Program through Cash-for-Training and Work (RRP-CFTW) Project LAWA at BINHI (Local Adaptation to Water Access and Breaking Insufficiency through Nutritious Harvest for the Impoverished).';
}

function cash_advance_default_tts_certification(): string
{
    return 'This is to certify that the names listed above have rendered service under our supervision for 8 hours per day and for a number of days as indicated above on the conduct  of Cash for Training and Work Program of DSWD Caraga';
}

function cash_advance_particular_text(array $context): string
{
    $manual = $context['manual'] ?? [];
    $value = trim((string) ($manual['custom_particulars'] ?? ''));
    return $value !== '' ? cash_advance_replace_context_tokens($value, $context) : cash_advance_default_particular_text($context);
}

function cash_advance_atp_statement(array $context): string
{
    $manual = $context['manual'] ?? [];
    $value = trim((string) ($manual['custom_atp_statement'] ?? ''));
    return $value !== '' ? cash_advance_replace_context_tokens($value, $context) : cash_advance_default_atp_statement();
}

function cash_advance_tts_certification(array $context): string
{
    $manual = $context['manual'] ?? [];
    $value = trim((string) ($manual['custom_tts_certification'] ?? ''));
    return $value !== '' ? cash_advance_replace_context_tokens($value, $context) : cash_advance_default_tts_certification();
}

function cash_advance_replace_context_tokens(string $value, array $context): string
{
    return strtr($value, [
        '<Province>' => (string) ($context['province'] ?? ''),
        '<PROVINCE>' => strtoupper((string) ($context['province'] ?? '')),
        '<Municipality>' => (string) ($context['municipality'] ?? ''),
        '<MUNICIPALITY>' => strtoupper((string) ($context['municipality'] ?? '')),
        '{province}' => (string) ($context['province'] ?? ''),
        '{municipality}' => (string) ($context['municipality'] ?? ''),
    ]);
}

function cash_advance_replace_cells_containing(Spreadsheet $spreadsheet, string $needle, string $replacement): void
{
    if ($needle === '') {
        return;
    }

    foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
        foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
            $cell = $sheet->getCell($coordinate);
            $value = $cell->getValue();
            if (!is_string($value) || strpos($value, $needle) === false) {
                continue;
            }

            $cell->setValue(str_replace($needle, $replacement, $value));
        }
    }
}

function cash_advance_manual_fields(): array
{
    return [
        'do_sdo_payee' => [
            'label' => 'Name of DO/SDO / Payee',
            'template' => 'Shared Fields',
            'context' => 'Used for Request for Cash Advance C8, OSR D6, DV E11, and the Payroll summary DO/SDO name.',
            'type' => 'text',
        ],
        'request_ca_c10' => [
            'label' => 'Position of DO/SDO',
            'template' => 'Request for Cash Advance',
            'context' => 'Optional manual field from cell C10.',
            'type' => 'text',
        ],
        'dv_number' => [
            'label' => 'Disbursement Voucher Number',
            'template' => 'Disbursement Voucher',
            'context' => 'Optional manual field from cell AB6.',
            'type' => 'text',
        ],
        'time_tally_mswdo' => [
            'label' => 'MSWDO',
            'template' => 'Time Tally Sheet',
            'context' => 'Optional manual field from the signature area.',
            'type' => 'text',
        ],
    ];
}

function cash_advance_location_options(mysqli $conn, ?int $year = null): array
{
    if ($year !== null && $year > 0) {
        $stmt = $conn->prepare("
            SELECT province AS province_name, lgu AS municipality_name, COUNT(*) AS beneficiary_count, COUNT(DISTINCT barangay) AS barangay_count
            FROM meb
            WHERE YEAR(time_stamp) = ?
              AND TRIM(province) <> ''
              AND TRIM(lgu) <> ''
            GROUP BY province, lgu
            ORDER BY province ASC, lgu ASC
        ");
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $year);
        $stmt->execute();
        $rows = db_stmt_fetch_all_assoc($stmt);
        $stmt->close();

        $items = [];
        foreach ($rows as $row) {
            $province = trim((string) ($row['province_name'] ?? ''));
            $municipality = trim((string) ($row['municipality_name'] ?? ''));
            if ($province === '' || $municipality === '') {
                continue;
            }

            $count = (int) ($row['beneficiary_count'] ?? 0);
            $barangayCount = (int) ($row['barangay_count'] ?? 0);
            $items[] = [
                'province' => $province,
                'municipality' => $municipality,
                'value' => base64_encode($province . '|' . $municipality),
                'label' => sprintf('%s, %s (%s beneficiaries, %s barangays)', $municipality, $province, number_format($count), number_format($barangayCount)),
                'beneficiary_count' => $count,
                'barangay_count' => $barangayCount,
            ];
        }

        return $items;
    }

    $result = $conn->query("
        SELECT DISTINCT p.province_name, m.municipality_name
        FROM municipality m
        INNER JOIN provinces p ON p.id = m.province_id
        WHERE TRIM(p.province_name) <> ''
          AND TRIM(m.municipality_name) <> ''
        ORDER BY p.province_name ASC, m.municipality_name ASC
    ");
    if (!($result instanceof mysqli_result)) {
        return [];
    }

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $province = trim((string) ($row['province_name'] ?? ''));
        $municipality = trim((string) ($row['municipality_name'] ?? ''));
        if ($province === '' || $municipality === '') {
            continue;
        }

        $items[] = [
            'province' => $province,
            'municipality' => $municipality,
            'value' => base64_encode($province . '|' . $municipality),
            'label' => $municipality . ', ' . $province,
        ];
    }
    $result->free();

    return $items;
}

function cash_advance_decode_location(string $encoded, array $allowedLocations): array
{
    $decoded = base64_decode($encoded, true);
    if (!is_string($decoded) || strpos($decoded, '|') === false) {
        throw new RuntimeException('Please select a valid municipality.');
    }

    [$province, $municipality] = array_map('trim', explode('|', $decoded, 2));
    foreach ($allowedLocations as $location) {
        if (
            strcasecmp((string) ($location['province'] ?? ''), $province) === 0
            && strcasecmp((string) ($location['municipality'] ?? ''), $municipality) === 0
        ) {
            return [
                'province' => (string) $location['province'],
                'municipality' => (string) $location['municipality'],
            ];
        }
    }

    throw new RuntimeException('Please select a valid municipality.');
}

function cash_advance_build_dataset(mysqli $conn, string $province, string $municipality, int $year): array
{
    $stmt = $conn->prepare("
        SELECT id, lastName, firstName, middleName, ext, barangay, lgu, province
        FROM meb
        WHERE YEAR(time_stamp) = ?
          AND province = ?
          AND lgu = ?
        ORDER BY barangay ASC, lastName ASC, firstName ASC, middleName ASC, ext ASC, id ASC
    ");
    if (!$stmt) {
        throw new RuntimeException('Unable to load MEB records for the selected municipality.');
    }

    $stmt->bind_param('iss', $year, $province, $municipality);
    $stmt->execute();
    $rows = db_stmt_fetch_all_assoc($stmt);
    $stmt->close();

    if ($rows === []) {
        throw new RuntimeException('No MEB beneficiaries were found for the selected municipality and fiscal year.');
    }

    $dailyWageRate = project_variable_get_number($conn, 'daily_wage_rate', $year, 0);
    $payoutDays = (int) round(project_variable_get_number($conn, 'working_days', $year, 20));
    $payoutDays = $payoutDays > 0 ? $payoutDays : 20;
    if ($dailyWageRate <= 0) {
        throw new RuntimeException('Missing project variable for daily wage rate in the selected fiscal year.');
    }

    $beneficiaryRate = (float) $dailyWageRate * $payoutDays;
    $barangays = [];
    foreach ($rows as $row) {
        $barangay = trim((string) ($row['barangay'] ?? ''));
        if ($barangay === '') {
            $barangay = 'Unspecified Barangay';
        }

        if (!isset($barangays[$barangay])) {
            $barangays[$barangay] = [
                'name' => $barangay,
                'beneficiaries' => [],
                'count' => 0,
                'amount' => 0.0,
            ];
        }

        $barangays[$barangay]['beneficiaries'][] = [
            'id' => (int) ($row['id'] ?? 0),
            'last_name' => trim((string) ($row['lastName'] ?? '')),
            'first_name' => trim((string) ($row['firstName'] ?? '')),
            'middle_name' => trim((string) ($row['middleName'] ?? '')),
            'extension' => trim((string) ($row['ext'] ?? '')),
            'barangay' => $barangay,
        ];
        $barangays[$barangay]['count']++;
        $barangays[$barangay]['amount'] = $barangays[$barangay]['count'] * $beneficiaryRate;
    }

    $items = array_values($barangays);
    $totalBeneficiaries = count($rows);
    $totalAmount = $totalBeneficiaries * $beneficiaryRate;

    return [
        'year' => $year,
        'province' => $province,
        'municipality' => $municipality,
        'daily_wage_rate' => $dailyWageRate,
        'payout_days' => $payoutDays,
        'beneficiary_rate' => $beneficiaryRate,
        'total_beneficiaries' => $totalBeneficiaries,
        'total_amount' => $totalAmount,
        'barangays' => $items,
        'barangay_names' => array_map(static fn(array $item): string => (string) $item['name'], $items),
    ];
}

function cash_advance_build_dataset_from_rows(
    mysqli $conn,
    array $rows,
    string $province,
    string $municipality,
    int $year,
    ?float $manualBeneficiaryRate = null
): array
{
    if ($rows === []) {
        throw new RuntimeException('No beneficiaries were found in the uploaded workbook.');
    }

    if ($manualBeneficiaryRate !== null) {
        if ($manualBeneficiaryRate <= 0) {
            throw new RuntimeException('Please enter a valid amount per beneficiary for the uploaded workbook.');
        }

        $dailyWageRate = $manualBeneficiaryRate;
        $payoutDays = 1;
        $beneficiaryRate = $manualBeneficiaryRate;
    } else {
        $dailyWageRate = project_variable_get_number($conn, 'daily_wage_rate', $year, 0);
        $payoutDays = (int) round(project_variable_get_number($conn, 'working_days', $year, 20));
        $payoutDays = $payoutDays > 0 ? $payoutDays : 20;
        if ($dailyWageRate <= 0) {
            throw new RuntimeException('Missing project variable for daily wage rate in the selected fiscal year.');
        }

        $beneficiaryRate = (float) $dailyWageRate * $payoutDays;
    }

    $barangays = [];
    foreach ($rows as $row) {
        $barangay = trim((string) ($row['barangay'] ?? ''));
        if ($barangay === '') {
            $barangay = 'Unspecified Barangay';
        }

        if (!isset($barangays[$barangay])) {
            $barangays[$barangay] = [
                'name' => $barangay,
                'beneficiaries' => [],
                'count' => 0,
                'amount' => 0.0,
            ];
        }

        $barangays[$barangay]['beneficiaries'][] = [
            'id' => (int) ($row['id'] ?? 0),
            'last_name' => trim((string) ($row['last_name'] ?? '')),
            'first_name' => trim((string) ($row['first_name'] ?? '')),
            'middle_name' => trim((string) ($row['middle_name'] ?? '')),
            'extension' => trim((string) ($row['extension'] ?? '')),
            'barangay' => $barangay,
        ];
        $barangays[$barangay]['count']++;
        $barangays[$barangay]['amount'] = $barangays[$barangay]['count'] * $beneficiaryRate;
    }

    $items = array_values($barangays);
    $totalBeneficiaries = count($rows);
    $totalAmount = $totalBeneficiaries * $beneficiaryRate;

    return [
        'year' => $year,
        'province' => $province,
        'municipality' => $municipality,
        'daily_wage_rate' => $dailyWageRate,
        'payout_days' => $payoutDays,
        'beneficiary_rate' => $beneficiaryRate,
        'total_beneficiaries' => $totalBeneficiaries,
        'total_amount' => $totalAmount,
        'barangays' => $items,
        'barangay_names' => array_map(static fn(array $item): string => (string) $item['name'], $items),
    ];
}

function cash_advance_validate_uploaded_meb_file(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Please upload a valid workbook.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('The uploaded workbook was not received correctly.');
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, ['xlsx', 'xlsm'], true)) {
        throw new RuntimeException('The uploaded workbook must be an .xlsx or .xlsm file.');
    }

    $detectedMime = function_exists('security_detect_upload_mime')
        ? security_detect_upload_mime($tmpName)
        : 'application/octet-stream';
    $allowedMimeByExtension = [
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
        ],
        'xlsm' => [
            'application/vnd.ms-excel.sheet.macroEnabled.12',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
        ],
    ];

    if (!in_array($detectedMime, $allowedMimeByExtension[$extension] ?? [], true)) {
        throw new RuntimeException('The uploaded workbook file type is not allowed.');
    }

    return [
        'tmp_name' => $tmpName,
        'name' => $originalName,
    ];
}

function cash_advance_simple_header_aliases(): array
{
    return [
        'last_name' => [
            'last name',
            'lastname',
            'surname',
            'family name',
            'familyname',
            'beneficiary last name',
            'beneficiary surname',
        ],
        'first_name' => [
            'first name',
            'firstname',
            'given name',
            'givenname',
            'forename',
            'beneficiary first name',
            'beneficiary given name',
        ],
        'middle_name' => [
            'middle name',
            'middlename',
            'mid name',
            'mid',
            'mid.',
            'middle initial',
            'mid initial',
            'mid. initial',
            'mi',
            'm i',
            'beneficiary middle name',
        ],
        'extension' => [
            'ext',
            'ext name',
            'extname',
            'ext.',
            'extension',
            'extension name',
            'name extension',
            'suffix',
            'name suffix',
            'qualifier',
        ],
        'barangay' => [
            'barangay',
            'barangay name',
            'brgy',
            'brgy name',
            'brgy.',
            'brgy. name',
            'bgy',
            'bgy name',
            'village',
            'community',
            'area barangay',
        ],
        'municipality' => [
            'municipality',
            'municipality name',
            'lgu',
            'lgu name',
            'city',
            'city name',
            'city municipality',
            'municipality city',
            'municipality city name',
            'mun city',
            'municity',
            'mun',
            'town',
            'local government unit',
        ],
        'province' => [
            'province',
            'province name',
            'prov',
            'prov.',
            'provincial',
            'area province',
        ],
    ];
}

function cash_advance_find_simple_header_map(Worksheet $sheet): ?array
{
    $aliases = cash_advance_simple_header_aliases();
    $required = ['last_name', 'first_name', 'middle_name', 'extension', 'barangay', 'municipality', 'province'];

    for ($row = 1; $row <= min(30, $sheet->getHighestDataRow()); $row++) {
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

    return null;
}

function cash_advance_find_simple_sheet($spreadsheet): array
{
    foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
        $headerMap = cash_advance_find_simple_header_map($sheet);
        if ($headerMap !== null) {
            return [$sheet, $headerMap];
        }
    }

    throw new RuntimeException('The uploaded workbook must contain an MEB sheet or columns for Last Name, First Name, Middle Name, Ext., Barangay, Municipality, and Province.');
}

function cash_advance_detect_simple_sheet_name(string $path, string $originalName): ?string
{
    $sheetNames = mebis_candidate_sheet_names($path, $originalName);
    if ($sheetNames === []) {
        return null;
    }

    foreach ($sheetNames as $sheetName) {
        $spreadsheet = mebis_load_spreadsheet($path, $originalName, [(string) $sheetName], true);

        try {
            $sheet = mebis_find_sheet_by_name($spreadsheet, (string) $sheetName);
            if ($sheet instanceof Worksheet && cash_advance_find_simple_header_map($sheet) !== null) {
                return (string) $sheetName;
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    return null;
}

function cash_advance_choose_uploaded_meb_sheet_name(string $path, string $originalName): ?string
{
    $sheetNames = mebis_candidate_sheet_names($path, $originalName);
    foreach ($sheetNames as $sheetName) {
        if (strcasecmp((string) $sheetName, 'MEB') === 0) {
            return (string) $sheetName;
        }
    }

    return $sheetNames === [] ? mebis_detect_uploaded_meb_sheet($path, $originalName) : null;
}

function cash_advance_build_dataset_from_uploaded_meb(mysqli $conn, array $file, int $year, float $manualBeneficiaryRate): array
{
    $upload = cash_advance_validate_uploaded_meb_file($file);
    $path = (string) $upload['tmp_name'];
    $originalName = (string) $upload['name'];

    $targetSheetName = cash_advance_choose_uploaded_meb_sheet_name($path, $originalName);
    $isMebWorkbook = $targetSheetName !== null;
    if (!$isMebWorkbook) {
        $targetSheetName = cash_advance_detect_simple_sheet_name($path, $originalName);
    }

    $spreadsheet = mebis_load_spreadsheet($path, $originalName, $targetSheetName !== null ? [$targetSheetName] : null, true);

    try {
        if ($isMebWorkbook) {
            $sheet = mebis_find_sheet_by_name($spreadsheet, (string) $targetSheetName);
            if ($sheet === null) {
                throw new RuntimeException(sprintf('The workbook "%s" does not contain an MEB sheet.', $originalName));
            }

            $province = mebis_location_from_row($sheet, 2, ['province']);
            $municipality = mebis_location_from_row($sheet, 3, ['municipality', 'city']);
            if ($province === '' || $municipality === '') {
                throw new RuntimeException(sprintf('The workbook "%s" is missing province or municipality labels.', $originalName));
            }

            $headerMap = mebis_find_header_map($sheet);
            if (($headerMap['name_mode'] ?? 'split') !== 'split') {
                throw new RuntimeException(sprintf('The workbook "%s" must have separate last name, first name, middle name, and ext columns.', $originalName));
            }
        } else {
            if ($targetSheetName !== null) {
                $sheet = mebis_find_sheet_by_name($spreadsheet, (string) $targetSheetName);
                $headerMap = $sheet instanceof Worksheet ? cash_advance_find_simple_header_map($sheet) : null;
                if (!$sheet instanceof Worksheet || $headerMap === null) {
                    throw new RuntimeException('The uploaded workbook must contain an MEB sheet or columns for Last Name, First Name, Middle Name, Ext., Barangay, Municipality, and Province.');
                }
            } else {
                [$sheet, $headerMap] = cash_advance_find_simple_sheet($spreadsheet);
            }

            $province = '';
            $municipality = '';
        }

        $rows = [];
        $highestRow = $sheet->getHighestDataRow();
        $dataStartRow = ((int) $headerMap['header_row']) + 1;

        for ($row = $dataStartRow; $row <= $highestRow; $row++) {
            if (mebis_is_hidden_row($sheet, $row)) {
                continue;
            }

            $highestColumn = $sheet->getHighestDataColumn($row);
            $rowValues = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, false)[0] ?? [];
            $entryNumber = $isMebWorkbook ? mebis_row_value($rowValues, $headerMap, 'entry_no') : (string) (count($rows) + 1);
            $lastName = mebis_row_value($rowValues, $headerMap, 'last_name');
            $firstName = mebis_row_value($rowValues, $headerMap, 'first_name');
            $middleName = mebis_row_value($rowValues, $headerMap, 'middle_name');
            $extensionName = $isMebWorkbook
                ? mebis_row_value($rowValues, $headerMap, 'extName')
                : mebis_row_value($rowValues, $headerMap, 'extension');
            $barangayName = $isMebWorkbook
                ? mebis_row_value($rowValues, $headerMap, 'barangay_name')
                : mebis_row_value($rowValues, $headerMap, 'barangay');

            if ($isMebWorkbook && $entryNumber === '' && $lastName === '' && $firstName === '') {
                continue;
            }

            if (!$isMebWorkbook && $lastName === '' && $firstName === '' && $middleName === '' && $extensionName === '' && $barangayName === '') {
                continue;
            }

            if ($isMebWorkbook && !is_numeric($entryNumber)) {
                continue;
            }

            if (!$isMebWorkbook && ($lastName === '' || $firstName === '')) {
                continue;
            }

            $rowProvince = $isMebWorkbook ? $province : mebis_row_value($rowValues, $headerMap, 'province');
            $rowMunicipality = $isMebWorkbook ? $municipality : mebis_row_value($rowValues, $headerMap, 'municipality');
            if (!$isMebWorkbook) {
                if ($rowMunicipality === '') {
                    throw new RuntimeException('A beneficiary row in the uploaded workbook is missing municipality.');
                }

                if ($rowProvince === '') {
                    throw new RuntimeException('A beneficiary row in the uploaded workbook is missing province.');
                }

                if ($barangayName === '') {
                    throw new RuntimeException('A beneficiary row in the uploaded workbook is missing barangay.');
                }

                if ($municipality === '') {
                    $municipality = $rowMunicipality;
                } elseif (strcasecmp($municipality, $rowMunicipality) !== 0) {
                    throw new RuntimeException('The uploaded workbook must contain beneficiaries for one municipality only.');
                }

                if ($province === '') {
                    $province = $rowProvince;
                } elseif (strcasecmp($province, $rowProvince) !== 0) {
                    throw new RuntimeException('The uploaded workbook must contain beneficiaries for one province only.');
                }
            }

            $rows[] = [
                'id' => is_numeric($entryNumber) ? (int) $entryNumber : count($rows) + 1,
                'last_name' => $lastName,
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'extension' => $extensionName,
                'barangay' => $barangayName,
            ];
        }

        if (!$isMebWorkbook && $municipality === '') {
            throw new RuntimeException('The uploaded workbook did not contain a municipality value.');
        }

        if (!$isMebWorkbook && $province === '') {
            throw new RuntimeException('The uploaded workbook did not contain a province value.');
        }

        return cash_advance_build_dataset_from_rows($conn, $rows, $province, $municipality, $year, $manualBeneficiaryRate);
    } finally {
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }
}

function cash_advance_format_date_range(?string $from, ?string $to): string
{
    $from = trim((string) $from);
    $to = trim((string) $to);
    if ($from === '' || $from === '0000-00-00') {
        return '';
    }

    $fromTimestamp = strtotime($from);
    $fromFormatted = $fromTimestamp === false ? $from : date('F j, Y', $fromTimestamp);
    if ($to === '' || $to === '0000-00-00' || $to === $from) {
        return $fromFormatted;
    }

    $toTimestamp = strtotime($to);
    $toFormatted = $toTimestamp === false ? $to : date('F j, Y', $toTimestamp);
    return $fromFormatted . ' - ' . $toFormatted;
}

function cash_advance_payout_schedule_date(mysqli $conn, string $province, string $municipality, int $year): string
{
    $stmt = $conn->prepare("
        SELECT
            MIN(payout_schedule_from) AS payout_from,
            MAX(COALESCE(payout_schedule_to, payout_schedule_from)) AS payout_to
        FROM program_activity_metadata
        WHERE fiscal_year = ?
          AND province = ?
          AND municipality = ?
          AND payout_schedule_from IS NOT NULL
    ");
    if (!$stmt) {
        return '';
    }

    $stmt->bind_param('iss', $year, $province, $municipality);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt) ?: [];
    $stmt->close();

    return cash_advance_format_date_range($row['payout_from'] ?? '', $row['payout_to'] ?? '');
}

function cash_advance_latest_implementation_date(mysqli $conn, string $province, string $municipality): string
{
    $stmt = $conn->prepare("
        SELECT
            COALESCE(
                MAX(check_issuance_date),
                MAX(payout_schedule_from),
                MAX(payout_schedule_to),
                MAX(stage3_end_date),
                MAX(stage2_end_date),
                MAX(stage1_end_date),
                MAX(updated_at)
            ) AS implementation_date
        FROM program_activity_metadata
        WHERE province = ?
          AND municipality = ?
    ");
    if (!$stmt) {
        return '';
    }

    $stmt->bind_param('ss', $province, $municipality);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt) ?: [];
    $stmt->close();

    $value = trim((string) ($row['implementation_date'] ?? ''));
    if ($value === '' || $value === '0000-00-00') {
        return '';
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? $value : date('F j, Y', $timestamp);
}

function cash_advance_post_value(array $input, string $key): string
{
    return trim((string) ($input[$key] ?? ''));
}

function cash_advance_format_optional_date(string $value): string
{
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? $value : date('F j, Y', $timestamp);
}

function cash_advance_download_basename(string $municipality): string
{
    $name = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim($municipality));
    return trim((string) $name, '_') ?: 'municipality';
}

function cash_advance_user_initials(array $source): string
{
    $parts = [
        (string) ($source['first_name'] ?? ''),
        (string) ($source['middle_name'] ?? ''),
        (string) ($source['last_name'] ?? ''),
    ];

    $initials = '';
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $initials .= strtolower(mb_substr($part, 0, 1));
    }

    return $initials;
}

function cash_advance_money(float $amount): string
{
    return 'Php' . number_format($amount, 2);
}

function cash_advance_beneficiary_full_name(array $beneficiary): string
{
    $lastName = trim((string) ($beneficiary['last_name'] ?? ''));
    $firstName = trim((string) ($beneficiary['first_name'] ?? ''));
    $middleName = trim((string) ($beneficiary['middle_name'] ?? ''));
    $extension = trim((string) ($beneficiary['extension'] ?? ''));

    $given = trim(implode(' ', array_filter([$firstName, $middleName, $extension], static fn(string $value): bool => $value !== '')));
    if ($lastName !== '' && $given !== '') {
        return strtoupper($lastName . ', ' . $given);
    }

    return strtoupper(trim($lastName . ' ' . $given));
}

function cash_advance_number_to_words(int $number): string
{
    $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    if ($number < 20) {
        return $ones[$number];
    }
    if ($number < 100) {
        return trim($tens[intdiv($number, 10)] . ' ' . $ones[$number % 10]);
    }
    if ($number < 1000) {
        return trim($ones[intdiv($number, 100)] . ' hundred ' . cash_advance_number_to_words($number % 100));
    }

    foreach ([1000000000 => 'billion', 1000000 => 'million', 1000 => 'thousand'] as $value => $label) {
        if ($number >= $value) {
            return trim(cash_advance_number_to_words(intdiv($number, $value)) . ' ' . $label . ' ' . cash_advance_number_to_words($number % $value));
        }
    }

    return '';
}

function cash_advance_amount_in_words(float $amount): string
{
    $whole = (int) floor($amount);
    $cents = (int) round(($amount - $whole) * 100);
    $words = $whole > 0 ? cash_advance_number_to_words($whole) : 'zero';
    $result = ucwords($words) . ' Pesos';
    if ($cents > 0) {
        $result .= ' and ' . str_pad((string) $cents, 2, '0', STR_PAD_LEFT) . '/100';
    }
    return $result . ' Only';
}

function cash_advance_generation_date_label(): string
{
    return date('F j, Y');
}

function cash_advance_ordinal_day(int $day): string
{
    if ($day % 100 >= 11 && $day % 100 <= 13) {
        return $day . 'th';
    }

    return $day . match ($day % 10) {
        1 => 'st',
        2 => 'nd',
        3 => 'rd',
        default => 'th',
    };
}

function cash_advance_generation_day_phrase(): string
{
    return cash_advance_ordinal_day((int) date('j')) . ' day of ' . date('F Y');
}

function cash_advance_replace_cell_markers(string $value, array $context, ?string $barangay = null): string
{
    $manual = $context['manual'] ?? [];
    $generatedDate = cash_advance_generation_date_label();
    $amountWords = cash_advance_amount_in_words((float) ($context['total_amount'] ?? 0));

    $map = [
        '<Province>' => $context['province'],
        '<PROVINCE>' => strtoupper((string) $context['province']),
        '<Municipality>' => $context['municipality'],
        '<MUNICIPALITY>' => strtoupper((string) $context['municipality']),
        '<MUNICIPALITY' => strtoupper((string) $context['municipality']),
        '<BARANGAY>' => $barangay ?: '',
        '<BARANGAY' => $barangay ?: '',
        '<Manual Input>' => '',
        '<MANUAL INPUT>' => '',
        '<MANUAL INPUT AS `MSWDO`>' => (string) ($manual['time_tally_mswdo'] ?? ''),
        '<MANUAL INPUT IF NO DATE ENTERED IN THE IMPLEMENTATION STATUS>' => $generatedDate,
        '<PLACE THE AMOUNT IN WORDS HERE>' => $amountWords,
        '<GENERATION DATE>' => $generatedDate,
        '<GENERATION DATE (23RD day of February)>' => cash_advance_generation_day_phrase(),
    ];

    $result = strtr($value, $map);
    $result = str_replace(
        'CA re: Risk Resiliency Program thru Cash for Training and Work (RRP-CFTW) in the Municipality of ' . (string) ($context['municipality'] ?? '') . ', ' . (string) ($context['province'] ?? ''),
        cash_advance_particular_text($context),
        $result
    );
    $result = str_replace(
        cash_advance_default_atp_statement(),
        cash_advance_atp_statement($context),
        $result
    );
    $result = str_replace(
        cash_advance_default_tts_certification(),
        cash_advance_tts_certification($context),
        $result
    );
    $result = str_replace(
        'Date :  ',
        'Date : ' . $generatedDate,
        $result
    );

    return $result;
}

function cash_advance_apply_direct_manual_cells(Spreadsheet $spreadsheet, string $templateKey, array $context): void
{
    $manual = $context['manual'] ?? [];
    $doSdoPayee = (string) (
        ($manual['do_sdo_payee'] ?? '')
        ?: ($manual['request_ca_c8'] ?? '')
        ?: ($manual['obs_d6'] ?? '')
        ?: ($manual['dv_e11'] ?? '')
        ?: ($manual['payroll_sdo_name'] ?? '')
    );
    $generatedDate = cash_advance_generation_date_label();
    $totalAmount = (float) ($context['total_amount'] ?? 0);

    if ($templateKey === 'request_for_cash_advance') {
        $sheet = $spreadsheet->getSheet(0);
        $sheet->setCellValue('C8', $doSdoPayee);
        $sheet->setCellValue('C10', (string) ($manual['request_ca_c10'] ?? ''));
        $sheet->setCellValue('D16', cash_advance_particular_text($context));
        $sheet->setCellValue('D20', (string) ($context['payout_date'] ?? ''));
        $sheet->setCellValue('D22', cash_advance_amount_in_words($totalAmount));
        $sheet->setCellValue('C25', '(' . cash_advance_money($totalAmount) . ')');
    } elseif ($templateKey === 'obligation_status_request') {
        $sheet = $spreadsheet->getSheet(0);
        $sheet->setCellValue('D6', $doSdoPayee);
        $sheet->setCellValue('K4', 'Date : ' . $generatedDate);
        $sheet->setCellValue('D14', cash_advance_particular_text($context));
        $sheet->setCellValue('L14', cash_advance_money($totalAmount));
        $sheet->setCellValue('L22', cash_advance_money($totalAmount));
    } elseif ($templateKey === 'disbursement_voucher') {
        $sheet = $spreadsheet->getSheet(0);
        $sheet->setCellValue('AB5', 'Date : ' . $generatedDate);
        $sheet->setCellValue('AB6', 'DV No. : ' . (string) ($manual['dv_number'] ?? ''));
        $sheet->setCellValue('E11', $doSdoPayee);
        $sheet->setCellValue('A16', cash_advance_particular_text($context));
        $sheet->setCellValue('AB16', $totalAmount);
        $sheet->setCellValue('AB22', $totalAmount);
    } elseif ($templateKey === 'payroll') {
        cash_advance_clear_payroll_payout_dates($spreadsheet, $context);
        $summarySheet = $spreadsheet->getSheetByName('SUMMARY PAGE');
        if ($summarySheet instanceof Worksheet) {
            $approvedRow = cash_advance_find_label_row($summarySheet, 'Approved for Payment') ?: 28;
            $summarySheet->setCellValue('I' . $approvedRow, $doSdoPayee);
        }
    } elseif ($templateKey === 'authority_to_pay') {
        cash_advance_replace_cells_containing($spreadsheet, cash_advance_default_atp_statement(), cash_advance_atp_statement($context));
    } elseif ($templateKey === 'time_tally_sheet') {
        cash_advance_replace_cells_containing($spreadsheet, cash_advance_default_tts_certification(), cash_advance_tts_certification($context));
        if (trim((string) ($manual['time_tally_mswdo'] ?? '')) === '') {
            cash_advance_apply_time_tally_mswdo_border($spreadsheet);
        }
    }
}

function cash_advance_apply_generic_replacements(Spreadsheet $spreadsheet, array $context, array $barangays): void
{
    foreach ($spreadsheet->getWorksheetIterator() as $sheetIndex => $sheet) {
        $sheetTitle = $sheet->getTitle();
        $sheetBarangay = '';
        $sheetBarangayNumber = 0;
        if (preg_match('/^<BARANGAY\s+(\d+)>$/i', $sheetTitle, $match)) {
            $sheetBarangayNumber = (int) $match[1];
            $sheetBarangay = $barangays[$sheetBarangayNumber - 1] ?? '';
        }

        foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
            $cell = $sheet->getCell($coordinate);
            $value = $cell->getValue();
            if (!is_string($value) || strpos($value, '<') === false) {
                continue;
            }

            $replacement = cash_advance_replace_cell_markers($value, $context, $sheetBarangay);
            for ($index = 1; $index <= 50; $index++) {
                $replacement = str_replace('<BARANGAY ' . $index . '>', $barangays[$index - 1] ?? '', $replacement);
            }

            $cell->setValue($replacement);
        }

        if ($sheetBarangay !== '') {
            cash_advance_rename_sheet($sheet, $sheetBarangay);
        } elseif ($sheetBarangayNumber > 0) {
            cash_advance_rename_sheet($sheet, 'Barangay ' . $sheetBarangayNumber);
        }
    }
}

function cash_advance_find_footer_row(Worksheet $sheet): int
{
    $highestRow = max(10, $sheet->getHighestDataRow());
    for ($row = 10; $row <= $highestRow; $row++) {
        $value = trim((string) $sheet->getCell('A' . $row)->getFormattedValue());
        if ($value === '' || !preg_match('/^\d+$/', $value)) {
            return $row;
        }
    }

    return $highestRow + 1;
}

function cash_advance_copy_row_style(Worksheet $sheet, int $sourceRow, int $targetRow): void
{
    foreach (range('A', 'L') as $column) {
        $sheet->duplicateStyle($sheet->getStyle($column . $sourceRow), $column . $targetRow);
    }
    $sheet->getRowDimension($targetRow)->setRowHeight($sheet->getRowDimension($sourceRow)->getRowHeight());
}

function cash_advance_prepare_data_rows(Worksheet $sheet, int $neededRows): void
{
    $startRow = 10;
    $footerRow = cash_advance_find_footer_row($sheet);
    $availableRows = max(0, $footerRow - $startRow);

    if ($neededRows > $availableRows) {
        $insertCount = $neededRows - $availableRows;
        $sheet->insertNewRowBefore($footerRow, $insertCount);
        for ($row = $footerRow; $row < $footerRow + $insertCount; $row++) {
            cash_advance_copy_row_style($sheet, max($startRow, $footerRow - 1), $row);
        }
    } elseif ($neededRows < $availableRows) {
        $deleteCount = $availableRows - $neededRows;
        if ($deleteCount > 0) {
            $sheet->removeRow($startRow + $neededRows, $deleteCount);
        }
    }
}

function cash_advance_populate_payroll_sheet(Worksheet $sheet, array $context, array $barangay, int &$sequence): void
{
    $beneficiaries = $barangay['beneficiaries'] ?? [];
    cash_advance_prepare_data_rows($sheet, count($beneficiaries));

    $sheet->setCellValue('A2', 'Risk Resiliency Program thru Cash for Training and Work (RRP-CFTW) ' . (string) ($context['year'] ?? ''));
    $sheet->setCellValue('A5', 'MUNICIPALITY OF ' . strtoupper((string) $context['municipality']) . ', ' . strtoupper((string) $context['province']));

    cash_advance_set_payroll_payout_date($sheet, (string) ($context['payout_date'] ?? ''));

    $rowNumber = 10;
    foreach ($beneficiaries as $beneficiary) {
        $sheet->setCellValue('A' . $rowNumber, $sequence);
        $sheet->setCellValue('B' . $rowNumber, '');
        $sheet->setCellValue('C' . $rowNumber, '');
        $sheet->setCellValue('D' . $rowNumber, '');
        $sheet->setCellValue('E' . $rowNumber, cash_advance_beneficiary_full_name($beneficiary));
        $sheet->setCellValue('F' . $rowNumber, (string) ($barangay['name'] ?? ''));
        $sheet->setCellValue('G' . $rowNumber, (string) $context['municipality']);
        $sheet->setCellValue('H' . $rowNumber, (float) ($context['beneficiary_rate'] ?? 0));
        $sheet->setCellValue('I' . $rowNumber, '');
        $sheet->setCellValue('J' . $rowNumber, '');
        $sheet->setCellValue('K' . $rowNumber, '');
        $sequence++;
        $rowNumber++;
    }
}

function cash_advance_set_payroll_payout_date(Worksheet $sheet, string $payoutDate): void
{
    $richText = new RichText();
    $richText->createText('PAY-OUT DATE:    ');

    if (trim($payoutDate) !== '') {
        $dateRun = $richText->createTextRun($payoutDate);
        $dateRun->getFontOrThrow()->setBold(true);
        $dateRun->getFontOrThrow()->setUnderline(Font::UNDERLINE_SINGLE);
    }

    $sheet->setCellValue('A7', $richText);
}

function cash_advance_find_time_tally_footer_row(Worksheet $sheet): int
{
    $highestRow = max(11, $sheet->getHighestDataRow());
    for ($row = 11; $row <= $highestRow; $row++) {
        $value = trim((string) $sheet->getCell('A' . $row)->getFormattedValue());
        if ($value === '' || !preg_match('/^\d+$/', $value)) {
            return $row;
        }
    }

    return $highestRow + 1;
}

function cash_advance_prepare_time_tally_rows(Worksheet $sheet, int $neededRows): void
{
    unset($sheet, $neededRows);
}

function cash_advance_populate_time_tally_sheet(Worksheet $sheet, array $context, array $barangay, int &$sequence): void
{
    $beneficiaries = $barangay['beneficiaries'] ?? [];
    $startRow = 11;
    $oldFooterRow = cash_advance_find_time_tally_footer_row($sheet);
    $newFooterRow = $startRow + count($beneficiaries);
    $oldLastFooterRow = $oldFooterRow + 8;
    $newLastFooterRow = $newFooterRow + 8;
    $footerValues = [];
    $footerStyles = [];
    $footerHeights = [];
    for ($row = $oldFooterRow; $row <= $oldLastFooterRow; $row++) {
        $offset = $row - $oldFooterRow;
        $footerHeights[$offset] = $sheet->getRowDimension($row)->getRowHeight();
        foreach (range('A', 'L') as $column) {
            $footerStyles[$offset][$column] = $sheet->getStyle($column . $row)->exportArray();
            $footerValues[$offset][$column] = $sheet->getCell($column . $row)->getValue();
        }
    }
    $footerMerges = [];
    foreach ($sheet->getMergeCells() as $range) {
        if (preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $range, $match)) {
            $startMergeRow = (int) $match[2];
            $endMergeRow = (int) $match[4];
            if ($startMergeRow >= $oldFooterRow && $endMergeRow <= $oldLastFooterRow) {
                $footerMerges[] = [
                    'start_column' => $match[1],
                    'start_offset' => $startMergeRow - $oldFooterRow,
                    'end_column' => $match[3],
                    'end_offset' => $endMergeRow - $oldFooterRow,
                ];
            }
        }
    }

    foreach ($sheet->getMergeCells() as $range) {
        if (preg_match('/^[A-Z]+(\d+):[A-Z]+(\d+)$/', $range, $match) && (int) $match[1] >= $startRow) {
            $sheet->unmergeCells($range);
        }
    }

    $clearToRow = max($sheet->getHighestDataRow(), $newLastFooterRow, $oldLastFooterRow);
    for ($row = $startRow; $row <= $clearToRow; $row++) {
        foreach (range('A', 'L') as $column) {
            $sheet->setCellValue($column . $row, '');
        }
    }

    $sheet->setCellValue('B3', 'Risk Resiliency Program thru Cash for Training and Work (RRP-CFTW) ' . (string) ($context['year'] ?? ''));
    $sheet->setCellValue('B5', 'MUNICIPALITY OF ' . strtoupper((string) $context['municipality']) . ', ' . strtoupper((string) $context['province']));
    $sheet->setCellValue('B7', (string) ($barangay['name'] ?? ''));

    $rowNumber = $startRow;
    foreach ($beneficiaries as $beneficiary) {
        cash_advance_copy_row_style($sheet, min(max($startRow, $oldFooterRow - 1), max($startRow, $rowNumber - 1)), $rowNumber);
        $sheet->setCellValue('A' . $rowNumber, $sequence);
        $sheet->setCellValue('B' . $rowNumber, cash_advance_beneficiary_full_name($beneficiary));
        foreach (range('C', 'L') as $column) {
            $sheet->setCellValue($column . $rowNumber, '');
        }
        $sequence++;
        $rowNumber++;
    }

    for ($offset = 0; $offset <= 8; $offset++) {
        $targetRow = $newFooterRow + $offset;
        $sheet->getRowDimension($targetRow)->setRowHeight($footerHeights[$offset] ?? -1);
        foreach (range('A', 'L') as $column) {
            $sheet->getStyle($column . $targetRow)->applyFromArray($footerStyles[$offset][$column] ?? []);
            $sheet->setCellValue($column . $targetRow, $footerValues[$offset][$column] ?? '');
        }
    }

    foreach ($footerMerges as $merge) {
        $sheet->mergeCells(
            $merge['start_column'] . ($newFooterRow + $merge['start_offset'])
            . ':'
            . $merge['end_column'] . ($newFooterRow + $merge['end_offset'])
        );
    }

    $sheet->setCellValue('C' . ($newFooterRow + 8), '=B7');
    if ($newLastFooterRow < $oldLastFooterRow) {
        $sheet->removeRow($newLastFooterRow + 1, $oldLastFooterRow - $newLastFooterRow);
    }
}

function cash_advance_summary_total_row(Worksheet $sheet): int
{
    for ($row = 1; $row <= $sheet->getHighestDataRow(); $row++) {
        if (strtoupper(trim((string) $sheet->getCell('F' . $row)->getFormattedValue())) === 'TOTAL') {
            return $row;
        }
    }

    return 16;
}

function cash_advance_find_label_row(Worksheet $sheet, string $label): int
{
    for ($row = 1; $row <= $sheet->getHighestDataRow(); $row++) {
        foreach (range('A', 'K') as $column) {
            if (stripos((string) $sheet->getCell($column . $row)->getFormattedValue(), $label) !== false) {
                return $row;
            }
        }
    }

    return 0;
}

function cash_advance_populate_payroll_summary(Worksheet $sheet, array $context): void
{
    $barangays = $context['barangays'] ?? [];
    $neededRows = count($barangays);
    $totalRow = cash_advance_summary_total_row($sheet);
    $availableRows = max(0, $totalRow - 3);

    if ($neededRows > $availableRows) {
        $sheet->insertNewRowBefore($totalRow, $neededRows - $availableRows);
    } elseif ($neededRows < $availableRows) {
        $sheet->removeRow(3 + $neededRows, $availableRows - $neededRows);
    }

    $row = 3;
    foreach ($barangays as $index => $barangay) {
        $sheet->setCellValue('E' . $row, $index === 0 ? (string) $context['municipality'] : '');
        $sheet->setCellValue('F' . $row, (string) ($barangay['name'] ?? ''));
        $sheet->setCellValue('G' . $row, (int) ($barangay['count'] ?? 0));
        $sheet->setCellValue('H' . $row, (float) ($barangay['amount'] ?? 0));
        $row++;
    }

    $totalRow = 3 + $neededRows;
    $sheet->setCellValue('F' . $totalRow, 'TOTAL');
    $sheet->setCellValue('G' . $totalRow, (int) ($context['total_beneficiaries'] ?? 0));
    $sheet->setCellValue('H' . $totalRow, (float) ($context['total_amount'] ?? 0));

    $personsRow = cash_advance_find_label_row($sheet, 'Total Number of Persons');
    if ($personsRow > 0) {
        $sheet->setCellValue('G' . $personsRow, (int) ($context['total_beneficiaries'] ?? 0));
    }

    $amountRow = cash_advance_find_label_row($sheet, 'Total Amount Needed');
    if ($amountRow > 0) {
        $sheet->setCellValue('G' . $amountRow, (float) ($context['total_amount'] ?? 0));
    }

    $manual = $context['manual'] ?? [];
    $doSdoPayee = (string) (
        ($manual['do_sdo_payee'] ?? '')
        ?: ($manual['payroll_sdo_name'] ?? '')
        ?: ($manual['request_ca_c8'] ?? '')
        ?: ($manual['obs_d6'] ?? '')
        ?: ($manual['dv_e11'] ?? '')
    );
    $sheet->setCellValue('I' . (cash_advance_find_label_row($sheet, 'Approved for Payment') ?: 28), $doSdoPayee);
}

function cash_advance_adjust_payroll_sheets(Spreadsheet $spreadsheet, array $context): void
{
    $barangays = $context['barangays'] ?? [];
    $summarySheet = $spreadsheet->getSheetByName('SUMMARY PAGE');

    $templateSheets = [];
    foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
        if (preg_match('/^<BARANGAY\s+\d+>$/i', $sheet->getTitle())) {
            $templateSheets[] = $sheet;
        }
    }

    if ($templateSheets === []) {
        throw new RuntimeException('Payroll template does not contain barangay sheets.');
    }

    while (count($templateSheets) < count($barangays)) {
        $clone = clone $templateSheets[count($templateSheets) - 1];
        $clone->setTitle('<BARANGAY ' . (count($templateSheets) + 1) . '>');
        $insertIndex = $summarySheet ? $spreadsheet->getIndex($summarySheet) : $spreadsheet->getSheetCount();
        $spreadsheet->addSheet($clone, $insertIndex);
        $templateSheets[] = $clone;
    }

    for ($index = count($templateSheets) - 1; $index >= count($barangays); $index--) {
        $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($templateSheets[$index]));
        unset($templateSheets[$index]);
    }

    $sequence = 1;
    foreach (array_values($templateSheets) as $index => $sheet) {
        $barangay = $barangays[$index] ?? null;
        if (!is_array($barangay)) {
            continue;
        }

        cash_advance_populate_payroll_sheet($sheet, $context, $barangay, $sequence);
        cash_advance_rename_sheet($sheet, (string) ($barangay['name'] ?? ('Barangay ' . ($index + 1))));
    }

    $summarySheet = $spreadsheet->getSheetByName('SUMMARY PAGE');
    if ($summarySheet instanceof Worksheet) {
        cash_advance_populate_payroll_summary($summarySheet, $context);
    }
}

function cash_advance_adjust_time_tally_sheets(Spreadsheet $spreadsheet, array $context): void
{
    $barangays = $context['barangays'] ?? [];

    $templateSheets = [];
    foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
        if (preg_match('/^<BARANGAY\s+\d+>$/i', $sheet->getTitle())) {
            $templateSheets[] = $sheet;
        }
    }

    if ($templateSheets === []) {
        throw new RuntimeException('Time Tally Sheet template does not contain barangay sheets.');
    }

    while (count($templateSheets) < count($barangays)) {
        $clone = clone $templateSheets[count($templateSheets) - 1];
        $clone->setTitle('<BARANGAY ' . (count($templateSheets) + 1) . '>');
        $spreadsheet->addSheet($clone);
        $templateSheets[] = $clone;
    }

    for ($index = count($templateSheets) - 1; $index >= count($barangays); $index--) {
        $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($templateSheets[$index]));
        unset($templateSheets[$index]);
    }

    $sequence = 1;
    foreach (array_values($templateSheets) as $index => $sheet) {
        $barangay = $barangays[$index] ?? null;
        if (!is_array($barangay)) {
            continue;
        }

        cash_advance_populate_time_tally_sheet($sheet, $context, $barangay, $sequence);
        cash_advance_rename_sheet($sheet, (string) ($barangay['name'] ?? ('Barangay ' . ($index + 1))));
    }
}

function cash_advance_apply_time_tally_mswdo_border(Spreadsheet $spreadsheet): void
{
    foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
        $labelRow = cash_advance_find_label_row($sheet, 'MSWDO');
        if ($labelRow <= 1) {
            continue;
        }

        $nameRow = $labelRow - 1;
        $sheet->getStyle('I' . $nameRow . ':J' . $nameRow)
            ->getBorders()
            ->getBottom()
            ->setBorderStyle(Border::BORDER_THIN);
    }
}

function cash_advance_apply_time_tally_footer_formatting(Worksheet $sheet, int $footerRow): void
{
    unset($sheet, $footerRow);
}

function cash_advance_flatten_beneficiaries(array $context): array
{
    $beneficiaries = [];
    foreach (($context['barangays'] ?? []) as $barangay) {
        foreach (($barangay['beneficiaries'] ?? []) as $beneficiary) {
            $beneficiaries[] = $beneficiary;
        }
    }

    return $beneficiaries;
}

function cash_advance_authority_total_row(Worksheet $sheet): int
{
    $highestRow = max(16, $sheet->getHighestDataRow());
    for ($row = 16; $row <= $highestRow; $row++) {
        $gValue = strtoupper(trim((string) $sheet->getCell('G' . $row)->getFormattedValue()));
        $hValue = strtoupper(trim((string) $sheet->getCell('H' . $row)->getFormattedValue()));
        if (str_contains($gValue, 'TOTAL') || str_contains($hValue, 'GRAND TOTAL')) {
            return $row;
        }
    }

    return $highestRow + 1;
}

function cash_advance_prepare_authority_rows(Worksheet $sheet, int $neededRows): int
{
    $startRow = 16;
    return $startRow + $neededRows;
}

function cash_advance_find_authority_footer_code_row(Worksheet $sheet): int
{
    for ($row = $sheet->getHighestDataRow(); $row >= 1; $row--) {
        $value = trim((string) $sheet->getCell('A' . $row)->getFormattedValue());
        if (stripos($value, 'Authority to Pay/') === 0) {
            return $row;
        }
    }

    return 0;
}

function cash_advance_apply_authority_bottom_formatting(
    Worksheet $sheet,
    int $totalRow,
    int $doneRow,
    int $directorRow,
    int $roleRow,
    int $footerRow
): void {
    foreach ([$totalRow, $doneRow, $directorRow, $roleRow, $footerRow] as $row) {
        $sheet->getRowDimension($row)->setRowHeight(15);
    }

    foreach ([$totalRow + 1, $doneRow + 1, $doneRow + 2, $doneRow + 3, $directorRow + 2, $directorRow + 3, $directorRow + 4] as $row) {
        $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
            'font' => [
                'bold' => false,
                'italic' => false,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_NONE],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    $sheet->getStyle('A' . $totalRow . ':H' . $totalRow)->applyFromArray([
        'font' => ['bold' => true],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN],
        ],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    ]);

    $sheet->getStyle('A' . $doneRow . ':H' . $doneRow)->applyFromArray([
        'font' => ['bold' => false],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_NONE],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_LEFT,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ]);

    $sheet->getStyle('A' . $directorRow . ':H' . $directorRow)->applyFromArray([
        'font' => ['bold' => true],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_NONE],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ]);

    $sheet->getStyle('A' . $roleRow . ':H' . $roleRow)->applyFromArray([
        'font' => ['bold' => false],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_NONE],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ]);

    $sheet->getStyle('A' . $footerRow . ':H' . $footerRow)->applyFromArray([
        'font' => [
            'bold' => false,
            'italic' => true,
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_NONE],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_LEFT,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ]);
}

function cash_advance_adjust_authority_to_pay(Spreadsheet $spreadsheet, array $context): void
{
    $sheet = $spreadsheet->getSheet(0);
    cash_advance_rename_sheet($sheet, 'ATP ' . (string) ($context['municipality'] ?? 'Municipality'));

    $beneficiaries = cash_advance_flatten_beneficiaries($context);
    $oldTotalRow = cash_advance_authority_total_row($sheet);
    $oldDoneRow = cash_advance_find_label_row($sheet, 'Done this') ?: ($oldTotalRow + 2);
    $oldDirectorRow = cash_advance_find_label_row($sheet, 'MARI - FLOR') ?: ($oldTotalRow + 6);
    $oldRoleRow = cash_advance_find_label_row($sheet, 'Regional Director') ?: ($oldDirectorRow + 1);
    $oldFooterRow = cash_advance_find_authority_footer_code_row($sheet) ?: ($oldTotalRow + 11);
    $directorName = (string) $sheet->getCell('A' . $oldDirectorRow)->getValue();
    $directorRole = (string) $sheet->getCell('A' . $oldRoleRow)->getValue();
    $footerNote = (string) $sheet->getCell('A' . $oldFooterRow)->getValue();
    $totalRow = cash_advance_prepare_authority_rows($sheet, count($beneficiaries));
    $doneRow = $totalRow + 2;
    $directorRow = $totalRow + 6;
    $roleRow = $totalRow + 7;
    $footerRow = $totalRow + 11;

    foreach ($sheet->getMergeCells() as $range) {
        if (preg_match('/^[A-Z]+(\d+):[A-Z]+(\d+)$/', $range, $match) && (int) $match[1] >= 16) {
            $sheet->unmergeCells($range);
        }
    }

    $clearToRow = max($sheet->getHighestDataRow(), $footerRow);
    for ($row = 16; $row <= $clearToRow; $row++) {
        foreach (range('A', 'L') as $column) {
            $sheet->setCellValue($column . $row, '');
        }
    }

    $sheet->setCellValue('G6', '     Date: ' . cash_advance_generation_date_label());
    $sheet->setCellValue('A9', 'Series of ' . (string) ($context['year'] ?? date('Y')));
    $sheet->getAutoFilter()->setRange('F15:F' . ($totalRow - 1));

    $row = 16;
    foreach ($beneficiaries as $index => $beneficiary) {
        cash_advance_copy_row_style($sheet, min(202, max(16, $row - 1)), $row);
        $sheet->setCellValue('A' . $row, $index + 1);
        $sheet->setCellValue('B' . $row, (string) ($beneficiary['last_name'] ?? ''));
        $sheet->setCellValue('C' . $row, (string) ($beneficiary['first_name'] ?? ''));
        $sheet->setCellValue('D' . $row, (string) ($beneficiary['middle_name'] ?? ''));
        $sheet->setCellValue('E' . $row, (string) ($beneficiary['extension'] ?? ''));
        $sheet->setCellValue('F' . $row, (string) ($beneficiary['barangay'] ?? ''));
        $sheet->setCellValue('G' . $row, (string) ($context['municipality'] ?? ''));
        $sheet->setCellValue('H' . $row, (float) ($context['beneficiary_rate'] ?? 0));
        $row++;
    }

    cash_advance_copy_row_style($sheet, $oldTotalRow, $totalRow);
    $sheet->setCellValue('G' . $totalRow, 'Total >>>');
    $sheet->setCellValue('H' . $totalRow, (float) ($context['total_amount'] ?? 0));
    cash_advance_copy_row_style($sheet, $oldDoneRow, $doneRow);
    cash_advance_copy_row_style($sheet, $oldDirectorRow, $directorRow);
    cash_advance_copy_row_style($sheet, $oldRoleRow, $roleRow);
    cash_advance_copy_row_style($sheet, $oldFooterRow, $footerRow);

    $sheet->mergeCells('A' . $doneRow . ':H' . $doneRow);
    $sheet->mergeCells('A' . $directorRow . ':H' . $directorRow);
    $sheet->mergeCells('A' . $roleRow . ':H' . $roleRow);
    $sheet->setCellValue(
        'A' . $doneRow,
        'Done this ' . cash_advance_generation_day_phrase() . ' at DSWD Field Office Caraga, Butuan City, Philippines.'
    );
    $sheet->setCellValue('A' . $directorRow, $directorName);
    $sheet->setCellValue('A' . $roleRow, $directorRole);
    $initials = trim((string) ($context['user_initials'] ?? ''));
    if ($initials !== '') {
        $footerNote = preg_replace('~/[^/]*$~', '/' . $initials, $footerNote) ?? $footerNote;
    }
    $sheet->setCellValue('A' . $footerRow, $footerNote);
    cash_advance_apply_authority_bottom_formatting($sheet, $totalRow, $doneRow, $directorRow, $roleRow, $footerRow);
}

function cash_advance_clear_payroll_payout_dates(Spreadsheet $spreadsheet, array $context): void
{
    $payoutDate = (string) ($context['payout_date'] ?? '');
    foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
        if ($sheet->getTitle() === 'SUMMARY PAGE') {
            continue;
        }

        cash_advance_set_payroll_payout_date($sheet, $payoutDate);
    }
}

function cash_advance_rename_sheet(Worksheet $sheet, string $title): void
{
    $safe = preg_replace('/[\[\]\*\/\\\\\?:]/', ' ', trim($title));
    $safe = preg_replace('/\s+/', ' ', (string) $safe);
    $safe = trim((string) $safe);
    $sheet->setTitle(mb_substr($safe !== '' ? $safe : 'Barangay', 0, 31));
}

function cash_advance_generate_workbook(string $templateKey, array $template, array $context, array $barangays, string $outputPath): void
{
    $templatePath = cash_advance_template_dir() . '/' . $template['filename'];
    if (!is_file($templatePath)) {
        throw new RuntimeException('Template file not found: ' . $template['filename']);
    }

    $spreadsheet = IOFactory::load($templatePath);
    if ($templateKey === 'payroll') {
        cash_advance_adjust_payroll_sheets($spreadsheet, $context);
    } elseif ($templateKey === 'authority_to_pay') {
        cash_advance_adjust_authority_to_pay($spreadsheet, $context);
    } elseif ($templateKey === 'time_tally_sheet') {
        cash_advance_adjust_time_tally_sheets($spreadsheet, $context);
    }

    cash_advance_apply_generic_replacements($spreadsheet, $context, $barangays);
    cash_advance_apply_direct_manual_cells($spreadsheet, $templateKey, $context);

    $writer = new Xlsx($spreadsheet);
    $writer->save($outputPath);
    $spreadsheet->disconnectWorksheets();
}

function cash_advance_generate_zip(array $context, array $barangays): array
{
    $workDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'kodus-cash-advance-' . bin2hex(random_bytes(8));
    if (!mkdir($workDir, 0777, true) && !is_dir($workDir)) {
        throw new RuntimeException('Unable to create the temporary output folder.');
    }

    $zipPath = $workDir . DIRECTORY_SEPARATOR . 'cash-advance-requirements.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to prepare the download package.');
    }

    try {
        foreach (cash_advance_templates() as $templateKey => $template) {
            if ($templateKey === 'time_tally_sheet' && empty($context['include_time_tally'])) {
                continue;
            }

            $outputName = cash_advance_download_basename((string) $context['municipality']) . ' - ' . $template['output'];
            $outputPath = $workDir . DIRECTORY_SEPARATOR . $outputName;
            cash_advance_generate_workbook($templateKey, $template, $context, $barangays, $outputPath);
            $zip->addFile($outputPath, $outputName);
        }
    } finally {
        $zip->close();
    }

    return [
        'path' => $zipPath,
        'dir' => $workDir,
        'filename' => cash_advance_download_basename((string) $context['municipality']) . ' - Cash Advance Requirements.zip',
    ];
}

function cash_advance_cleanup_generated_package(array $package): void
{
    $dir = (string) ($package['dir'] ?? '');
    if ($dir === '' || !is_dir($dir)) {
        return;
    }

    foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($dir);
}
