<?php
// crossmatch/export.php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php'; // $conn

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// --- Validate inputs ---
$jobId  = $_POST['job_id'] ?? null;
$type   = $_POST['type'] ?? 'xlsx';
$accept = $_POST['accept'] ?? [];   // indices of accepted records
$choice = $_POST['choice'] ?? [];   // chosen candidate index per record

if (!$jobId) {
    http_response_code(400);
    echo "Missing job id.";
    exit;
}

// --- Fetch crossmatch job timestamp ---
$crossmatchedAt = '';

$stmtJob = $conn->prepare("SELECT created_at FROM crossmatch_jobs WHERE id = ? LIMIT 1");
$stmtJob->bind_param("i", $jobId);
$stmtJob->execute();
$resJob = $stmtJob->get_result();

if ($jobRow = $resJob->fetch_assoc()) {
    $crossmatchedAt = $jobRow['created_at'] ?? '';
}
$stmtJob->close();

// Format created_at for display
$crossmatchedLabel = 'Crossmatched: ';
if (!empty($crossmatchedAt)) {
    $timestamp = strtotime($crossmatchedAt);
    $crossmatchedLabel .= $timestamp ? date('F d, Y h:i A', $timestamp) : $crossmatchedAt;
} else {
    $crossmatchedLabel .= 'N/A';
}

// --- Fetch results for this job ---
$stmt = $conn->prepare("SELECT record_json, candidates_json FROM crossmatch_results WHERE job_id=? ORDER BY id ASC");
$stmt->bind_param("i", $jobId);
$stmt->execute();
$res = $stmt->get_result();

$results = [];
while ($row = $res->fetch_assoc()) {
    $results[] = [
        'record'     => json_decode($row['record_json'], true),
        'candidates' => json_decode($row['candidates_json'], true)
    ];
}
$stmt->close();

if (empty($results)) {
    http_response_code(400);
    echo "No results found.";
    exit;
}

// --- Build export rows ---
$exportRows = [];
foreach ($accept as $idxStr) {
    $i = (int)$idxStr;
    if (!isset($results[$i])) continue;
    $row = $results[$i];

    if (empty($row['candidates'])) {
        $cand = [
            'candidate' => [
                'lastName'   => 'NO MATCH',
                'firstName'  => 'NO MATCH',
                'middleName' => 'NO MATCH',
                'ext'        => 'NO MATCH',
                'birthDate'  => 'NO MATCH',
                'barangay'   => 'NO MATCH',
                'lgu'        => 'NO MATCH',
                'province'   => 'NO MATCH'
            ],
            'score'      => 'NO MATCH',
            'nameScore'  => 'NO MATCH',
            'birthScore' => 'NO MATCH',
            'addrScore'  => 'NO MATCH'
        ];
    } else {
        $cIdx = isset($choice[$i]) ? (int)$choice[$i] : 0;
        $cand = $row['candidates'][$cIdx] ?? $row['candidates'][0];
    }

    $exportRows[] = [
        'u_lastName'   => $row['record']['lastName'] ?? '',
        'u_firstName'  => $row['record']['firstName'] ?? '',
        'u_middleName' => $row['record']['middleName'] ?? '',
        'u_ext'        => $row['record']['ext'] ?? '',
        'u_birthDate'  => $row['record']['birthDate'] ?? '',
        'u_barangay'   => $row['record']['barangay'] ?? '',
        'u_lgu'        => $row['record']['lgu'] ?? '',
        'u_province'   => $row['record']['province'] ?? '',

        'm_lastName'   => $cand['candidate']['lastName'] ?? '',
        'm_firstName'  => $cand['candidate']['firstName'] ?? '',
        'm_middleName' => $cand['candidate']['middleName'] ?? '',
        'm_ext'        => $cand['candidate']['ext'] ?? '',
        'm_birthDate'  => $cand['candidate']['birthDate'] ?? '',
        'm_barangay'   => $cand['candidate']['barangay'] ?? '',
        'm_lgu'        => $cand['candidate']['lgu'] ?? '',
        'm_province'   => $cand['candidate']['province'] ?? '',
        'score'        => $cand['score'] ?? '',
        'nameScore'    => $cand['nameScore'] ?? '',
        'birthScore'   => $cand['birthScore'] ?? '',
        'addrScore'    => $cand['addrScore'] ?? ''
    ];
}

// --- Create Spreadsheet ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Crossmatch Export');

// =====================================================
// TITLE
// =====================================================
$sheet->setCellValue('A1', 'Crossmatch Export Results');
$sheet->mergeCells('A1:T1');

