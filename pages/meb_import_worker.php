<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/meb_import_helpers.php';

$jobToken = preg_replace('/[^a-f0-9]/i', '', (string) ($argv[1] ?? ''));
if ($jobToken === '') {
    fwrite(STDERR, "Missing MEB import job token.\n");
    exit(1);
}

try {
    meb_import_run_job($conn, $jobToken);
} catch (Throwable $e) {
    error_log('MEB import worker failed: ' . $e->getMessage());
    exit(1);
}
