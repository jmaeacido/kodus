<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../socket_helpers.php';
require_once __DIR__ . '/../app_notification_helpers.php';

$user_log = $_SESSION['username'] ?? 'Unknown';

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();

$response = ["success" => false, "message" => ""];

try {
    if (empty($_POST["date_received"]) || empty($_POST["description"])) {
        throw new Exception("Date and description are required.");
    }

    $date_received = $_POST["date_received"];
    $description = $_POST["description"];
    $remarks = $_POST["remarks"] ?? "";

    $uploadDir = "uploads/";
    $fileName = null;
    $fileType = null;
    $fileSize = null;
    $uploadTime = date("Y-m-d H:i:s");

    if (!empty($_FILES["file"]["name"])) {
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

        if (!move_uploaded_file($fileTmpPath, $uploadDir . $fileName)) {
            throw new Exception("File upload failed.");
        }
    }

    $tempTrackingNumber = "TEMP";

    $stmt = $conn->prepare("INSERT INTO incoming 
        (date_received, description, file_name, file_type, file_size, upload_time, remarks, tracking_number, user_log) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    $stmt->bind_param("ssssissss", $date_received, $description, $fileName, $fileType, $fileSize, $uploadTime, $remarks, $tempTrackingNumber, $user_log);

    if (!$stmt->execute()) {
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
    $response["message"] = $e->getMessage();
}

echo json_encode($response);
?>
