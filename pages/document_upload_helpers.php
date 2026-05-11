<?php

function tracking_upload_dir(): string
{
    return __DIR__ . '/uploads/';
}

function tracking_upload_allowed_types(): array
{
    return [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream',
        ],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/octet-stream',
        ],
        'xlsm' => [
            'application/vnd.ms-excel.sheet.macroEnabled.12',
            'application/vnd.ms-excel',
            'application/zip',
            'application/octet-stream',
        ],
    ];
}

function tracking_parse_size_to_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float) $value;

    switch ($unit) {
        case 'g':
            $number *= 1024;
            // no break
        case 'm':
            $number *= 1024;
            // no break
        case 'k':
            $number *= 1024;
            break;
    }

    return (int) $number;
}

function tracking_format_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return rtrim(rtrim(number_format($bytes / (1024 * 1024), 1), '0'), '.') . ' MB';
    }

    if ($bytes >= 1024) {
        return rtrim(rtrim(number_format($bytes / 1024, 1), '0'), '.') . ' KB';
    }

    return $bytes . ' bytes';
}

function tracking_reject_oversized_post(): void
{
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postLimit = tracking_parse_size_to_bytes((string) ini_get('post_max_size'));

    if ($contentLength > 0 && $postLimit > 0 && $contentLength > $postLimit) {
        security_send_json([
            'success' => false,
            'message' => 'The selected files are too large. Please keep the total upload under ' . tracking_format_bytes($postLimit) . '.',
        ], 413);
    }
}

function tracking_ensure_file_columns(mysqli $conn, string $tableName): void
{
    if (!in_array($tableName, ['incoming', 'outgoing'], true)) {
        return;
    }

    $columns = ['file_name', 'file_type', 'file_size'];
    foreach ($columns as $column) {
        $stmt = $conn->prepare("
            SELECT DATA_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ");

        if (!$stmt) {
            continue;
        }

        $stmt->bind_param('ss', $tableName, $column);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
        $stmt->close();

        $dataType = strtolower((string) ($row['DATA_TYPE'] ?? ''));
        if ($dataType !== '' && !in_array($dataType, ['text', 'mediumtext', 'longtext'], true)) {
            $conn->query("ALTER TABLE `{$tableName}` MODIFY `{$column}` TEXT NULL");
        }
    }
}

function tracking_normalize_uploaded_files(array $fileInput): array
{
    $names = $fileInput['name'] ?? [];
    if (!is_array($names)) {
        return [[
            'name' => $fileInput['name'] ?? '',
            'type' => $fileInput['type'] ?? '',
            'tmp_name' => $fileInput['tmp_name'] ?? '',
            'error' => $fileInput['error'] ?? UPLOAD_ERR_NO_FILE,
            'size' => $fileInput['size'] ?? 0,
        ]];
    }

    $files = [];
    $names = array_values($names);
    $types = array_values((array) ($fileInput['type'] ?? []));
    $tmpNames = array_values((array) ($fileInput['tmp_name'] ?? []));
    $errors = array_values((array) ($fileInput['error'] ?? []));
    $sizes = array_values((array) ($fileInput['size'] ?? []));

    foreach ($names as $index => $name) {
        $files[] = [
            'name' => $name,
            'type' => $types[$index] ?? '',
            'tmp_name' => $tmpNames[$index] ?? '',
            'error' => $errors[$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $sizes[$index] ?? 0,
        ];
    }

    return $files;
}

function tracking_has_uploaded_files(string $fieldName = 'file'): bool
{
    if (!isset($_FILES[$fieldName])) {
        return false;
    }

    foreach (tracking_normalize_uploaded_files($_FILES[$fieldName]) as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE && (string) ($file['name'] ?? '') !== '') {
            return true;
        }
    }

    return false;
}

function tracking_unique_upload_name(string $originalName, int $index): string
{
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
    $sanitizedBaseName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $baseName);
    $sanitizedBaseName = trim((string) $sanitizedBaseName, '_-');

    if ($sanitizedBaseName === '') {
        $sanitizedBaseName = 'document';
    }

    if (strlen($sanitizedBaseName) > 80) {
        $sanitizedBaseName = substr($sanitizedBaseName, 0, 80);
    }

    $suffix = date('Ymd_His') . '_' . bin2hex(random_bytes(3));
    if ($index > 0) {
        $suffix .= '_' . ($index + 1);
    }

    return strtolower($sanitizedBaseName . '_' . $suffix . '.' . $extension);
}

