<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ini_set('memory_limit', '1536M');
set_time_limit(0);

require_once __DIR__ . '/../env_helpers.php';
require_once __DIR__ . '/../app_location_helpers.php';
require_once __DIR__ . '/../db_stmt_helpers.php';
require_once __DIR__ . '/helpers/jobs.php';
require_once __DIR__ . '/../vendor/autoload.php';

app_load_environment();
app_apply_current_timezone();

$jobToken = preg_replace('/[^a-f0-9]/i', '', (string) ($argv[1] ?? ''));
if ($jobToken === '') {
    fwrite(STDERR, "Missing MEBIS template job token.\n");
    exit(1);
}

$conn = new mysqli(
    app_env('DB_HOST', '127.0.0.1') ?? '127.0.0.1',
    app_env('DB_USERNAME', 'root') ?? 'root',
    app_env('DB_PASSWORD', '') ?? '',
    app_env('DB_NAME', '') ?? ''
);

if ($conn->connect_error) {
    fwrite(STDERR, 'Database connection failed: ' . $conn->connect_error . "\n");
    exit(1);
}

$conn->set_charset('utf8mb4');
app_apply_mysql_timezone($conn);

try {
    mebis_template_run_job($conn, $jobToken);
} catch (Throwable $e) {
    error_log('MEBIS LGU template background job failed: ' . $e->getMessage());
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} finally {
    $conn->close();
}
