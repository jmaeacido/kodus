<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../socket_helpers.php';
require_once __DIR__ . '/../app_notification_helpers.php';
require_once __DIR__ . '/document_upload_helpers.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
tracking_reject_oversized_post();
security_require_csrf_token();
tracking_ensure_file_columns($conn, 'incoming');

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

    $fileName = null;
    $fileType = null;
    $fileSize = null;
    $uploadTime = null;
    $fileAction = "keep";

    $existingFileName = (string) ($existingRecord['file_name'] ?? '');
    $newFilePath = null;
    $uploadedFiles = ['paths' => []];

    if ($removeFile === 1) {
        $fileAction = "remove";
    } elseif (tracking_has_uploaded_files('file')) {
        $uploadedFiles = tracking_save_uploaded_files('file');
        $fileName = $uploadedFiles['file_name'];
        $fileType = $uploadedFiles['file_type'];
        $fileSize = $uploadedFiles['file_size'];
        $uploadTime = date("Y-m-d H:i:s");
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
        $types .= "ssss";
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

        if ($fileAction === "remove" || $fileAction === "upload") {
            tracking_delete_files_if_unreferenced($conn, $existingFileName);
        }

        echo json_encode(['success' => true, 'message' => 'Document updated successfully.']);
    } else {
        if ($fileAction === "upload") {
            tracking_cleanup_saved_paths($uploadedFiles['paths'] ?? []);
        }
        throw new Exception("Database error: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    tracking_cleanup_saved_paths($uploadedFiles['paths'] ?? []);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