function tracking_upload_error_message(int $error, string $originalName): string
{
    $label = $originalName !== '' ? basename($originalName) : 'The selected file';
    $fileLimit = tracking_parse_size_to_bytes((string) ini_get('upload_max_filesize'));

    switch ($error) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return $label . ' is too large. Please keep each file under ' . tracking_format_bytes($fileLimit) . '.';
        case UPLOAD_ERR_PARTIAL:
            return $label . ' was only partially uploaded. Please try again.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'The server upload temporary folder is missing.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'The server could not write the uploaded file.';
        case UPLOAD_ERR_EXTENSION:
            return 'A server extension blocked the uploaded file.';
        default:
            return 'File upload failed. Please try again.';
    }
}

function tracking_save_uploaded_files(string $fieldName = 'file'): array
{
    if (!tracking_has_uploaded_files($fieldName)) {
        return [
            'file_name' => null,
            'file_type' => null,
            'file_size' => null,
            'paths' => [],
        ];
    }

    $uploadDir = tracking_upload_dir();
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new Exception('Upload directory could not be created.');
    }

    $allowedTypes = tracking_upload_allowed_types();
    $savedNames = [];
    $savedTypes = [];
    $savedSizes = [];
    $savedPaths = [];

    foreach (tracking_normalize_uploaded_files($_FILES[$fieldName]) as $index => $file) {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $originalName = (string) ($file['name'] ?? '');

        if ($error === UPLOAD_ERR_NO_FILE && $originalName === '') {
            continue;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new Exception(tracking_upload_error_message($error, $originalName));
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new Exception('The file upload field was received without a valid temporary file. Please try again.');
        }

        $detectedType = security_detect_upload_mime($tmpPath);

        if (!isset($allowedTypes[$extension]) || !in_array($detectedType, $allowedTypes[$extension], true)) {
            throw new Exception('Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG, XLSX, XLSM.');
        }

        $savedName = tracking_unique_upload_name(basename($originalName), $index);
        $targetPath = $uploadDir . $savedName;

        if (!move_uploaded_file($tmpPath, $targetPath)) {
            throw new Exception('File upload failed.');
        }

        $savedNames[] = $savedName;
        $savedTypes[] = $detectedType;
        $savedSizes[] = (string) ((int) ($file['size'] ?? 0));
        $savedPaths[] = $targetPath;
    }

    return [
        'file_name' => $savedNames ? implode(',', $savedNames) : null,
        'file_type' => $savedTypes ? implode(',', $savedTypes) : null,
        'file_size' => $savedSizes ? implode(',', $savedSizes) : null,
        'paths' => $savedPaths,
    ];
}

function tracking_split_file_names(?string $fileNames): array
{
    $fileNames = trim((string) $fileNames);
    if ($fileNames === '') {
        return [];
    }

    $decoded = json_decode($fileNames, true);
    if (is_array($decoded)) {
        return array_values(array_filter(array_map('strval', $decoded), static fn($name) => trim($name) !== ''));
    }

    return array_values(array_filter(array_map('trim', explode(',', $fileNames)), static fn($name) => $name !== ''));
}

function tracking_delete_files(?string $fileNames): void
{
    foreach (tracking_split_file_names($fileNames) as $fileName) {
        $path = tracking_upload_dir() . basename($fileName);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function tracking_delete_files_if_unreferenced(mysqli $conn, ?string $fileNames): void
{
    foreach (tracking_split_file_names($fileNames) as $fileName) {
        $stillReferenced = false;
        $like = '%' . $fileName . '%';

        foreach (['incoming', 'outgoing'] as $tableName) {
            $stmt = $conn->prepare("SELECT file_name FROM `{$tableName}` WHERE file_name LIKE ? LIMIT 25");
            if (!$stmt) {
                continue;
            }

            $stmt->bind_param('s', $like);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
                if (in_array($fileName, tracking_split_file_names($row['file_name'] ?? ''), true)) {
                    $stillReferenced = true;
                    break;
                }
            }

            $stmt->close();

            if ($stillReferenced) {
                break;
            }
        }

        if (!$stillReferenced) {
            $path = tracking_upload_dir() . basename($fileName);
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}

function tracking_cleanup_saved_paths(array $paths): void
{
    foreach ($paths as $path) {
        if (is_string($path) && $path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
}
