<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../socket_helpers.php';
require_once __DIR__ . '/../app_notification_helpers.php';
require_once __DIR__ . '/document_upload_helpers.php';
require_once __DIR__ . '/tracking_recipient_helpers.php';
$user_log = $_SESSION['username'] ?? 'Unknown';

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
tracking_reject_oversized_post();
security_require_csrf_token();
tracking_ensure_file_columns($conn, 'outgoing');

$response = ["success" => false, "message" => ""];

try {
    if (empty($_POST["date_out"]) || empty($_POST["description"])) {
        throw new Exception("Date and description are required.");
    }

    $date_out = $_POST["date_out"];
    $description = trim($_POST["description"]);
    $remarks = $_POST["remarks"] ?? "";
    $recipientData = tracking_normalize_recipient_inputs($_POST);
    $receiving_office = $recipientData['display'];
    $date_forwarded = !empty($_POST['date_forwarded']) ? $_POST['date_forwarded'] : null; 

    $uploadedFiles = tracking_save_uploaded_files('file');
    $fileName = $uploadedFiles['file_name'];
    $fileType = $uploadedFiles['file_type'];
    $fileSize = $uploadedFiles['file_size'];
    $uploadTime = date("Y-m-d H:i:s");

    if (!$fileName) {
        // ✅ No file uploaded → check if incoming has a file with the same description
        $stmtIncoming = $conn->prepare("SELECT file_name, file_type, file_size FROM incoming WHERE description = ? LIMIT 1");
        $stmtIncoming->bind_param("s", $description);
        $stmtIncoming->execute();
        $stmtIncoming->bind_result($incomingFile, $incomingFileType, $incomingFileSize);

        if ($stmtIncoming->fetch()) {
            $fileName = $incomingFile;
            $fileType = $incomingFileType;
            $fileSize = $incomingFileSize;
        }
        $stmtIncoming->close();
    }

    // Insert with temp tracking number
    $tempTrackingNumber = "TEMP";

    $stmt = $conn->prepare("INSERT INTO outgoing 
        (date_out, description, file_name, file_type, file_size, upload_time, remarks, receiving_office, date_forwarded, tracking_number, user_log) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    $stmt->bind_param("sssssssssss",
        $date_out,
        $description,
        $fileName,
        $fileType,
        $fileSize,
        $uploadTime,
        $remarks,
        $receiving_office,
        $date_forwarded,
        $tempTrackingNumber,
        $user_log
    );

    if (!$stmt->execute()) {
        tracking_cleanup_saved_paths($uploadedFiles['paths']);
        throw new Exception("Database error: " . $stmt->error);
    }

    // Get ID and build tracking number
    $insertedId = (int) $conn->insert_id;
    $lastInsertId = "000" . $insertedId;
    $tracking_number = date("m-d-y") . "-" . $lastInsertId . "-O";

    $updateStmt = $conn->prepare("UPDATE outgoing SET tracking_number = ? WHERE id = ?");
    if (!$updateStmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    $updateStmt->bind_param("si", $tracking_number, $insertedId);

    if (!$updateStmt->execute()) {
        tracking_cleanup_saved_paths($uploadedFiles['paths']);
        throw new Exception("Database update error: " . $updateStmt->error);
    }

    $response["success"] = true;
    $response["tracking_number"] = $tracking_number;
    $response["message"] = "Document tracked successfully.";
    app_notification_create($conn, [
        'category' => 'outgoing',
        'title' => 'Outgoing document added',
        'message' => app_notification_actor_name_from_session() . " tracked outgoing document {$tracking_number}.",
        'url' => app_notification_build_url('pages/data-tracking-out'),
        'icon_class' => 'fas fa-file-upload',
        'color_class' => 'text-primary',
        'actor_user_id' => (int) ($_SESSION['user_id'] ?? 0),
        'actor_name' => app_notification_actor_name_from_session(),
    ]);

    kodus_socket_broadcast('kodus.outgoing', 'outgoing.changed', [
        'action' => 'created',
        'outgoing_id' => $insertedId,
        'tracking_number' => $tracking_number,
        'actor_id' => (int) ($_SESSION['user_id'] ?? 0),
    ]);

    $mailResult = tracking_send_document_recipient_emails($conn, $recipientData['emails'], [
        'context' => 'Outgoing document',
        'tracking_number' => $tracking_number,
        'description' => $description,
        'remarks' => $remarks,
        'receiving_office' => $receiving_office,
        'date_forwarded' => $date_forwarded ?: $date_out,
        'url' => app_notification_build_url('pages/data-tracking-out'),
    ]);
    $response['mail_sent'] = $mailResult['sent'];
    $response['mail_failed'] = $mailResult['failed'];
    $kodusAlertResult = tracking_send_document_recipient_kodus_alerts($conn, $recipientData['emails'], [
        'context' => 'Outgoing document',
        'tracking_number' => $tracking_number,
        'description' => $description,
        'remarks' => $remarks,
        'receiving_office' => $receiving_office,
        'date_forwarded' => $date_forwarded ?: $date_out,
        'url' => app_notification_build_url('pages/data-tracking-out'),
    ]);
    $response['notifications_sent'] = $kodusAlertResult['notifications'];
    $response['messenger_sent'] = $kodusAlertResult['messages'];

} catch (Exception $e) {
    if (isset($uploadedFiles) && is_array($uploadedFiles)) {
        tracking_cleanup_saved_paths($uploadedFiles['paths'] ?? []);
    }
    $response["message"] = $e->getMessage();
}

echo json_encode($response);
?>
