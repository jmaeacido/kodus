<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
security_configure_runtime_for_web();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../socket_helpers.php';
require_once __DIR__ . '/../app_notification_helpers.php';

header('Content-Type: application/json');

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Access denied."]);
    exit;
}

$batchIdRaw = trim((string) ($_POST['batchId'] ?? ''));
if ($batchIdRaw === '') {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Batch ID is required."]);
    exit;
}

$batchIdDigits = preg_replace('/\D+/', '', $batchIdRaw);
if ($batchIdDigits === '') {
    security_send_json(["success" => false, "error" => "Batch ID must be numeric."], 400);
}

$batchId = (int) $batchIdDigits;

$stmt = $conn->prepare("DELETE FROM meb WHERE batch_id = ?");
$stmt->bind_param('i', $batchId);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    app_notification_create($conn, [
        'category' => 'meb',
        'title' => 'MEB batch deleted',
        'message' => app_notification_actor_name_from_session() . " deleted MEB batch {$batchId}.",
        'url' => app_notification_build_url('pages/data-tracking-meb'),
        'icon_class' => 'fas fa-trash',
        'color_class' => 'text-danger',
        'actor_user_id' => (int) ($_SESSION['user_id'] ?? 0),
        'actor_name' => app_notification_actor_name_from_session(),
    ]);
    kodus_socket_broadcast('kodus.meb', 'meb.changed', [
        'action' => 'batch_deleted',
        'batch_id' => $batchId,
        'actor_id' => (int) ($_SESSION['user_id'] ?? 0),
    ]);
    kodus_socket_broadcast('kodus.meb', 'meb.validation.changed', [
        'action' => 'batch_deleted',
        'batch_id' => $batchId,
        'actor_id' => (int) ($_SESSION['user_id'] ?? 0),
    ]);
    $stmt->close();
    security_send_json(["success" => true], 200);
} else {
    $stmt->close();
    security_send_json(["success" => false, "error" => "The selected batch was not found or has already been deleted."], 400);
}
