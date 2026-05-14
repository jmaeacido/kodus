<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../socket_helpers.php';
require_once __DIR__ . '/../app_notification_helpers.php';
require_once __DIR__ . '/document_upload_helpers.php';
require_once __DIR__ . '/tracking_recipient_helpers.php';
header('Content-Type: application/json'); 

require_once __DIR__ . '/../config.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
tracking_reject_oversized_post();
security_require_csrf_token();
tracking_ensure_file_columns($conn, 'outgoing');

$uploadedFiles = ['paths' => []];

try {
    // Get form data
    $id = $_POST['id'] ?? null;
    $date_out = $_POST['date_out'] ?? null;
    $description = $_POST['description'] ?? null;
    $remarks = $_POST['remarks'] ?? null;
    $recipientData = tracking_normalize_recipient_inputs($_POST);
    $receiving_office = $recipientData['display'] !== '' ? $recipientData['display'] : ($_POST['receiving_office'] ?? null);
    $date_forwarded = !empty($_POST['date_forwarded']) ? $_POST['date_forwarded'] : null; 
    $submittedKeepExistingFiles = isset($_POST['keep_existing_files_submitted']);

    if (!$id) {
        throw new Exception("Invalid request. Missing document ID.");
    }

    $existingStmt = $conn->prepare("SELECT id, date_out, tracking_number, description, remarks, receiving_office, date_forwarded, file_name, file_size, file_type, upload_time FROM outgoing WHERE id = ?");
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
    $uploadTime = date("Y-m-d H:i:s");

    $existingFileName = (string) ($existingRecord['file_name'] ?? '');
    $keepExistingFiles = $submittedKeepExistingFiles
        ? ($_POST['keep_existing_files'] ?? [])
        : tracking_split_file_names($existingFileName);
    $existingFilePayload = tracking_filter_existing_file_payload($existingRecord, $keepExistingFiles);
    $removedExistingFileName = $existingFilePayload['removed_file_name'];
    $fileAction = "keep";
    if (tracking_has_uploaded_files('file')) {
        $uploadedFiles = tracking_save_uploaded_files('file');
        $mergedFiles = tracking_merge_file_payloads($existingFilePayload, $uploadedFiles);
        $fileName = $mergedFiles['file_name'];
        $fileType = $mergedFiles['file_type'];
        $fileSize = $mergedFiles['file_size'];
        $fileAction = "upload";
    } elseif ($existingFilePayload['changed']) {
        $fileName = $existingFilePayload['file_name'];
        $fileType = $existingFilePayload['file_type'];
        $fileSize = $existingFilePayload['file_size'];
        $uploadTime = $fileName ? ($existingRecord['upload_time'] ?? null) : null;
        $fileAction = $fileName ? "upload" : "remove";
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

    if ($fileAction === "upload") {
        // Update with new file
        $sql .= ", file_name = ?, file_size = ?, file_type = ?, upload_time = ?";
        $params = array_merge($params, [$fileName, $fileSize, $fileType, $uploadTime]);
        $types .= "ssss";
    } elseif ($fileAction === "remove") {
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

        if ($fileAction === "remove" || $fileAction === "upload") {
            tracking_delete_files_if_unreferenced($conn, $removedExistingFileName ?: $existingFileName);
        }

        $response = [
            'success' => true,
            'message' => 'Document updated successfully.',
        ];
        if ($recipientData['emails'] !== [] && $changes !== []) {
            tracking_finish_json_response_then_send_document_recipient_notices($conn, $response, $recipientData['emails'], [
                'context' => 'Updated outgoing document',
                'tracking_number' => (string) ($existingRecord['tracking_number'] ?? ''),
                'description' => (string) $description,
                'remarks' => (string) $remarks,
                'receiving_office' => (string) $receiving_office,
                'date_forwarded' => (string) ($date_forwarded ?: $date_out),
                'url' => app_notification_build_url('pages/data-tracking-out'),
            ]);
            exit;
        }

        echo json_encode($response + ['mail_queued' => false]);
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
