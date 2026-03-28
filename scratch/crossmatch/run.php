<?php
// crossmatch/run.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config.php';        // loads $conn (mysqli)
require_once __DIR__ . '/helpers/file_parser.php';
require_once __DIR__ . '/helpers/fuzzy.php';

function updateCrossmatchJob(mysqli $conn, int $jobId, int $percent, string $status, int $done = 0): void {
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
    // mark job as started
    $stmt = $conn->prepare("UPDATE crossmatch_jobs SET percent=0, status='Starting…', done=0 WHERE id=?");
    $stmt->bind_param("s", $jobId);
    $stmt->execute();
    $stmt->close();

    // load A (uploaded file)
    $stmt = $conn->prepare("UPDATE crossmatch_jobs SET status='Parsing first file…' WHERE id=?");
    $stmt->bind_param("s", $jobId);
    $stmt->execute();
    $stmt->close();

    if (!is_file($file1)) {
        throw new RuntimeException('Uploaded source file is missing.');
    }
    if ($mode === 'file_vs_file' && (!$file2 || !is_file($file2))) {
        throw new RuntimeException('Second source file is missing.');
    }

    $A = kds_parse_any($file1);
    if (!is_array($A)) {
        throw new RuntimeException("Parsing file1 failed!");
    }

    // load B (file2 OR DB)
    $stmt = $conn->prepare("UPDATE crossmatch_jobs SET status='Loading candidates…' WHERE id=?");
    $stmt->bind_param("s", $jobId);
    $stmt->execute();
    $stmt->close();

    if ($mode === 'file_vs_file') {
        $B = kds_parse_any($file2);
    } else {
        $B = loadAllCandidatesFromDb_mysqli($conn);
    }

    $deleteStmt = $conn->prepare("DELETE FROM crossmatch_results WHERE job_id = ?");
    $deleteStmt->bind_param("i", $jobId);
    $deleteStmt->execute();
    $deleteStmt->close();

    $total = max(1, count($A));
    $opts = [
        'birthdate_rule' => $job['rule'] ?? 'strict',
        'weights' => ['name'=>0.60,'birth'=>0.20,'address'=>0.20],
        'topN' => 3,
        'threshold' => (float)($job['threshold'] ?? 85)
    ];

    foreach ($A as $i => $rec) {
        $top3 = topCandidatesForRecord($rec, $B, $opts);

        // save result
        $stmt = $conn->prepare("INSERT INTO crossmatch_results (job_id, record_json, candidates_json) VALUES (?,?,?)");
        $stmt->bind_param("sss", $jobId, json_encode($rec), json_encode($top3));
        $stmt->execute();
        $stmt->close();

        // update progress
        $percent = (int)floor((($i+1)/$total) * 100);
        $status  = "Matching record " . ($i+1) . " of " . $total;

        $stmt = $conn->prepare("UPDATE crossmatch_jobs SET percent=?, status=?, done=0 WHERE id=?");
        $stmt->bind_param("iss", $percent, $status, $jobId);
        $stmt->execute();
        $stmt->close();
    }

    // finalize
    $stmt = $conn->prepare("UPDATE crossmatch_jobs SET percent=100, status='Completed', done=1 WHERE id=?");
    $stmt->bind_param("s", $jobId);
    $stmt->execute();
    $stmt->close();

    http_response_code(204);
    exit;

} catch (Throwable $e) {
    $err = $e->getMessage();
    error_log("Crossmatch run error: " . $err);

    $stmt = $conn->prepare("UPDATE crossmatch_jobs SET status=?, done=1, percent=100 WHERE id=?");
    $stmt->bind_param("ss", $err, $jobId);
    $stmt->execute();
    $stmt->close();

    http_response_code(500);
    echo "Error: $err";
    exit;
}
