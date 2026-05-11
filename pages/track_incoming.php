<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../socket_helpers.php';
require_once __DIR__ . '/../app_notification_helpers.php';
require_once __DIR__ . '/document_upload_helpers.php';

$user_log = $_SESSION['username'] ?? 'Unknown';

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
tracking_reject_oversized_post();
security_require_csrf_token();
tracking_ensure_file_columns($conn, 'incoming');

$response = ["success" => false, "message" => ""];

try {
    if (empty($_POST["date_received"]) || empty($_POST["description"])) {
        throw new Exception("Date and description are required.");
    }

    $date_received = $_POST["date_received"];
    $description = $_POST["description"];
    $remarks = $_POST["remarks"] ?? "";

    $uploadedFiles = tracking_save_uploaded_files('file');
    $fileName = $uploadedFiles['file_name'];
    $fileType = $uploadedFiles['file_type'];
    $fileSize = $uploadedFiles['file_size'];
    $uploadTime = date("Y-m-d H:i:s");

    $tempTrackingNumber = "TEMP";

    $stmt = $conn->prepare("INSERT INTO incoming 
        (date_received, description, file_name, file_type, file_size, upload_time, remarks, tracking_number, user_log) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    $stmt->bind_param("sssssssss", $date_received, $description, $fileName, $fileType, $fileSize, $uploadTime, $remarks, $tempTrackingNumber, $user_log);

    if (!$stmt->execute()) {
        tracking_cleanup_saved_paths($uploadedFiles['paths']);
        throw new Exception("Database error: " . $stmt->error);
    }

    $insertedId = (int) $conn->insert_id;
    $lastInsertId = "000" . $insertedId;
    $tracking_number = date("m-d-y") . "-" . $lastInsertId . "-I";

    $updateStmt = $conn->prepare("UPDATE incoming SET tracking_number = ? WHERE id = ?");
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
        'category' => 'incoming',
        'title' => 'Incoming document added',
        'message' => app_notification_actor_name_from_session() . " tracked incoming document {$tracking_number}.",
        'url' => app_notification_build_url('pages/data-tracking-in'),
        'icon_class' => 'fas fa-file-download',
        'color_class' => 'text-primary',
        'actor_user_id' => (int) ($_SESSION['user_id'] ?? 0),
        'actor_name' => app_notification_actor_name_from_session(),
    ]);

    kodus_socket_broadcast('kodus.incoming', 'incoming.changed', [
        'action' => 'created',
        'incoming_id' => $insertedId,
        'tracking_number' => $tracking_number,
        'actor_id' => (int) ($_SESSION['user_id'] ?? 0),
    ]);
} catch (Exception $e) {
    if (isset($uploadedFiles) && is_array($uploadedFiles)) {
        tracking_cleanup_saved_paths($uploadedFiles['paths'] ?? []);
    }
    $response["message"] = $e->getMessage();
}

echo json_encode($response);
?>
