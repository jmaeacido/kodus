<?php

declare(strict_types=1);

require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers/template_generator.php';

auth_handle_page_access($conn);
security_require_method(['GET']);

$filename = basename((string) ($_GET['file'] ?? ''));
$path = crossmatch_template_outputs_dir() . '/' . $filename;
if ($filename === '' || !is_file($path)) {
    http_response_code(404);
    echo 'Template file not found.';
    exit;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
while (ob_get_level() > 0) {
    ob_end_clean();
}
readfile($path);
exit;
