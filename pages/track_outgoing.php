<?php
session_start();
$user_log = $_SESSION['username'];

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$response = ["success" => false, "message" => ""];

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Invalid request method.");
    }

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

        $fileType = $_FILES["file"]["type"];
        $fileSize = $_FILES["file"]["size"];

        $allowedExtensions = ["pdf", "doc", "docx", "jpg", "png", "xlsx", "xlsm"];
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception("Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG, XLSX, XLSM.");
        }

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
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
    $lastInsertId = "000" . $conn->insert_id;
    $tracking_number = date("m-d-y") . "-" . $lastInsertId . "-O";

    $updateStmt = $conn->prepare("UPDATE outgoing SET tracking_number = ? WHERE id = ?");
    if (!$updateStmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    $updateStmt->bind_param("si", $tracking_number, $lastInsertId);

    if (!$updateStmt->execute()) {
        throw new Exception("Database update error: " . $updateStmt->error);
    }

    $response["success"] = true;
    $response["tracking_number"] = $tracking_number;
    $response["message"] = "Document tracked successfully.";

} catch (Exception $e) {
    $response["message"] = $e->getMessage();
}

echo json_encode($response);
?>