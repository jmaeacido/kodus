<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

function mebis_load_with_extension_copy($reader, string $path, string $extension)
{
    $tempBase = tempnam(sys_get_temp_dir(), 'mbs');
    if ($tempBase === false) {
        return $reader->load($path);
    }

    $tempPath = $tempBase . '.' . $extension;
    if (!@rename($tempBase, $tempPath)) {
        @unlink($tempBase);
        $tempPath = $tempBase . '.' . $extension;
    }

    if (!@copy($path, $tempPath)) {
        @unlink($tempPath);
        return $reader->load($path);
    }

    try {
        return $reader->load($tempPath);
    } finally {
        @unlink($tempPath);
    }
}

function mebis_configure_reader($reader, ?array $sheetNames = null, bool $readDataOnly = true): void
{
    if (method_exists($reader, 'setReadDataOnly')) {
        $reader->setReadDataOnly($readDataOnly);
    }

    if (method_exists($reader, 'setReadEmptyCells')) {
        $reader->setReadEmptyCells(false);
    }

    if (method_exists($reader, 'setIgnoreRowsWithNoCells')) {
        $reader->setIgnoreRowsWithNoCells(true);
    }

    if ($sheetNames !== null && method_exists($reader, 'setLoadSheetsOnly')) {
        $reader->setLoadSheetsOnly($sheetNames);
    }
}

function mebis_candidate_sheet_names(string $path, ?string $originalName = null): array
{
    $resolvedPath = realpath($path);
    if ($resolvedPath !== false) {
        $path = $resolvedPath;
    }
    $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

    $extensionSource = $originalName ?: $path;
    $extension = strtolower(pathinfo($extensionSource, PATHINFO_EXTENSION));

    if (!in_array($extension, ['xlsx', 'xlsm'], true)) {
        return [];
    }

    $reader = new Xlsx();
    $names = [];

    try {
        $names = $reader->listWorksheetNames($path);
    } catch (Throwable $e) {
        try {
            $names = mebis_load_with_extension_copy($reader, $path, $extension === 'xlsm' ? 'xlsm' : 'xlsx')->getSheetNames();
        } catch (Throwable $ignored) {
            return [];
        }
    }

    return is_array($names) ? $names : [];
}

function mebis_choose_data_sheet_name(string $path, ?string $originalName = null): ?string
{
    $sheetNames = mebis_candidate_sheet_names($path, $originalName);
    if ($sheetNames === []) {
        return null;
    }

    foreach ($sheetNames as $name) {
        if (strcasecmp((string) $name, 'MEB') === 0) {
            return (string) $name;
        }
    }

    return null;
}

function mebis_find_sheet_by_name($spreadsheet, string $targetName)
{
    $sheet = $spreadsheet->getSheetByName($targetName);
    if ($sheet !== null) {
        return $sheet;
    }

    foreach ($spreadsheet->getAllSheets() as $candidate) {
        if (strcasecmp((string) $candidate->getTitle(), $targetName) === 0) {
            return $candidate;
        }
    }

    return null;
}

function mebis_detect_uploaded_meb_sheet(string $path, string $originalName): ?string
{
    $sheetNames = mebis_candidate_sheet_names($path, $originalName);
    foreach ($sheetNames as $sheetName) {
        if (strcasecmp((string) $sheetName, 'MEB') === 0) {
            return (string) $sheetName;
        }
    }

    try {
        $spreadsheet = mebis_load_spreadsheet($path, $originalName, null, true);
    } catch (Throwable $e) {
        return null;
    }

    try {
        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            if (strcasecmp((string) $sheetName, 'MEB') === 0) {
                return (string) $sheetName;
            }
        }
    } finally {
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    return null;
}

function mebis_load_spreadsheet(string $path, ?string $originalName = null, ?array $sheetNames = null, bool $readDataOnly = true)
{
    mebis_bootstrap_spreadsheet_runtime();

    $resolvedPath = realpath($path);
    if ($resolvedPath !== false) {
        $path = $resolvedPath;
    }
    $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

    $extensionSource = $originalName ?: $path;
    $extension = strtolower(pathinfo($extensionSource, PATHINFO_EXTENSION));

    if (in_array($extension, ['xlsx', 'xlsm'], true)) {
        try {
            $reader = new Xlsx();
            mebis_configure_reader($reader, $sheetNames, $readDataOnly);
            return $reader->load($path);
        } catch (Throwable $e) {
            $reader = new Xlsx();
            mebis_configure_reader($reader, $sheetNames, $readDataOnly);
            return mebis_load_with_extension_copy($reader, $path, $extension === 'xlsm' ? 'xlsm' : 'xlsx');
        }
    }

    if ($extension === 'csv') {
        $reader = new Csv();
        return $reader->load($path);
    }

    return IOFactory::load($path);
}

