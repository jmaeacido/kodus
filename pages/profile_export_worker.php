<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/profile_export_job_helpers.php';

$jobToken = preg_replace('/[^a-f0-9]/i', '', (string) ($argv[1] ?? ''));
if ($jobToken === '') {
    fwrite(STDERR, "Missing profile export job token.\n");
    exit(1);
}

try {
    meb_profile_export_run_job($conn, $jobToken);
} catch (Throwable $e) {
    error_log('MEB profile export worker failed: ' . $e->getMessage());
    exit(1);
}
