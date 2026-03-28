<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }

    $id = $_POST['id'] ?? null;
    $date_received = $_POST['date_received'] ?? null;
    $description = $_POST['description'] ?? null;
    $remarks = $_POST['remarks'] ?? null;
    $focal = !empty($_POST['focal']) ? $_POST['focal'] : null;
    $removeFile = isset($_POST['remove_file']) ? (int)$_POST['remove_file'] : 0;

    if (!$id) {
        throw new Exception("Invalid request. Missing document ID.");
    }

    $uploadDir = "uploads/";
    $fileName = null;
    $fileType = null;
    $fileSize = null;
    $uploadTime = null;
    $fileAction = "keep";

    if ($removeFile === 1) {
        $res = $conn->query("SELECT file_name FROM incoming WHERE id=" . (int)$id);
        if ($res && $row = $res->fetch_assoc()) {
            $currentFile = $row['file_name'];
            if ($currentFile && file_exists($uploadDir . $currentFile)) {
                unlink($uploadDir . $currentFile);
            }
        }
        $fileAction = "remove";
    }
    elseif (!empty($_FILES["file"]["name"])) {
        $res = $conn->query("SELECT file_name FROM incoming WHERE id=" . (int)$id);
        if ($res && $row = $res->fetch_assoc()) {
            $currentFile = $row['file_name'];
            if ($currentFile && file_exists($uploadDir . $currentFile)) {
                unlink($uploadDir . $currentFile);
            }
        }

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
        $uploadTime = date("Y-m-d H:i:s");

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
        echo json_encode(['success' => true, 'message' => 'Document updated successfully.']);
    } else {
        throw new Exception("Database error: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>