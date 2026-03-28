<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers/file_parser.php';
require_once __DIR__ . '/helpers/fuzzy.php';

function updateCrossmatchJobStatus(mysqli $conn, int $jobId, int $percent, string $status, int $done = 0): void {
    $stmt = $conn->prepare("UPDATE crossmatch_jobs SET percent=?, status=?, done=? WHERE id=?");
    $stmt->bind_param("isii", $percent, $status, $done, $jobId);
    $stmt->execute();
    $stmt->close();
}

$jobId = (int)($_GET['job'] ?? $_POST['job'] ?? ($_SESSION['kds_cfg']['job_id'] ?? 0));
if ($jobId <= 0) {
    http_response_code(400);
    echo "Missing job id.";
    exit;
}

$stmt = $conn->prepare("
    SELECT id, file1_name, file2_name, rule, threshold
    FROM crossmatch_jobs
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $jobId);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    http_response_code(404);
    echo "Job not found.";
    exit;
}

$file1 = __DIR__ . '/uploads/' . $job['file1_name'];
$file2 = !empty($job['file2_name']) ? (__DIR__ . '/uploads/' . $job['file2_name']) : null;
$mode = $file2 ? 'file_vs_file' : 'db_vs_file';
session_write_close();

try {
    updateCrossmatchJobStatus($conn, $jobId, 0, 'Starting...', 0);

    if (!is_file($file1)) {
        throw new RuntimeException('Uploaded source file is missing.');
    }
    if ($mode === 'file_vs_file' && (!$file2 || !is_file($file2))) {
        throw new RuntimeException('Second source file is missing.');
    }

    updateCrossmatchJobStatus($conn, $jobId, 0, 'Parsing first file...', 0);
    $records = kds_parse_any($file1);
    if (!is_array($records)) {
        throw new RuntimeException('Parsing file1 failed.');
    }

    updateCrossmatchJobStatus($conn, $jobId, 0, 'Loading candidates...', 0);
    $candidates = $mode === 'file_vs_file' ? kds_parse_any($file2) : loadAllCandidatesFromDb_mysqli($conn);

    $deleteStmt = $conn->prepare("DELETE FROM crossmatch_results WHERE job_id = ?");
    $deleteStmt->bind_param("i", $jobId);
    $deleteStmt->execute();
    $deleteStmt->close();

    $insertStmt = $conn->prepare("
        INSERT INTO crossmatch_results (job_id, record_json, candidates_json)
        VALUES (?, ?, ?)
    ");

    $total = max(1, count($records));
    $opts = [
        'birthdate_rule' => $job['rule'] ?? 'strict',
        'weights' => ['name' => 0.60, 'birth' => 0.20, 'address' => 0.20],
        'topN' => 3,
        'threshold' => (float)($job['threshold'] ?? 85),
    ];

    foreach ($records as $i => $record) {
        $topMatches = topCandidatesForRecord($record, $candidates, $opts);
        $recordJson = json_encode($record, JSON_UNESCAPED_UNICODE);
        $matchesJson = json_encode($topMatches, JSON_UNESCAPED_UNICODE);

        $insertStmt->bind_param("iss", $jobId, $recordJson, $matchesJson);
        $insertStmt->execute();

        $percent = (int) floor((($i + 1) / $total) * 100);
        updateCrossmatchJobStatus($conn, $jobId, $percent, 'Matching record ' . ($i + 1) . ' of ' . $total, 0);
    }

    $insertStmt->close();
    updateCrossmatchJobStatus($conn, $jobId, 100, 'Completed', 1);

    http_response_code(204);
    exit;
} catch (Throwable $e) {
    $err = $e->getMessage();
    error_log('Crossmatch run error: ' . $err);
    updateCrossmatchJobStatus($conn, $jobId, 100, $err, 1);

    http_response_code(500);
    echo 'Error: ' . $err;
    exit;
}
