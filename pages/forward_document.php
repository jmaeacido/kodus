<?php
session_start();
include('../config.php'); // Database connection

header('Content-Type: application/json');

// Check user session
if(!isset($_SESSION['user_id'])){
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Required POST fields
$required = ['id','tracking_number','description','receiving_office','date_forwarded'];
foreach($required as $field){
    if(empty($_POST[$field])){
        echo json_encode(['success' => false, 'message' => "Field $field is required"]);
        exit;
    }
}

// Sanitize inputs
$id = intval($_POST['id']);
$tracking_number = $_POST['tracking_number'];
$description = $_POST['description'];
//$remarks = $_POST['remarks'];
$file_name = $_POST['file_name'] ?? '';
$receiving_office = $_POST['receiving_office'];
$date_forwarded = $_POST['date_forwarded'];

// Get user info
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'unknown'; // username to log in outgoing.user_log

// Check if incoming already forwarded
$check = $conn->prepare("SELECT status FROM incoming WHERE id = ? LIMIT 1");
$check->bind_param("i", $id);
$check->execute();
$result = $check->get_result();

if($result->num_rows === 0){
    echo json_encode(['success' => false, 'message' => 'Document not found']);
    exit;
}

$row = $result->fetch_assoc();
if($row['status'] === 'Forwarded'){
    echo json_encode(['success' => false, 'message' => 'Document already forwarded']);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Insert into outgoing including date_out and user_log
    $stmt = $conn->prepare("INSERT INTO outgoing 
        (tracking_number, description, remarks, file_name, receiving_office, date_forwarded, date_out, user_log)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "ssssssss",
        $tracking_number,
        $description,
        $remarks,
        $file_name,
        $receiving_office,
        $date_forwarded,  // date_forwarded
        $date_forwarded,  // date_out = same as date_forwarded
        $username         // user_log
    );
    $stmt->execute();

    // Update incoming status
    $stmt2 = $conn->prepare("UPDATE incoming SET status='Forwarded' WHERE id=?");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();

    // Audit log
    $action = "Forwarded incoming document";
    $details = json_encode([
        'tracking_number' => $tracking_number,
        'receiving_office' => $receiving_office,
        'remarks' => $remarks
    ], JSON_UNESCAPED_UNICODE);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt3 = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt3->bind_param("isss", $user_id, $action, $details, $ip_address);
    $stmt3->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Document forwarded successfully']);

} catch(Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: '.$e->getMessage()]);
}

?>