$sheet->setCellValue('A2', $crossmatchedLabel);
$sheet->mergeCells('A2:T2');

// =====================================================
// HEADERS
// =====================================================
$headers = [
    'Uploaded Last Name',
    'Uploaded First Name',
    'Uploaded Middle Name',
    'Uploaded Ext',
    'Uploaded BirthDate',
    'Uploaded Barangay',
    'Uploaded LGU',
    'Uploaded Province',
    'Matched Last Name',
    'Matched First Name',
    'Matched Middle Name',
    'Matched Ext',
    'Matched BirthDate',
    'Matched Barangay',
    'Matched LGU',
    'Matched Province',
    'Score',
    'Name Score',
    'Birth Score',
    'Addr Score'
];

$sheet->fromArray($headers, null, 'A3');

// =====================================================
// DATA ROWS
// =====================================================
$rowNum = 4;
foreach ($exportRows as $row) {
    $sheet->fromArray([
        $row['u_lastName'],
        $row['u_firstName'],
        $row['u_middleName'],
        $row['u_ext'],
        $row['u_birthDate'],
        $row['u_barangay'],
        $row['u_lgu'],
        $row['u_province'],
        $row['m_lastName'],
        $row['m_firstName'],
        $row['m_middleName'],
        $row['m_ext'],
        $row['m_birthDate'],
        $row['m_barangay'],
        $row['m_lgu'],
        $row['m_province'],
        $row['score'],
        $row['nameScore'],
        $row['birthScore'],
        $row['addrScore']
    ], null, "A{$rowNum}");
    $rowNum++;
}

// =====================================================
// STYLING
// =====================================================
$sheet->getStyle('A1:T2')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 14
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
]);

$sheet->getStyle('A3:T3')->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 10
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '374151']
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '808080']
        ]
    ]
]);

$sheet->getRowDimension(1)->setRowHeight(30);
$sheet->getRowDimension(2)->setRowHeight(25);
$sheet->getRowDimension(3)->setRowHeight(42);

// Freeze pane below header
$sheet->freezePane('A4');

// Add autofilter to header row
$sheet->setAutoFilter('A3:T3');

// Column widths
$sheet->getColumnDimension('A')->setWidth(18);
$sheet->getColumnDimension('B')->setWidth(18);
$sheet->getColumnDimension('C')->setWidth(18);
$sheet->getColumnDimension('D')->setWidth(10);
$sheet->getColumnDimension('E')->setWidth(14);
$sheet->getColumnDimension('F')->setWidth(18);
$sheet->getColumnDimension('G')->setWidth(18);
$sheet->getColumnDimension('H')->setWidth(18);

$sheet->getColumnDimension('I')->setWidth(18);
$sheet->getColumnDimension('J')->setWidth(18);
$sheet->getColumnDimension('K')->setWidth(18);
$sheet->getColumnDimension('L')->setWidth(10);
$sheet->getColumnDimension('M')->setWidth(14);
$sheet->getColumnDimension('N')->setWidth(18);
$sheet->getColumnDimension('O')->setWidth(18);
$sheet->getColumnDimension('P')->setWidth(18);

$sheet->getColumnDimension('Q')->setWidth(12);
$sheet->getColumnDimension('R')->setWidth(12);
$sheet->getColumnDimension('S')->setWidth(12);
$sheet->getColumnDimension('T')->setWidth(12);

// Body styling
$lastDataRow = $rowNum - 1;
if ($lastDataRow >= 4) {
    $sheet->getStyle("A4:T{$lastDataRow}")->applyFromArray([
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER,
            'horizontal' => Alignment::HORIZONTAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '808080']
            ]
        ]
    ]);

    // Left align text-heavy columns
    $sheet->getStyle("A4:P{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
}

// --- Output file ---
$filename = "Crossmatch_" . date('Ymd_His');

if ($type === 'csv') {
    header('Content-Type: text/csv');
    header("Content-Disposition: attachment;filename=\"{$filename}.csv\"");
    header('Cache-Control: max-age=0');

    $writer = new Csv($spreadsheet);
    $writer->setDelimiter(",");
    $writer->setEnclosure('"');
    $writer->setSheetIndex(0);

    if (ob_get_length()) ob_end_clean();
    $writer->save('php://output');
    exit;
} else {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment;filename=\"{$filename}.xlsx\"");
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);

    if (ob_get_length()) ob_end_clean();
    $writer->save('php://output');
    exit;
}
?>