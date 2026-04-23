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
    $id = $_POST['id'] ?? null;
    $date_received = $_POST['date_received'] ?? null;
    $description = $_POST['description'] ?? null;
    $remarks = $_POST['remarks'] ?? null;
    $focal = !empty($_POST['focal']) ? $_POST['focal'] : null;
    $removeFile = isset($_POST['remove_file']) ? (int)$_POST['remove_file'] : 0;

    if (!$id) {
        throw new Exception("Invalid request. Missing document ID.");
    }

    $existingStmt = $conn->prepare("SELECT id, date_received, description, focal, remarks, file_name, file_size, file_type, upload_time FROM incoming WHERE id = ?");
    $existingStmt->bind_param("i", $id);
    $existingStmt->execute();
    $existingRecord = db_stmt_fetch_one_assoc($existingStmt);
    $existingStmt->close();

    if (!$existingRecord) {
        throw new Exception("Document not found.");
    }

    $uploadDir = "uploads/";
    $fileName = null;
    $fileType = null;
    $fileSize = null;
    $uploadTime = null;
    $fileAction = "keep";

    $existingFileName = (string) ($existingRecord['file_name'] ?? '');
    $existingFilePath = $existingFileName !== '' ? $uploadDir . $existingFileName : null;
    $newFilePath = null;

    if ($removeFile === 1) {
        $fileAction = "remove";
    } elseif (!empty($_FILES["file"]["name"])) {
        $fileTmpPath = $_FILES["file"]["tmp_name"];
        $originalFileName = basename($_FILES["file"]["name"]);
        $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        $baseName = pathinfo($originalFileName, PATHINFO_FILENAME);

        // 🔹 Sanitize base name
        $sanitizedBaseName = preg_replace("/[^A-Za-z0-9_\-]/", "_", $baseName);

        // 🔹 Enforce max length
        $maxBaseLength = 80;
        if (strlen($sanitizedBaseName) > $maxBaseLength) {
            $sanitizedBaseName = substr($sanitizedBaseName, 0, $maxBaseLength);
        }

        // 🔹 Add timestamp
        $timestamp = date("Ymd_His");
        $fileName = strtolower($sanitizedBaseName . "_" . $timestamp . "." . $fileExtension);

        $fileType = security_detect_upload_mime($fileTmpPath);
        $fileSize = $_FILES["file"]["size"];
        $uploadTime = date("Y-m-d H:i:s");

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

        $newFilePath = $uploadDir . $fileName;

        if (!move_uploaded_file($fileTmpPath, $newFilePath)) {
            throw new Exception("File upload failed.");
        }

        $fileAction = "upload";
    }

    $sql = "UPDATE incoming SET 
            date_received = ?, 
            description = ?, 
            focal = ?, 
            remarks = ?";
    $params = [$date_received, $description, $focal, $remarks];
    $types = "ssss";

    if ($fileAction === "remove") {
        $sql .= ", file_name = NULL, file_size = NULL, file_type = NULL, upload_time = NULL";
    } elseif ($fileAction === "upload") {
        $sql .= ", file_name = ?, file_size = ?, file_type = ?, upload_time = ?";
        $params = array_merge($params, [$fileName, $fileSize, $fileType, $uploadTime]);
        $types .= "siss";
    }

    $sql .= " WHERE id = ?";
    $params[] = $id;
    $types .= "i";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $updatedFileName = $fileAction === "remove"
            ? null
            : ($fileAction === "upload" ? $fileName : ($existingRecord['file_name'] ?? null));
        $updatedFileSize = $fileAction === "remove"
            ? null
            : ($fileAction === "upload" ? $fileSize : ($existingRecord['file_size'] ?? null));
        $updatedFileType = $fileAction === "remove"
            ? null
            : ($fileAction === "upload" ? $fileType : ($existingRecord['file_type'] ?? null));
        $updatedUploadTime = $fileAction === "remove"
            ? null
            : ($fileAction === "upload" ? $uploadTime : ($existingRecord['upload_time'] ?? null));

        $changes = audit_collect_field_changes(
            [
                'date_received' => $existingRecord['date_received'] ?? null,
                'description' => $existingRecord['description'] ?? null,
                'focal' => $existingRecord['focal'] ?? null,
                'remarks' => $existingRecord['remarks'] ?? null,
                'file_name' => $existingRecord['file_name'] ?? null,
                'file_size' => $existingRecord['file_size'] ?? null,
                'file_type' => $existingRecord['file_type'] ?? null,
                'upload_time' => $existingRecord['upload_time'] ?? null,
            ],
            [
                'date_received' => $date_received,
                'description' => $description,
                'focal' => $focal,
                'remarks' => $remarks,
                'file_name' => $updatedFileName,
                'file_size' => $updatedFileSize,
                'file_type' => $updatedFileType,
                'upload_time' => $updatedUploadTime,
            ]
        );

        audit_log(
            $conn,
            (int) ($_SESSION['user_id'] ?? 0),
            'Update Incoming Document',
            'Updated incoming document ID ' . (int) $id . ' | Changes: ' . audit_format_field_changes($changes)
        );
        app_notification_create($conn, [
            'category' => 'incoming',
            'title' => 'Incoming document updated',
            'message' => app_notification_actor_name_from_session() . ' updated incoming document ID ' . (int) $id . '.',
            'url' => app_notification_build_url('pages/data-tracking-in'),
            'icon_class' => 'fas fa-edit',
            'color_class' => 'text-info',
            'actor_user_id' => (int) ($_SESSION['user_id'] ?? 0),
            'actor_name' => app_notification_actor_name_from_session(),
        ]);

        kodus_socket_broadcast('kodus.incoming', 'incoming.changed', [
            'action' => 'updated',
            'incoming_id' => (int) $id,
            'actor_id' => (int) ($_SESSION['user_id'] ?? 0),
        ]);

        if ($fileAction === "remove" && $existingFilePath && file_exists($existingFilePath)) {
            @unlink($existingFilePath);
        } elseif ($fileAction === "upload" && $existingFilePath && file_exists($existingFilePath)) {
            @unlink($existingFilePath);
        }

        echo json_encode(['success' => true, 'message' => 'Document updated successfully.']);
    } else {
        if ($fileAction === "upload" && $newFilePath && file_exists($newFilePath)) {
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
?>
