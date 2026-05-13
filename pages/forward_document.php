<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../socket_helpers.php';
require_once __DIR__ . '/tracking_recipient_helpers.php';
include('../config.php'); // Database connection

header('Content-Type: application/json');

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();

// Check user session
if(!isset($_SESSION['user_id'])){
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Required POST fields
$required = ['id','tracking_number','description','date_forwarded'];
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
$remarks = $_POST['remarks'] ?? '';
$file_name = $_POST['file_name'] ?? '';
$recipientData = tracking_normalize_recipient_inputs($_POST);
$receiving_office = $recipientData['display'];
$date_forwarded = $_POST['date_forwarded'];
if ($receiving_office === '') {
    echo json_encode(['success' => false, 'message' => 'Field receiving_office is required']);
    exit;
}

// Get user info
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'unknown'; // username to log in outgoing.user_log

// Check if incoming already forwarded
$check = $conn->prepare("SELECT status FROM incoming WHERE id = ? LIMIT 1");
$check->bind_param("i", $id);
$check->execute();
$row = db_stmt_fetch_one_assoc($check);

if(!$row){
    echo json_encode(['success' => false, 'message' => 'Document not found']);
    exit;
}
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
    $outgoingId = (int) $conn->insert_id;

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
    audit_log($conn, (int) $user_id, $action, $details, $ip_address);

    app_notification_create($conn, [
        'category' => 'document_tracking',
        'title' => 'Document forwarded',
        'message' => app_notification_actor_name_from_session() . " forwarded document {$tracking_number}.",
        'url' => app_notification_build_url('pages/data-tracking-out'),
        'icon_class' => 'fas fa-share',
        'color_class' => 'text-success',
        'actor_user_id' => (int) $user_id,
        'actor_name' => app_notification_actor_name_from_session(),
    ]);

    $conn->commit();

    kodus_socket_broadcast('kodus.incoming', 'incoming.changed', [
        'action' => 'forwarded',
        'incoming_id' => $id,
        'tracking_number' => $tracking_number,
        'actor_id' => (int) $user_id,
    ]);
    kodus_socket_broadcast('kodus.outgoing', 'outgoing.changed', [
        'action' => 'created_from_forward',
        'incoming_id' => $id,
        'outgoing_id' => $outgoingId,
        'tracking_number' => $tracking_number,
        'actor_id' => (int) $user_id,
    ]);

    tracking_finish_json_response_then_send_document_recipient_notices($conn, [
        'success' => true,
        'message' => 'Document forwarded successfully',
    ], $recipientData['emails'], [
        'context' => 'Forwarded document',
        'tracking_number' => $tracking_number,
        'description' => $description,
        'remarks' => $remarks,
        'receiving_office' => $receiving_office,
        'date_forwarded' => $date_forwarded,
        'url' => app_notification_build_url('pages/data-tracking-out'),
    ]);
    exit;

} catch(Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: '.$e->getMessage()]);
}

?>
