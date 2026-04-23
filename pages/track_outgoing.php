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
    if (empty($_POST["date_out"]) || empty($_POST["description"])) {
        throw new Exception("Date and description are required.");
    }

    $date_out = $_POST["date_out"];
    $description = trim($_POST["description"]);
    $remarks = $_POST["remarks"] ?? "";
    $receiving_office = $_POST['receiving_office'];
    $date_forwarded = !empty($_POST['date_forwarded']) ? $_POST['date_forwarded'] : null; 

    // File variables
    $uploadDir = "uploads/";
    $fileName = null;
    $fileType = null;
    $fileSize = null;
    $uploadTime = date("Y-m-d H:i:s");

    if (!empty($_FILES["file"]["name"])) {
        // ✅ User uploaded a file
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
    } else {
        // ✅ No file uploaded → check if incoming has a file with the same description
        $stmtIncoming = $conn->prepare("SELECT file_name FROM incoming WHERE description = ? LIMIT 1");
        $stmtIncoming->bind_param("s", $description);
        $stmtIncoming->execute();
        $stmtIncoming->bind_result($incomingFile);

        if ($stmtIncoming->fetch()) {
            $fileName = $incomingFile;
            $filePath = $uploadDir . $fileName;
            if (file_exists($filePath)) {
                $fileType = mime_content_type($filePath) ?: "application/octet-stream";
                $fileSize = filesize($filePath);
            }
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
    $stmt->bind_param("ssssissssss",
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

} catch (Exception $e) {
    $response["message"] = $e->getMessage();
}

echo json_encode($response);
?>
