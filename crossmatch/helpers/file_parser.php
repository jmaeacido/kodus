<?php
// crossmatch/helpers/file_parser.php
require_once __DIR__ . '/../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

const KDS_REQUIRED_HEADERS = ['lastname','firstname','middlename','ext','birthdate','barangay','lgu','province'];

function kds_header_map(array $headerRow): array {
    $map = [];
    foreach ($headerRow as $i => $heading) {
        $key = strtolower(trim((string)$heading));
        if (in_array($key, KDS_REQUIRED_HEADERS, true)) $map[$key] = $i;
    }
    foreach (KDS_REQUIRED_HEADERS as $req) {
        if (!array_key_exists($req, $map)) throw new RuntimeException("Missing required column: {$req}");
    }
    return $map;
}

function kds_filter_empty(array $rows): array {
    return array_values(array_filter($rows, function($x){
        return trim(($x['lastName'] ?? '') . ($x['firstName'] ?? '')) !== '';
    }));
}

function kds_parse_csv(string $path): array {
    if (($h = fopen($path, 'r')) === false) throw new RuntimeException('Cannot open CSV file.');
    $first = fgets($h);
    if ($first === false) { fclose($h); return []; }
    $first = preg_replace('/^\xEF\xBB\xBF/', '', $first); // BOM
    $header = str_getcsv($first);
    $map = kds_header_map($header);
    $rows = [];
    while (($data = fgetcsv($h)) !== false) {
        $rows[] = [
            'lastName'  => (string)($data[$map['lastname']]  ?? ''),
            'firstName' => (string)($data[$map['firstname']] ?? ''),
            'middleName'=> (string)($data[$map['middlename']]?? ''),
            'ext'       => (string)($data[$map['ext']]       ?? ''),
            'birthDate' => (string)($data[$map['birthdate']] ?? ''),
            'barangay'  => (string)($data[$map['barangay']]  ?? ''),
            'lgu'       => (string)($data[$map['lgu']]       ?? ''),
            'province'  => (string)($data[$map['province']]  ?? ''),
        ];
    }
    fclose($h);
    return kds_filter_empty($rows);
}

function kds_parse_xlsx(string $path): array {
    $ss = IOFactory::load($path);
    $sheet = $ss->getActiveSheet();
    $all = $sheet->toArray(null, true, true, true);
    if (!$all || count($all) < 1) return [];

    $headerRow = $all[1];

    $hdrNumeric = [];
    $i = 0;
    foreach ($headerRow as $col) {
        $hdrNumeric[$i++] = $col;
    }

    $map = kds_header_map($hdrNumeric);

    $rows = [];
    $rowIndex = 2;
    while (isset($all[$rowIndex])) {
        $r = $all[$rowIndex];
        $keys = array_keys($r);
        $rows[] = [
            'lastName'  => (string)($r[$keys[$map['lastname']] ?? null]  ?? ''),
            'firstName' => (string)($r[$keys[$map['firstname']] ?? null] ?? ''),
            'middleName'=> (string)($r[$keys[$map['middlename']] ?? null]?? ''),
            'ext'       => (string)($r[$keys[$map['ext']] ?? null]       ?? ''),
            'birthDate' => (string)($r[$keys[$map['birthdate']] ?? null] ?? ''),
            'barangay'  => (string)($r[$keys[$map['barangay']] ?? null]  ?? ''),
            'lgu'       => (string)($r[$keys[$map['lgu']] ?? null]       ?? ''),
            'province'  => (string)($r[$keys[$map['province']] ?? null]  ?? '')
        ];
        $rowIndex++;
    }
    return kds_filter_empty($rows);
}

function kds_parse_any(string $path): array {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return $ext === 'csv' ? kds_parse_csv($path) : kds_parse_xlsx($path);
}