function mebis_normalize_text(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/\s+/u', ' ', $value);
    $value = str_replace(['Ñ', 'ñ'], ['N', 'n'], $value);
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($ascii !== false && $ascii !== '') {
        $value = $ascii;
    }

    return strtolower(trim($value));
}

function mebis_clean_location_label(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/^(province|municipality|city)\s+of\s+/i', '', $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return trim($value);
}

function mebis_normalize_location_key(string $value): string
{
    $value = mebis_normalize_text($value);
    $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return trim($value);
}

function mebis_location_alias_keys(string $value): array
{
    $value = trim($value);
    if ($value === '') {
        return [];
    }

    $variants = [$value];
    $abbreviationSwaps = [
        '/\bsaint\b/i' => ['st', 'sto'],
        '/\bsanto\b/i' => ['sto', 'st'],
        '/\bsanta\b/i' => ['sta', 'st'],
        '/\bbarangay\b/i' => ['brgy', 'bgy'],
    ];

    foreach ($abbreviationSwaps as $pattern => $replacements) {
        foreach ($variants as $variant) {
            if (preg_match($pattern, $variant) !== 1) {
                continue;
            }

            foreach ($replacements as $replacement) {
                $variants[] = preg_replace($pattern, $replacement, $variant);
            }
        }
    }

    $keys = [];
    foreach ($variants as $variant) {
        $normalized = mebis_normalize_location_key($variant);
        if ($normalized !== '') {
            $keys[$normalized] = true;
            $keys[str_replace(' ', '', $normalized)] = true;
        }

        $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($tokens) >= 2) {
            $lastToken = array_pop($tokens);
            $initials = implode('', array_map(static fn(string $token): string => $token[0], $tokens));

            if ($initials !== '') {
                $keys[trim(implode(' ', str_split($initials)) . ' ' . $lastToken)] = true;
                $keys[$initials . ' ' . $lastToken] = true;
                $keys[$initials . $lastToken] = true;
            }

            $firstInitial = $tokens[0][0] ?? '';
            if ($firstInitial !== '') {
                $keys[$firstInitial . ' ' . $lastToken] = true;
                $keys[$firstInitial . $lastToken] = true;
            }
        }
    }

    return array_keys($keys);
}

function mebis_lookup_store(array &$bucket, string $key, array $value): void
{
    if ($key === '' || array_key_exists($key, $bucket)) {
        return;
    }

    $bucket[$key] = $value;
}

function mebis_lookup_store_aliases(array &$bucket, array $parts, array $value): void
{
    $segments = [];

    foreach ($parts as $part) {
        $aliases = mebis_location_alias_keys((string) $part);
        if ($aliases === []) {
            return;
        }
        $segments[] = $aliases;
    }

    $combinations = [''];
    foreach ($segments as $aliases) {
        $next = [];
        foreach ($combinations as $prefix) {
            foreach ($aliases as $alias) {
                $next[] = $prefix === '' ? $alias : $prefix . '|' . $alias;
            }
        }
        $combinations = $next;
    }

    foreach ($combinations as $combination) {
        mebis_lookup_store($bucket, $combination, $value);
    }
}

function mebis_lookup_find(array $bucket, array $parts): ?array
{
    $segments = [];
    foreach ($parts as $part) {
        $aliases = mebis_location_alias_keys((string) $part);
        if ($aliases === []) {
            return null;
        }
        $segments[] = $aliases;
    }

    $combinations = [''];
    foreach ($segments as $aliases) {
        $next = [];
        foreach ($combinations as $prefix) {
            foreach ($aliases as $alias) {
                $next[] = $prefix === '' ? $alias : $prefix . '|' . $alias;
            }
        }
        $combinations = $next;
    }

    foreach ($combinations as $combination) {
        if (array_key_exists($combination, $bucket)) {
            return $bucket[$combination];
        }
    }

    return null;
}

function mebis_row_text($sheet, int $row): string
{
    $highestColumn = $sheet->getHighestDataColumn($row);
    $values = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, false)[0] ?? [];
    $parts = [];

    foreach ($values as $value) {
        $text = trim((string) $value);
        if ($text !== '') {
            $parts[] = $text;
        }
    }

    return trim(implode(' ', $parts));
}

