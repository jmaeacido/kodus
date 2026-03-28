<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/helpers/validator.php';

function dedupFriendlyColumnLabel(string $field): string
{
    $labels = [
        'lastName' => 'Last Name',
        'firstName' => 'First Name',
        'middleName' => 'Middle Name',
        'ext' => 'Extension',
        'birthDate' => 'Birth Date',
        'barangay' => 'Barangay',
        'lgu' => 'LGU / City / Municipality',
        'province' => 'Province',
    ];

    return $labels[$field] ?? $field;
}

function dedupRenderUploadError(string $message): never
{
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $html = nl2br($safeMessage);

    if (preg_match('/Missing required column\(s\):\s*(.+?)\./i', $message, $matches)) {
        $fields = array_filter(array_map('trim', explode(',', $matches[1])));
        $items = array_map(
            static fn(string $field) => '<li style="margin-bottom:8px; line-height:1.45;">'
                . '<span style="display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; margin-right:8px; border-radius:999px; background:#eef2ff; color:#1d4ed8; font-size:12px; font-weight:700;">•</span>'
                . '<span style="font-size:15px; color:#1f2937; font-weight:600;">'
                . htmlspecialchars(dedupFriendlyColumnLabel($field), ENT_QUOTES, 'UTF-8')
                . '</span></li>',
            $fields
        );

        $html = '<div style="text-align:left;">'
            . '<div style="font-size:15px; line-height:1.6; color:#334155;">'
            . 'The uploaded file does not match the <strong>deduplication template</strong>.'
            . '<br>Please make sure your file includes these required column headers:'
            . '</div>'
            . '<ul style="list-style:none; text-align:left; margin:16px 0 0; padding-left:0;">'
            . implode('', $items)
            . '</ul>'
            . '<div style="margin-top:18px; padding:14px 16px; border-radius:12px; background:#f8fafc; border:1px solid #dbeafe;">'
            . '<div style="font-size:13px; font-weight:700; letter-spacing:0.02em; text-transform:uppercase; color:#1d4ed8; margin-bottom:6px;">Need the correct file?</div>'
            . '<a href="helpers/Deduplication_Template.xlsx" download style="display:inline-flex; align-items:center; gap:8px; font-size:15px; font-weight:700; color:#2563eb; text-decoration:none;">'
            . 'Download the deduplication template'
            . '</a>'
            . '<div style="margin-top:6px; font-size:13px; color:#475569;">Use this template, fill in your records, then upload it again.</div>'
            . '</div>'
            . '</div>';
    }

    http_response_code(400);
    echo "<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1'>
  <title>Deduplication Upload Error</title>
  <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
</head>
<body>
  <script>
    window.addEventListener('load', function () {
      Swal.fire({
        icon: 'error',
        title: 'Invalid Template',
        html: " . json_encode($html) . ",
        confirmButtonText: 'Back to Upload',
        allowOutsideClick: false
      }).then(function () {
        window.location.href = 'index.php';
      });
    });
  </script>
</body>
</html>";
    exit;
}

function dedupPhpHasZipSupport(string $phpBinary): bool
{
    $phpBinary = trim($phpBinary);
    if ($phpBinary === '' || !is_executable($phpBinary)) {
        return false;
    }

    $command = '"' . $phpBinary . '" -r "echo class_exists(\'ZipArchive\') ? \'1\' : \'0\';"';
    $output = @shell_exec($command);

    return trim((string) $output) === '1';
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die("Unauthorized.");
}

$rule = $_POST['rule'] ?? 'soft';
$threshold = max(50, min(100, intval($_POST['threshold'] ?? 85)));
$file = $_FILES['file'] ?? null;

if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    dedupRenderUploadError('File upload failed.');
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['csv','xlsx'])) {
    dedupRenderUploadError('Only CSV and Excel files are allowed.');
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
    dedupRenderUploadError('Failed to save uploaded file.');
}

try {
    validateAndParseFile($targetPath);
} catch (Throwable $e) {
    if (is_file($targetPath)) {
        unlink($targetPath);
    }

    dedupRenderUploadError($e->getMessage());
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
$phpBinaryName = strtolower(basename(str_replace('\\', '/', $phpBinary)));
if ($phpBinaryName !== 'php.exe' || !is_executable($phpBinary)) {
    $candidates = array_merge(
        glob('C:\\laragon\\bin\\php\\php-*\\php.exe') ?: [],
        glob('C:\\laragon\\bin\\php\\archive\\php-*\\php.exe') ?: [],
        [
            'C:\\xampp\\php\\php.exe',
            '/usr/bin/php',
            '/usr/local/bin/php',
        ]
    );

    $candidates = array_values(array_unique($candidates));
    rsort($candidates, SORT_NATURAL);

    foreach ($candidates as $p) {
        if (
            is_executable($p)
            && strtolower(basename(str_replace('\\', '/', $p))) === 'php.exe'
            && dedupPhpHasZipSupport($p)
        ) {
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
    $cmd = sprintf(
        'cmd /c start "" /B "%s" "%s" %d',
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
