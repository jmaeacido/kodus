<?php
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Validate headers and parse rows into canonical associative arrays
 * Required fields: lastName, firstName, middleName, ext, birthDate, barangay, lgu, province
 */
function validateAndParseFile($filePath) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $sheetData = [];

    if ($ext === 'csv') {
        $raw = array_map('trim', file($filePath));
        foreach ($raw as $line) {
            if ($line === '') continue;
            $sheetData[] = str_getcsv($line);
        }
    } else {
        $spreadsheet = IOFactory::load($filePath);
        $sheetRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        foreach ($sheetRows as $r) {
            $sheetData[] = array_values($r); 
        }
    }

    if (empty($sheetData)) {
        throw new Exception("Uploaded file is empty.");
    }

    // First row = headers
    $headerRow = array_map(fn($v) => strtolower(trim((string)$v)), array_values($sheetData[0]));

    // map column index → canonical key
    $posToKey = [];
    foreach ($headerRow as $i => $h) {
        $normalized = str_replace([' ', '_'], '', strtolower($h));
        if (in_array($normalized, ['lastname','last','lname'])) {
            $posToKey[$i] = 'lastName';
        } elseif (in_array($normalized, ['firstname','first','fname'])) {
            $posToKey[$i] = 'firstName';
        } elseif (in_array($normalized, ['middlename','middle','mname'])) {
            $posToKey[$i] = 'middleName';
        } elseif (in_array($normalized, ['ext','suffix'])) {
            $posToKey[$i] = 'ext';
        } elseif (strpos($normalized,'birth') !== false || strpos($normalized,'dob') !== false) {
            $posToKey[$i] = 'birthDate';
        } elseif ($normalized === 'barangay' || $normalized === 'brgy') {
            $posToKey[$i] = 'barangay';
        } elseif ($normalized === 'lgu' || $normalized === 'city' || $normalized === 'municipality') {
            $posToKey[$i] = 'lgu';
        } elseif ($normalized === 'province' || $normalized === 'prov') {
            $posToKey[$i] = 'province';
        }
    }

    $outRows = [];
    for ($i=1; $i<count($sheetData); $i++) {
        $row = $sheetData[$i];
        if (!is_array($row)) continue;
        $assoc = [];
        foreach ($posToKey as $idx=>$key) {
            $val = isset($row[$idx]) ? trim((string)$row[$idx]) : '';
            if ($key === 'birthDate' && $val !== '') {
                // convert Excel serial → Y-m-d
                if (is_numeric($val)) {
                    $val = date('Y-m-d', ExcelDate::excelToTimestamp($val));
                } else {
                    $ts = strtotime($val);
                    if ($ts) $val = date('Y-m-d', $ts);
                }
            }
            $assoc[$key] = $val;
        }
        if (!empty($assoc['lastName']) && !empty($assoc['firstName'])) {
            $outRows[] = $assoc;
        }
    }

    return $outRows;
}