function mebis_location_from_row($sheet, int $row, array $prefixes): string
{
    $rowText = mebis_row_text($sheet, $row);
    if ($rowText === '') {
        return '';
    }

    foreach ($prefixes as $prefix) {
        $pattern = '/^' . preg_quote($prefix, '/') . '\s+of\s+/i';
        if (preg_match($pattern, $rowText) === 1) {
            return mebis_clean_location_label($rowText);
        }
    }

    return mebis_clean_location_label($rowText);
}

function mebis_normalize_header_label(string $value): string
{
    $value = mebis_normalize_text($value);
    $value = str_replace(['-', '/', '(', ')', '.', ','], ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return trim($value);
}

function mebis_find_header_map($sheet): array
{
    $aliases = [
        'entry_no' => ['no'],
        'last_name' => ['last name'],
        'first_name' => ['first name'],
        'middle_name' => ['middle name'],
        'extName' => ['ext', 'ext '],
        'full_name' => ['name'],
        'purok' => ['purok'],
        'barangay_name' => ['barangay'],
        'birthdate' => ['b day d m y', 'birthdate dd mm yyyy', 'birthdate'],
    ];

    $combinedCandidate = null;

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

        $standardRequired = ['entry_no', 'last_name', 'first_name', 'middle_name', 'extName', 'barangay_name'];
        $combinedRequired = ['entry_no', 'full_name', 'barangay_name'];

        $hasStandard = true;
        foreach ($standardRequired as $field) {
            if (!array_key_exists($field, $map)) {
                $hasStandard = false;
                break;
            }
        }

        if ($hasStandard) {
            $map['name_mode'] = $hasStandard ? 'split' : 'combined';
            $map['header_row'] = $row;
            return $map;
        }

        $hasCombined = true;
        foreach ($combinedRequired as $field) {
            if (!array_key_exists($field, $map)) {
                $hasCombined = false;
                break;
            }
        }

        if ($hasCombined && $combinedCandidate === null) {
            $map['name_mode'] = 'combined';
            $map['header_row'] = $row;
            $combinedCandidate = $map;
        }
    }

    if ($combinedCandidate !== null) {
        return $combinedCandidate;
    }

    throw new RuntimeException('Unable to detect the beneficiary header row in the MEB sheet.');
}

function mebis_row_value(array $values, array $headerMap, string $field): string
{
    $index = $headerMap[$field] ?? null;
    if ($index === null) {
        return '';
    }

    return trim((string) ($values[$index] ?? ''));
}

function mebis_cell_text(Cell $cell): string
{
    $value = $cell->getFormattedValue();
    if ($value === null || $value === '') {
        $value = $cell->getValue();
    }

    return trim((string) $value);
}

function mebis_date_value(Cell $cell): string
{
    $value = $cell->getValue();
    if ($value === null || $value === '') {
        return '';
    }

    if (ExcelDate::isDateTime($cell)) {
        try {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        } catch (Throwable $e) {
            return trim((string) $cell->getFormattedValue());
        }
    }

    if (is_numeric($value)) {
        $numeric = (float) $value;
        if ($numeric >= 1 && $numeric <= 60000) {
            try {
                return ExcelDate::excelToDateTimeObject($numeric)->format('Y-m-d');
            } catch (Throwable $e) {
                return trim((string) $cell->getFormattedValue());
            }
        }
    }

    return mebis_normalize_date_string((string) $cell->getFormattedValue());
}

function mebis_is_hidden_row($sheet, int $row): bool
{
    if (method_exists($sheet, 'isRowVisible') && $sheet->isRowVisible($row) === false) {
        return true;
    }

    $dimension = $sheet->getRowDimension($row);

    if ($dimension->getVisible() === false) {
        return true;
    }

    if (method_exists($dimension, 'getZeroHeight') && $dimension->getZeroHeight()) {
        return true;
    }

    if (method_exists($dimension, 'getCollapsed') && $dimension->getCollapsed()) {
        return true;
    }

    return false;
}

function mebis_normalize_date_string(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = str_replace(['.', '\\'], ['-', '/'], $value);

    $formats = [
        'Y-m-d',
        'Y/n/j',
        'Y/m/d',
        'm/d/Y',
        'n/j/Y',
        'd/m/Y',
        'j/n/Y',
        'm-d-Y',
        'n-j-Y',
        'd-m-Y',
        'j-n-Y',
        'M j, Y',
        'F j, Y',
        'M j Y',
        'F j Y',
    ];

    foreach ($formats as $format) {
        $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($date instanceof \DateTimeImmutable) {
            $errors = \DateTimeImmutable::getLastErrors();
            if (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0) {
                return $date->format('Y-m-d');
            }
        }
    }

    $timestamp = strtotime($value);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }

    return $value;
}

