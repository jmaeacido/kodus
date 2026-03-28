<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../export_style_helpers.php';

// PhpSpreadsheet
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;

// Validate input
$jobId   = intval($_GET['job'] ?? 0);
$groupId = isset($_GET['group']) ? intval($_GET['group']) : null;

if ($jobId <= 0) {
    die("Invalid job ID.");
}

// Fetch job details
$stmt = $conn->prepare("SELECT * FROM deduplication_jobs WHERE id=?");
$stmt->bind_param("i", $jobId);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    die("Job not found.");
}

// Build query depending on mode
if ($groupId) {
    $query = "SELECT group_id, row_data, similarity 
              FROM deduplication_results 
              WHERE job_id=? AND group_id=? 
              ORDER BY similarity DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $jobId, $groupId);
    $stmt->execute();
    $result = $stmt->get_result();
    $fileName = "deduplication_job{$jobId}_group{$groupId}.xlsx";
} else {
    $query = "SELECT group_id, row_data, similarity 
              FROM deduplication_results 
              WHERE job_id=? 
              ORDER BY group_id ASC, similarity DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jobId);
    $stmt->execute();
    $result = $stmt->get_result();
    $fileName = "deduplication_job{$jobId}_all.xlsx";
}

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Results");
$sheet->insertNewRowBefore(1, 2);
$sheet->setCellValue('A1', 'Deduplication Results');
$sheet->mergeCells('A1:C1');
$sheet->setCellValue('A2', $groupId ? "Group {$groupId} export generated on " . date('F d, Y h:i A') : 'Generated on ' . date('F d, Y h:i A'));
$sheet->mergeCells('A2:C2');

// Header row
$sheet->setCellValue('A3', 'Group ID');
$sheet->setCellValue('B3', 'Row Data');
$sheet->setCellValue('C3', 'Similarity (%)');

$rowIndex = 4;

// Write rows
while ($row = $result->fetch_assoc()) {
    $decoded = json_decode($row['row_data'], true);
    $rowNum = $decoded['_rowNumber'] ?? '?';

    // Remove _rowNumber before display
    if (is_array($decoded)) {
        unset($decoded['_rowNumber']);
        $rowData = implode(" | ", $decoded);
    } else {
        $rowData = $row['row_data'];
    }

    $sheet->setCellValue("A{$rowIndex}", $row['group_id']);
    $sheet->setCellValue("B{$rowIndex}", $rowData);
    $sheet->setCellValue("C{$rowIndex}", $row['similarity']);
    $rowIndex++;
}

kodus_export_apply_uniform_style($spreadsheet, $sheet, [
    'document_title' => 'Deduplication Results',
    'title_range' => 'A1:C2',
    'header_range' => 'A3:C3',
    'data_range' => $rowIndex > 4 ? 'A4:C' . ($rowIndex - 1) : null,
    'freeze_pane' => 'A4',
    'auto_filter' => 'A3:C3',
    'left_align_ranges' => $rowIndex > 4 ? ['B4:B' . ($rowIndex - 1)] : [],
    'row_heights' => [1 => 28, 2 => 22, 3 => 26],
    'column_widths' => ['A' => 14, 'B' => 80, 'C' => 16],
    'integer_ranges' => $rowIndex > 4 ? ['A4:A' . ($rowIndex - 1), 'C4:C' . ($rowIndex - 1)] : [],
]);

kodus_export_stream_xlsx($spreadsheet, $fileName);
