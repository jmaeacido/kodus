<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../socket_helpers.php';
require_once __DIR__ . '/../app_notification_helpers.php';
header('Content-Type: application/json'); 

require_once __DIR__ . '/../config.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();

try {
    // Get form data
    $id = $_POST['id'] ?? null;
    $date_out = $_POST['date_out'] ?? null;
    $description = $_POST['description'] ?? null;
    $remarks = $_POST['remarks'] ?? null;
    $receiving_office = $_POST['receiving_office'] ?? null;
    $date_forwarded = !empty($_POST['date_forwarded']) ? $_POST['date_forwarded'] : null; 

    if (!$id) {
        throw new Exception("Invalid request. Missing document ID.");
    }

    $existingStmt = $conn->prepare("SELECT id, date_out, description, remarks, receiving_office, date_forwarded, file_name, file_size, file_type, upload_time FROM outgoing WHERE id = ?");
    $existingStmt->bind_param("i", $id);
    $existingStmt->execute();
    $existingRecord = db_stmt_fetch_one_assoc($existingStmt);
    $existingStmt->close();

    if (!$existingRecord) {
        throw new Exception("Document not found.");
    }

    // File variables
    $uploadDir = "uploads/";
    $fileName = null;
    $fileType = null;
    $fileSize = null;
    $uploadTime = date("Y-m-d H:i:s");

    // Handle file removal flag
    $removeFile = isset($_POST['remove_file']) && $_POST['remove_file'] == "1";
    $existingFileName = (string) ($existingRecord['file_name'] ?? '');
    $existingFilePath = $existingFileName !== '' ? $uploadDir . $existingFileName : null;
    $newFilePath = null;

    if (!empty($_FILES["file"]["name"])) {
        // Case: Replace with new file
        $fileTmpPath = $_FILES["file"]["tmp_name"];
        $originalFileName = basename($_FILES["file"]["name"]);
        $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        $baseName = pathinfo($originalFileName, PATHINFO_FILENAME); // filename without extension

        // 🔹 Sanitize filename (only keep alphanumeric, dash, underscore)
        $sanitizedBaseName = preg_replace("/[^A-Za-z0-9_\-]/", "_", $baseName);

        // 🔹 Enforce max length (reserve ~20 chars for timestamp + extension)
        $maxBaseLength = 80; 
        if (strlen($sanitizedBaseName) > $maxBaseLength) {
            $sanitizedBaseName = substr($sanitizedBaseName, 0, $maxBaseLength);
        }

        // 🔹 Add timestamp
        $timestamp = date("Ymd_His");
        $fileName = strtolower($sanitizedBaseName . "_" . $timestamp . "." . $fileExtension);

        $fileType = security_detect_upload_mime($fileTmpPath);
        $fileSize = $_FILES["file"]["size"];

        // Validate file type
        $allowedTypes = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'xlsm' => ['application/vnd.ms-excel.sheet.macroEnabled.12'],
        ];
        if (!isset($allowedTypes[$fileExtension]) || !in_array($fileType, $allowedTypes[$fileExtension], true)) {
            throw new Exception("Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG, XLSX, XLSM.");
        }

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // 🔹 Delete old file if exists
        // Move new file
        $newFilePath = $uploadDir . $fileName;
        if (!move_uploaded_file($fileTmpPath, $newFilePath)) {
            throw new Exception("File upload failed.");
        }
    }

    // Build SQL
    $sql = "UPDATE outgoing SET 
            date_out = ?, 
            description = ?,
            remarks = ?,
            receiving_office = ?, 
            date_forwarded = ?";
    $params = [$date_out, $description, $remarks, $receiving_office, $date_forwarded];
    $types = "sssss";

    if ($fileName) {
        // Update with new file
        $sql .= ", file_name = ?, file_size = ?, file_type = ?, upload_time = ?";
        $params = array_merge($params, [$fileName, $fileSize, $fileType, $uploadTime]);
        $types .= "siss";
    } elseif ($removeFile) {
        // Delete physical file if exists
        // Set DB file fields to NULL
        $sql .= ", file_name = NULL, file_size = NULL, file_type = NULL, upload_time = NULL";
    }
    // Else: keep existing file as is

    $sql .= " WHERE id = ?";
    $params[] = $id;
    $types .= "i";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }

    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $updatedFileName = $removeFile
            ? null
            : ($fileName ?: ($existingRecord['file_name'] ?? null));
        $updatedFileSize = $removeFile
            ? null
            : ($fileName ? $fileSize : ($existingRecord['file_size'] ?? null));
        $updatedFileType = $removeFile
            ? null
            : ($fileName ? $fileType : ($existingRecord['file_type'] ?? null));
        $updatedUploadTime = $removeFile
            ? null
            : ($fileName ? $uploadTime : ($existingRecord['upload_time'] ?? null));

        $changes = audit_collect_field_changes(
            [
                'date_out' => $existingRecord['date_out'] ?? null,
                'description' => $existingRecord['description'] ?? null,
                'remarks' => $existingRecord['remarks'] ?? null,
                'receiving_office' => $existingRecord['receiving_office'] ?? null,
                'date_forwarded' => $existingRecord['date_forwarded'] ?? null,
                'file_name' => $existingRecord['file_name'] ?? null,
                'file_size' => $existingRecord['file_size'] ?? null,
                'file_type' => $existingRecord['file_type'] ?? null,
                'upload_time' => $existingRecord['upload_time'] ?? null,
            ],
            [
                'date_out' => $date_out,
                'description' => $description,
                'remarks' => $remarks,
                'receiving_office' => $receiving_office,
                'date_forwarded' => $date_forwarded,
                'file_name' => $updatedFileName,
                'file_size' => $updatedFileSize,
                'file_type' => $updatedFileType,
                'upload_time' => $updatedUploadTime,
            ]
        );

        audit_log(
            $conn,
            (int) ($_SESSION['user_id'] ?? 0),
            'Update Outgoing Document',
            'Updated outgoing document ID ' . (int) $id . ' | Changes: ' . audit_format_field_changes($changes)
        );
        app_notification_create($conn, [
            'category' => 'outgoing',
            'title' => 'Outgoing document updated',
            'message' => app_notification_actor_name_from_session() . ' updated outgoing document ID ' . (int) $id . '.',
            'url' => app_notification_build_url('pages/data-tracking-out'),
            'icon_class' => 'fas fa-edit',
            'color_class' => 'text-info',
            'actor_user_id' => (int) ($_SESSION['user_id'] ?? 0),
            'actor_name' => app_notification_actor_name_from_session(),
        ]);

        kodus_socket_broadcast('kodus.outgoing', 'outgoing.changed', [
            'action' => 'updated',
            'outgoing_id' => (int) $id,
            'actor_id' => (int) ($_SESSION['user_id'] ?? 0),
        ]);

        if (($removeFile || $fileName) && $existingFilePath && file_exists($existingFilePath)) {
            @unlink($existingFilePath);
        }

        echo json_encode(['success' => true, 'message' => 'Document updated successfully.']);
    } else {
        if ($fileName && $newFilePath && file_exists($newFilePath)) {
            @unlink($newFilePath);
        }
        throw new Exception("Database error: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    if (isset($newFilePath) && is_string($newFilePath) && $newFilePath !== '' && file_exists($newFilePath)) {
        @unlink($newFilePath);
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