function mebis_truthy_marker(Cell $cell): int
{
    $value = mebis_normalize_text(mebis_cell_text($cell));
    if ($value === '') {
        return 0;
    }

    return in_array($value, ['1', 'y', 'yes', 'true', 'm', 'g', 'member(active)', 'graduated(exited)'], true)
        || str_contains($value, 'check')
        || str_contains($value, '✓')
        ? 1
        : ($value !== '' ? 1 : 0);
}

function mebis_parse_psgc_workbook(string $path): array
{
    $cachedLookup = mebis_load_psgc_cache($path);
    if ($cachedLookup !== null) {
        return $cachedLookup;
    }

    $spreadsheet = mebis_load_spreadsheet($path, basename($path), ['PSGC'], true);

    try {
        $sheet = $spreadsheet->getSheetByName('PSGC');

        if ($sheet === null) {
            throw new RuntimeException('PSGC sheet not found in the uploaded PSGC workbook.');
        }

        $highestRow = $sheet->getHighestDataRow();
        $currentRegion = null;
        $currentProvince = null;
        $currentMunicipality = null;

        $lookup = [
            'province' => [],
            'municipality' => [],
            'barangay' => [],
        ];

        for ($row = 2; $row <= $highestRow; $row++) {
            $code = trim((string) $sheet->getCell('C' . $row)->getFormattedValue());
            $name = trim((string) $sheet->getCell('B' . $row)->getFormattedValue());
            $level = trim((string) $sheet->getCell('D' . $row)->getFormattedValue());

            if ($code === '' || $name === '' || $level === '') {
                continue;
            }

        if ($level === 'Reg') {
            $currentRegion = [
                'region_name' => $name,
                'region_code' => $code,
            ];
                $currentProvince = null;
                $currentMunicipality = null;
                continue;
            }

            if ($level === 'Prov') {
            $currentProvince = [
                'province_name' => $name,
                'province_code' => $code,
            ];
            $currentMunicipality = null;

            $provinceEntry = array_merge(
                $currentRegion ?? ['region_name' => '', 'region_code' => ''],
                $currentProvince
            );
            mebis_lookup_store_aliases($lookup['province'], [$name], $provinceEntry);
            continue;
        }

        if ($level === 'Mun' || $level === 'City') {
            $currentMunicipality = [
                'city_name' => $name,
                'city_code' => $code,
            ];

            $municipalityEntry = array_merge(
                $currentRegion ?? ['region_name' => '', 'region_code' => ''],
                $currentProvince ?? ['province_name' => '', 'province_code' => ''],
                $currentMunicipality
            );
            mebis_lookup_store_aliases(
                $lookup['municipality'],
                [$currentProvince['province_name'] ?? '', $name],
                $municipalityEntry
            );
            continue;
        }

        if ($level === 'Bgy') {
            $barangayEntry = array_merge(
                $currentRegion ?? ['region_name' => '', 'region_code' => ''],
                $currentProvince ?? ['province_name' => '', 'province_code' => ''],
                $currentMunicipality ?? ['city_name' => '', 'city_code' => ''],
                [
                    'barangay_name' => $name,
                    'barangay_code' => $code,
                ]
            );
            mebis_lookup_store_aliases(
                $lookup['barangay'],
                [$currentProvince['province_name'] ?? '', $currentMunicipality['city_name'] ?? '', $name],
                $barangayEntry
            );
        }
    }

        mebis_store_psgc_cache($path, $lookup);
        return $lookup;
    } finally {
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }
}

function mebis_match_psgc(array $lookup, string $province, string $municipality, string $barangay): array
{
    $provinceMatch = mebis_lookup_find($lookup['province'] ?? [], [$province]);
    $municipalityMatch = mebis_lookup_find($lookup['municipality'] ?? [], [$province, $municipality]);
    $barangayMatch = mebis_lookup_find($lookup['barangay'] ?? [], [$province, $municipality, $barangay]);

    return [
        'region_name' => $barangayMatch['region_name'] ?? $municipalityMatch['region_name'] ?? $provinceMatch['region_name'] ?? '',
        'region_code' => $barangayMatch['region_code'] ?? $municipalityMatch['region_code'] ?? $provinceMatch['region_code'] ?? '',
        'province_code' => $barangayMatch['province_code'] ?? $municipalityMatch['province_code'] ?? $provinceMatch['province_code'] ?? '',
        'city_code' => $barangayMatch['city_code'] ?? $municipalityMatch['city_code'] ?? '',
        'barangay_code' => $barangayMatch['barangay_code'] ?? '',
    ];
}

