<?php
session_start();
require_once __DIR__ . '/../config.php';

// PhpSpreadsheet
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

// Header row
$sheet->setCellValue('A1', 'Group ID');
$sheet->setCellValue('B1', 'Row Data');
$sheet->setCellValue('C1', 'Similarity (%)');

$rowIndex = 2;

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

// Auto-size columns
foreach (range('A', 'C') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Output file for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$fileName\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;