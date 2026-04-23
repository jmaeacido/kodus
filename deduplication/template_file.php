<?php

declare(strict_types=1);

require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers/generator_history.php';

security_bootstrap_session();
security_require_method(['GET']);
auth_handle_page_access($conn);
auth_apply_security_headers();

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    exit('Missing file id.');
}

$entry = dedup_template_find_output(
    $conn,
    $id,
    (int) ($_SESSION['user_id'] ?? 0),
    (string) ($_SESSION['user_type'] ?? 'user')
);
if ($entry === null) {
    http_response_code(404);
    exit('Saved template file not found.');
}

$path = dedup_template_outputs_dir() . '/' . $entry['filename'];
if (!is_file($path)) {
    http_response_code(404);
    exit('Saved template file not found.');
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . basename((string) $entry['filename']) . '"');
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
while (ob_get_level() > 0) {
    ob_end_clean();
}
readfile($path);
exit;
