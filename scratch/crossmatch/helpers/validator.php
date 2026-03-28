<?php
// crossmatch/helpers/validator.php
function validate_and_store(array $file, string $uploadDir): string {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('No file uploaded.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx','csv'], true)) {
        throw new RuntimeException('Invalid file type. Allowed: .xlsx, .csv');
    }

    if (($file['size'] ?? 0) > 15 * 1024 * 1024) {
        throw new RuntimeException('File too large. Max 15MB.');
    }

    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    // Generate safe filename
    $timestamp = time();
    $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
    $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $originalName);
    $newName = $timestamp . '_' . $safeName . '.' . $ext;

    $dest = rtrim($uploadDir, '/') . '/' . $newName;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Failed to store uploaded file.');
    }

    // ✅ Return relative path for processing
    return 'uploads/' . $newName;
}