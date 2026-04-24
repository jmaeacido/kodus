<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/helpers/history.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

security_bootstrap_session();
security_require_method(['GET']);
auth_enforce_admin_generator_access();

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    exit('Missing file id.');
}

$entry = mebis_template_find_output($conn, $id);
if ($entry === null) {
    http_response_code(404);
    exit('Saved template file not found.');
}

$path = mebis_template_outputs_dir() . '/' . $entry['filename'];
if (!is_file($path)) {
    http_response_code(404);
    exit('Saved template file not found.');
}

$csvFilename = preg_replace('/\.xlsx\z/i', '.csv', basename((string) $entry['filename']));
if (!is_string($csvFilename) || $csvFilename === basename((string) $entry['filename'])) {
    $csvFilename = basename((string) $entry['filename']) . '.csv';
}

$spreadsheet = IOFactory::load($path);

try {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $csvFilename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $writer = new Csv($spreadsheet);
    $writer->setSheetIndex(0);
    $writer->setUseBOM(true);
    $writer->save('php://output');
} finally {
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);
}

exit;
