<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized."]);
    exit;
}

require_once __DIR__ . '/../config.php';

function aatracker_column_exists(mysqli $conn, string $columnName): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'aatracker'
          AND COLUMN_NAME = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $columnName);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result instanceof mysqli_result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

function ensure_aatracker_schema(mysqli $conn): void
{
    if (aatracker_column_exists($conn, 'jmGwapo') && !aatracker_column_exists($conn, 'bFocal')) {
        $conn->query("ALTER TABLE aatracker CHANGE COLUMN jmGwapo bFocal DATE NOT NULL");
    }
}

ensure_aatracker_schema($conn);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validate form data
    $aaDate = $_POST['aaDate'] ?? null;
    $description = $_POST['description'] ?? null;
    $remarks = $_POST['remarks2'] ?? null;
    $upload_time = date("Y-m-d H:i:s");

    if (!$aaDate || !$description) {
        error_log("Missing required fields: aaDate or description");
        echo json_encode(["success" => false, "message" => "Missing required fields."]);
        exit;
    }

    if (!isset($_FILES['file_name']) || $_FILES['file_name']['error'] !== UPLOAD_ERR_OK) {
        error_log("File upload error: " . $_FILES['file_name']['error']);
        echo json_encode(["success" => false, "message" => "File upload error."]);
        exit;
    }

    // Process uploaded file
    $original_name = pathinfo(basename($_FILES['file_name']['name']), PATHINFO_FILENAME);
    $file_ext = strtolower(pathinfo($_FILES['file_name']['name'], PATHINFO_EXTENSION));
    $file_size = $_FILES['file_name']['size'];
    $file_tmp = $_FILES['file_name']['tmp_name'];
    $file_type = security_detect_upload_mime($file_tmp);

    $allowed_types = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'doc' => ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!isset($allowed_types[$file_ext]) || !in_array($file_type, $allowed_types[$file_ext], true)) {
        error_log("Invalid file type: " . $file_type);
        echo json_encode(["success" => false, "message" => "Invalid file type."]);
        exit;
    }

    if ($file_size > $max_size) {
        error_log("File too large: " . $file_size);
        echo json_encode(["success" => false, "message" => "File too large."]);
        exit;
    }

    // Ensure unique filename
    $unique_id = time();
    $safe_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $original_name);
    $new_file_name = $safe_name . "_$unique_id." . $file_ext;

    // Set upload path
    $upload_dir = __DIR__ . '/../storage/aatracker/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_path = $upload_dir . $new_file_name;

    if (!move_uploaded_file($file_tmp, $file_path)) {
        error_log("File move failed for: " . $file_path);
        echo json_encode(["success" => false, "message" => "File upload failed."]);
        exit;
    }

    // Insert into database
    $query = "INSERT INTO aatracker (aaDate, description, file_name, file_size, file_type, upload_time, remarks2) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssss", $aaDate, $description, $new_file_name, $file_size, $file_type, $upload_time, $remarks);

    if ($stmt->execute()) {
        $tracking_id = $stmt->insert_id;
        $date_part = date("m-d-y");
        $tracking_number = "$date_part-$tracking_id";

        // Update tracking number
        $update_query = "UPDATE aatracker SET tracking_number2 = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("si", $tracking_number, $tracking_id);
        $update_stmt->execute();

        echo json_encode(["success" => true, "tracking_number" => $tracking_number]);
    } else {
        error_log("Database error: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "Database error: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
