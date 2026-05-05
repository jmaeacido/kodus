<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers/validator.php';
require_once __DIR__ . '/helpers/jobs.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();

function crossmatch_is_ajax_request(): bool
{
    $requestedWith = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

    return $requestedWith === 'xmlhttprequest' || strpos($accept, 'application/json') !== false;
}

try {
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        throw new RuntimeException('Unauthorized.');
    }

    $mode = $_POST['mode'] ?? '';
    $threshold = max(0, min(100, (int) ($_POST['threshold'] ?? 85)));
    $birthRule = $_POST['birthdate_rule'] ?? 'strict';

    if (!in_array($mode, ['file_vs_file', 'db_vs_file'], true)) {
        throw new RuntimeException('Invalid mode.');
    }

    $uploadDir = __DIR__ . '/uploads/';
    $file1 = validate_and_store($_FILES['file1'] ?? [], $uploadDir);
    $file2 = null;
    if ($mode === 'file_vs_file') {
        $file2 = validate_and_store($_FILES['file2'] ?? [], $uploadDir);
    }

    crossmatch_ensure_job_schema($conn);

    $initStatus = 'Waiting to start...';
    $stmt = $conn->prepare(
        'INSERT INTO crossmatch_jobs (user_id, percent, status, done, file1_name, file2_name, rule, threshold)
         VALUES (?, 0, ?, 0, ?, ?, ?, ?)'
    );
    $f1ForDb = basename($file1);
    $f2ForDb = $file2 ? basename($file2) : null;
    $stmt->bind_param('issssi', $userId, $initStatus, $f1ForDb, $f2ForDb, $birthRule, $threshold);
    $stmt->execute();

    $jobId = $stmt->insert_id;
    $stmt->close();

    $_SESSION['kds_cfg'] = [
        'job_id' => $jobId,
        'mode' => $mode,
        'file1' => $file1,
        'file2' => $file2,
        'threshold' => $threshold,
        'birthdate_rule' => $birthRule,
    ];
    $_SESSION['kds_progress'] = 0;
    $_SESSION['kds_done'] = false;
    $_SESSION['kds_results'] = [];

    crossmatch_start_background_job((int) $jobId);

    if (crossmatch_is_ajax_request()) {
        security_send_json([
            'success' => true,
            'message' => 'Crossmatching job started in the background.',
            'job_id' => $jobId,
            'redirect' => "start.php?job={$jobId}",
        ]);
    }

    header("Location: start.php?job={$jobId}");
    exit;
} catch (Throwable $e) {
    if (crossmatch_is_ajax_request()) {
        security_send_json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 400);
    }

    $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo "<script src='../plugins/sweetalert2/sweetalert2.min.js'></script>
          <script>
            Swal.fire({
              icon:'error',
              title:'Upload error',
              text:'{$msg}'
            }).then(()=>history.back());
          </script>";
}
