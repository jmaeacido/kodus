<?php
// crossmatch/export.php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php'; // $conn
require_once __DIR__ . '/../export_style_helpers.php';
require_once __DIR__ . '/helpers/jobs.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();

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

$job = crossmatch_fetch_accessible_job(
    $conn,
    (int) $jobId,
    (int) ($_SESSION['user_id'] ?? 0),
    (string) ($_SESSION['user_type'] ?? ''),
    'id, user_id, created_at'
);
if (!$job) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

// --- Fetch crossmatch job timestamp ---
$crossmatchedAt = '';

$crossmatchedAt = $job['created_at'] ?? '';

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
$res = db_stmt_fetch_all_assoc($stmt);

$results = [];
foreach ($res as $row) {
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

$lastDataRow = $rowNum - 1;

kodus_export_apply_uniform_style($spreadsheet, $sheet, [
    'document_title' => 'Crossmatch Export Results',
    'title_range' => 'A1:T2',
    'header_range' => 'A3:T3',
    'data_range' => $lastDataRow >= 4 ? "A4:T{$lastDataRow}" : null,
    'freeze_pane' => 'A4',
    'auto_filter' => 'A3:T3',
    'left_align_ranges' => $lastDataRow >= 4 ? ["A4:P{$lastDataRow}"] : [],
    'row_heights' => [1 => 30, 2 => 25, 3 => 42],
    'column_widths' => [
        'A' => 18, 'B' => 18, 'C' => 18, 'D' => 10, 'E' => 14, 'F' => 18, 'G' => 18, 'H' => 18,
        'I' => 18, 'J' => 18, 'K' => 18, 'L' => 10, 'M' => 14, 'N' => 18, 'O' => 18, 'P' => 18,
        'Q' => 12, 'R' => 12, 'S' => 12, 'T' => 12,
    ],
]);

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
    kodus_export_stream_xlsx($spreadsheet, "{$filename}.xlsx");
}
?>