function mebis_psgc_cache_path(string $path): string
{
    return $path . '.lookup-cache.json';
}

function mebis_load_psgc_cache(string $path): ?array
{
    $cachePath = mebis_psgc_cache_path($path);
    if (!is_file($cachePath) || !is_file($path)) {
        return null;
    }

    $payload = json_decode((string) @file_get_contents($cachePath), true);
    if (!is_array($payload)) {
        return null;
    }

    $mtime = (int) ($payload['source_mtime'] ?? 0);
    if ($mtime !== (int) @filemtime($path)) {
        return null;
    }

    $lookup = $payload['lookup'] ?? null;
    return is_array($lookup) ? $lookup : null;
}

function mebis_store_psgc_cache(string $path, array $lookup): void
{
    $cachePath = mebis_psgc_cache_path($path);
    $payload = [
        'source_mtime' => (int) @filemtime($path),
        'lookup' => $lookup,
    ];

    @file_put_contents($cachePath, json_encode($payload, JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function mebis_parse_uploaded_workbook(string $path, string $originalName, array $psgcLookup): array
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
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $spreadsheet = mebis_load_spreadsheet($path, $originalName, null, true);
            $sheet = mebis_find_sheet_by_name($spreadsheet, 'MEB');
        }

        if ($sheet === null) {
            throw new RuntimeException(sprintf('The workbook "%s" does not contain an MEB sheet.', $originalName));
        }

        $province = mebis_location_from_row($sheet, 2, ['province']);
        $municipality = mebis_location_from_row($sheet, 3, ['municipality', 'city']);
        $headerMap = mebis_find_header_map($sheet);

        if ($province === '' || $municipality === '') {
            throw new RuntimeException(sprintf('The workbook "%s" is missing province or municipality labels.', $originalName));
        }

        if (($headerMap['name_mode'] ?? 'split') !== 'split') {
            throw new RuntimeException(sprintf('The workbook "%s" does not separate last name, first name, middle name, and extName into their own columns.', $originalName));
        }

        if (!array_key_exists('birthdate', $headerMap)) {
            throw new RuntimeException(sprintf('The workbook "%s" is missing a birthdate column. Birthdates are required.', $originalName));
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

            $number = mebis_row_value($rowValues, $headerMap, 'entry_no');
            $nameParts = [
                'last_name' => mebis_row_value($rowValues, $headerMap, 'last_name'),
                'first_name' => mebis_row_value($rowValues, $headerMap, 'first_name'),
                'middle_name' => mebis_row_value($rowValues, $headerMap, 'middle_name'),
                'extName' => mebis_row_value($rowValues, $headerMap, 'extName'),
            ];

            $lastName = $nameParts['last_name'];
            $firstName = $nameParts['first_name'];

            if ($number === '' && $lastName === '' && $firstName === '') {
                continue;
            }

            if (!is_numeric($number)) {
                continue;
            }

            $barangay = mebis_row_value($rowValues, $headerMap, 'barangay_name');
            $psgcMatch = mebis_match_psgc($psgcLookup, $province, $municipality, $barangay);

            $record = [
                'last_name' => $lastName,
                'first_name' => $firstName,
                'middle_name' => $nameParts['middle_name'],
                'extName' => $nameParts['extName'],
                'birthdate' => mebis_date_value(
                    $sheet->getCell(Coordinate::stringFromColumnIndex(($headerMap['birthdate'] ?? 0) + 1) . $row)
                ),
                'region_code' => $psgcMatch['region_code'],
                'province_code' => $psgcMatch['province_code'],
                'city_code' => $psgcMatch['city_code'],
                'barangay_code' => $psgcMatch['barangay_code'],
                'region_name' => $psgcMatch['region_name'],
                'province_name' => $province,
                'city_name' => $municipality,
                'barangay_name' => $barangay,
                'File_number' => (int) $number,
            ];

            $rows[] = $record;
        }

        return $rows;
    } finally {
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }
}

function mebis_expected_headers(): array
{
    return [
        'last_name',
        'first_name',
        'middle_name',
        'extName',
        'birthdate',
        'region_code',
        'province_code',
        'city_code',
        'barangay_code',
        'region_name',
        'province_name',
        'city_name',
        'barangay_name',
        'File_number',
    ];
}
