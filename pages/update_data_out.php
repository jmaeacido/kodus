<?php
header('Content-Type: application/json'); 
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }

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

    // File variables
    $uploadDir = "uploads/";
    $fileName = null;
    $fileType = null;
    $fileSize = null;
    $uploadTime = date("Y-m-d H:i:s");

    // Handle file removal flag
    $removeFile = isset($_POST['remove_file']) && $_POST['remove_file'] == "1";

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

        $fileType = $_FILES["file"]["type"];
        $fileSize = $_FILES["file"]["size"];

        // Validate file type
        $allowedExtensions = ["pdf", "doc", "docx", "jpg", "png", "xlsx", "xlsm"];
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception("Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG, XLSX, XLSM.");
        }

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // 🔹 Delete old file if exists
        $checkStmt = $conn->prepare("SELECT file_name FROM outgoing WHERE id = ?");
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $checkStmt->bind_result($existingFile);
        if ($checkStmt->fetch() && $existingFile) {
            $oldFilePath = $uploadDir . $existingFile;
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
        }
        $checkStmt->close();

        // Move new file
        if (!move_uploaded_file($fileTmpPath, $uploadDir . $fileName)) {
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
        $checkStmt = $conn->prepare("SELECT file_name FROM outgoing WHERE id = ?");
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $checkStmt->bind_result($existingFile);
        if ($checkStmt->fetch() && $existingFile) {
            $filePath = $uploadDir . $existingFile;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        $checkStmt->close();

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
        echo json_encode(['success' => true, 'message' => 'Document updated successfully.']);
    } else {
        throw new Exception("Database error: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
