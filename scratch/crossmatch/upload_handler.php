<?php
// crossmatch/upload_handler.php
session_start();
require_once __DIR__.'/../config.php'; // for $conn
require_once __DIR__.'/helpers/validator.php';

try {
    $mode = $_POST['mode'] ?? '';
    $threshold = max(0, min(100, (int)($_POST['threshold'] ?? 85)));
    $birthRule = $_POST['birthdate_rule'] ?? 'strict';

    if (!in_array($mode, ['file_vs_file','db_vs_file'], true)) {
        throw new RuntimeException('Invalid mode.');
    }

    $uploadDir = __DIR__.'/uploads/';
    $file1 = validate_and_store($_FILES['file1'] ?? [], $uploadDir);
    $file2 = null;
    if ($mode === 'file_vs_file') {
        $file2 = validate_and_store($_FILES['file2'] ?? [], $uploadDir);
    }

    // insert job record with initial status
    $initStatus = "Waiting to start…";
    $stmt = $conn->prepare("
        INSERT INTO crossmatch_jobs (percent, status, done, file1_name, file2_name, rule, threshold) 
        VALUES (0, ?, 0, ?, ?, ?, ?)
    ");
    $f1ForDb = basename($file1);
    $f2ForDb = $file2 ? basename($file2) : null;
    $stmt->bind_param("ssssi", $initStatus, $f1ForDb, $f2ForDb, $birthRule, $threshold);
    $stmt->execute();

    $jobId = $stmt->insert_id;
    $stmt->close();

    // store config in session
    $_SESSION['kds_cfg'] = [
        'job_id' => $jobId,
        'mode' => $mode,
        'file1'=> $file1,
        'file2'=> $file2,
        'threshold' => $threshold,
        'birthdate_rule' => $birthRule
    ];

    // session-only values are optional now (progress is tracked in DB)
    $_SESSION['kds_progress'] = 0;
    $_SESSION['kds_done'] = false;
    $_SESSION['kds_results'] = [];

    header("Location: start.php?job={$jobId}");
    exit;

} catch (Throwable $e) {
    $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES);
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
          <script>
            Swal.fire({
              icon:'error',
              title:'Upload error',
              text:'{$msg}'
            }).then(()=>history.back());
          </script>";
}
