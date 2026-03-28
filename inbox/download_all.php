<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();

// Ensure user logged in
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

if (empty($_POST['files'])) {
    die("No files selected");
}

$files = json_decode($_POST['files'], true);
if (!is_array($files) || empty($files)) {
    die("Invalid files");
}

$basePath = __DIR__ . "/uploads/contact_attachments/";
$zipName = "attachments_" . time() . ".zip";
$zipPath = sys_get_temp_dir() . "/" . $zipName;

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
    die("Could not create zip");
}

foreach ($files as $file) {
    $cleanName = basename((string) $file);
    if ($cleanName === '' || $cleanName !== (string) $file) {
        continue;
    }

    $filePath = realpath($basePath . $cleanName);
    $baseRealPath = realpath($basePath);
    if ($filePath && $baseRealPath && strpos($filePath, $baseRealPath) === 0 && file_exists($filePath)) {
        $zip->addFile($filePath, $cleanName);
    }
}
$zip->close();

header("Content-Type: application/zip");
header("Content-Disposition: attachment; filename=\"$zipName\"");
header("Content-Length: " . filesize($zipPath));
readfile($zipPath);

unlink($zipPath); // cleanup
exit;
