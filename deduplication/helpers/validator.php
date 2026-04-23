<?php
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

const DEDUP_REQUIRED_FIELDS = ['lastName', 'firstName', 'middleName', 'ext', 'birthDate', 'barangay', 'lgu', 'province'];

function dedupHeaderAliases(): array
{
    return [
        'lastName' => ['lastname', 'last', 'lname'],
        'firstName' => ['firstname', 'first', 'fname'],
        'middleName' => ['middlename', 'middle', 'mname'],
        'ext' => ['ext', 'suffix'],
        'birthDate' => ['birthdate', 'birth', 'dob', 'dateofbirth'],
        'barangay' => ['barangay', 'brgy'],
        'lgu' => ['lgu', 'city', 'municipality'],
        'province' => ['province', 'prov'],
    ];
}

function dedupMapHeaders(array $headerRow): array
{
    $aliases = dedupHeaderAliases();
    $mapped = [];

    foreach ($headerRow as $i => $heading) {
        $normalized = str_replace([' ', '_'], '', strtolower(trim((string) $heading)));
        if ($normalized === '') {
            continue;
        }

        foreach ($aliases as $canonical => $accepted) {
            if (in_array($normalized, $accepted, true)) {
                $mapped[$canonical] = $i;
                break;
            }
        }
    }

    $missing = array_values(array_diff(DEDUP_REQUIRED_FIELDS, array_keys($mapped)));
    if ($missing) {
        throw new Exception(
            'Invalid template. Missing required column(s): ' . implode(', ', $missing) . '.'
        );
    }

    return $mapped;
}

function validateAndParseFile($filePath): array
{
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $sheetData = [];

    if ($ext === 'csv') {
        $raw = array_map('trim', file($filePath));
        foreach ($raw as $line) {
            if ($line === '') {
                continue;
            }
            $sheetData[] = str_getcsv($line);
        }
    } else {
        $spreadsheet = IOFactory::load($filePath);
        $sheetRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        foreach ($sheetRows as $row) {
            $sheetData[] = array_values($row);
        }
    }

    if (empty($sheetData)) {
        throw new Exception('Uploaded file is empty.');
    }

    $headerRow = array_map(
        static fn($value) => strtolower(trim((string) $value)),
        array_values($sheetData[0])
    );
    $keyToPos = dedupMapHeaders($headerRow);

    $outRows = [];
    for ($i = 1; $i < count($sheetData); $i++) {
        $row = $sheetData[$i];
        if (!is_array($row)) {
            continue;
        }

        $assoc = [];
        foreach ($keyToPos as $key => $idx) {
            $val = isset($row[$idx]) ? trim((string) $row[$idx]) : '';
            if ($key === 'birthDate' && $val !== '') {
                if (is_numeric($val)) {
                    $val = date('Y-m-d', ExcelDate::excelToTimestamp($val));
                } else {
                    $ts = strtotime($val);
                    if ($ts) {
                        $val = date('Y-m-d', $ts);
                    }
                }
            }
            $assoc[$key] = $val;
        }

        if (!empty($assoc['lastName']) && !empty($assoc['firstName'])) {
            $outRows[] = $assoc;
        }
    }

    if (empty($outRows)) {
        throw new Exception('Invalid template or empty data. No valid beneficiary rows were found.');
    }

    return $outRows;
}
