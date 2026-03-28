<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die("Unauthorized.");
}

$rule = $_POST['rule'] ?? 'soft';
$threshold = max(50, min(100, intval($_POST['threshold'] ?? 85)));
$file = $_FILES['file'] ?? null;

if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die("File upload failed.");
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['csv','xlsx'])) {
    http_response_code(400);
    die("Only CSV and Excel files are allowed.");
}

$uploadDir = __DIR__ . '/uploads';
$logDir    = __DIR__ . '/logs';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
if (!is_dir($logDir)) mkdir($logDir, 0777, true);

// sanitize filename
$sanitizedName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file['name']);
$filename = time() . '_' . $sanitizedName;
$targetPath = $uploadDir . "/$filename";

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    die("Failed to save uploaded file.");
}

// Insert job record
$stmt = $conn->prepare("INSERT INTO deduplication_jobs 
    (user_id, file_name, rule, threshold, status, progress, created_at) 
    VALUES (?, ?, ?, ?, 'pending', 0, NOW())");
$stmt->bind_param("issi", $_SESSION['user_id'], $filename, $rule, $threshold);
$stmt->execute();
$jobId = $stmt->insert_id;
$stmt->close();

// Detect PHP binary
$phpBinary = defined('PHP_BINARY') ? PHP_BINARY : '';
if (!$phpBinary || !is_executable($phpBinary)) {
    $candidates = array_merge(
        glob('C:\\laragon\\bin\\php\\*\\php.exe') ?: [],
        [
            'C:\\xampp\\php\\php.exe',
            '/usr/bin/php',
            '/usr/local/bin/php',
        ]
    );

    foreach ($candidates as $p) {
        if (is_executable($p)) {
            $phpBinary = $p;
            break;
        }
    }
}
if (!$phpBinary) $phpBinary = 'php';

$workerPath = __DIR__ . '/worker_v2.php';
$jobIdArg   = intval($jobId);

// Background execution
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
if ($isWindows) {
    // Use full paths, detached, no window
    $cmd = sprintf(
        'start /B "" "%s" "%s" %d',
        $phpBinary,
        $workerPath,
        $jobIdArg
    );
    pclose(popen($cmd, "r"));
} else {
    $cmd = sprintf(
        'nohup %s %s %d > /dev/null 2>&1 &',
        escapeshellarg($phpBinary),
        escapeshellarg($workerPath),
        $jobIdArg
    );
    exec($cmd);
}

// Log launch command
file_put_contents(
    $logDir . '/launch_job_' . $jobId . '.log',
    date('c') . " - Job $jobId - LaunchCmd: $cmd\n",
    FILE_APPEND
);

// Redirect to progress
header("Location: progress_status.php?job=$jobId");
exit;
