<?php
// export_excel.php
require 'vendor/autoload.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/export_style_helpers.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

security_configure_runtime_for_web();
security_bootstrap_session();
$userId = $_SESSION['user_id'] ?? null;
$rows = $_SESSION['fuzzy_results'] ?? [];

if (!$userId) {
  header('HTTP/1.1 403 Forbidden');
  echo 'Unauthorized.';
  exit;
}

if (empty($rows)) {
  header('HTTP/1.1 400 Bad Request');
  echo 'No results to export.';
  exit;
}

$ss = new Spreadsheet();
$sh = $ss->getActiveSheet();
$sh->setTitle('Crossmatch Results');
$sh->insertNewRowBefore(1, 2);
$sh->setCellValue('A1', 'Crossmatch Results');
$sh->mergeCells('A1:T1');
$sh->setCellValue('A2', 'Generated on ' . date('F d, Y h:i A'));
$sh->mergeCells('A2:T2');

$headers = [
  'excel_lastName','excel_firstName','excel_middleName','excel_ext',
  'excel_birthDate','excel_barangay','excel_lgu','excel_province',
  'db_lastName','db_firstName','db_middleName','db_ext',
  'db_birthDate','db_barangay','db_lgu','db_province',
  'score','name_score','birth_score','addr_score'
];

$col = 'A';
foreach ($headers as $h) {
  $sh->setCellValue($col.'3', $h);
  $col++;
}

$r = 4;
foreach ($rows as $row) {
  $col = 'A';
  foreach ($headers as $h) {
    $sh->setCellValue($col.$r, $row[$h]);
    $col++;
  }
  $r++;
}

kodus_export_apply_uniform_style($ss, $sh, [
  'document_title' => 'Crossmatch Results',
  'title_range' => 'A1:T2',
  'header_range' => 'A3:T3',
  'data_range' => $r > 4 ? 'A4:T' . ($r - 1) : null,
  'freeze_pane' => 'A4',
  'auto_filter' => 'A3:T3',
  'left_align_ranges' => $r > 4 ? ['A4:P' . ($r - 1)] : [],
  'row_heights' => [1 => 28, 2 => 22, 3 => 30],
  'auto_size_columns' => range('A', 'T'),
]);

kodus_export_stream_xlsx($ss, 'Crossmatch_Results.xlsx');
