<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
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
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

$idsInput = trim((string) ($_POST['ids'] ?? ''));
$status = (string) ($_POST['status'] ?? '');

if ($idsInput === '' || !in_array($status, ['validated', 'not_validated'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$ids = array_values(array_filter(array_map('intval', explode(',', $idsInput))));
if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No valid rows selected.']);
    exit;
}

$validationValue = $status === 'validated' ? '✓' : '';
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));

$beforeSql = "SELECT id, validation FROM meb WHERE id IN ($placeholders)";
$beforeStmt = $conn->prepare($beforeSql);
$beforeStmt->bind_param($types, ...$ids);
$beforeStmt->execute();
$beforeRows = db_stmt_fetch_all_assoc($beforeStmt);
$beforeStmt->close();

$sql = "UPDATE meb SET validation = ? WHERE id IN ($placeholders)";
$stmt = $conn->prepare($sql);
$bindTypes = 's' . $types;
$bindParams = array_merge([$validationValue], $ids);
$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$action = 'Update MEB Validation';
$changeParts = [];
foreach ($beforeRows as $row) {
    $changes = audit_collect_field_changes(
        ['validation' => $row['validation'] ?? null],
        ['validation' => $validationValue]
    );
    $changeParts[] = 'Row ID ' . (int) ($row['id'] ?? 0) . ' [' . audit_format_field_changes($changes) . ']';
}

$details = sprintf(
    'Updated MEB validation for %d row(s). Target status: %s. %s',
    count($ids),
    $status === 'validated' ? 'validated' : 'not validated',
    implode(' | ', $changeParts)
);
audit_log($conn, $userId, $action, $details, $ipAddress);
app_notification_create($conn, [
    'category' => 'meb-validation',
    'title' => $status === 'validated' ? 'MEB rows validated' : 'MEB rows marked not validated',
    'message' => app_notification_actor_name_from_session() . ' updated validation for ' . count($ids) . ' MEB row(s).',
    'url' => app_notification_build_url('pages/data-tracking-meb-validation'),
    'icon_class' => 'fas fa-check-circle',
    'color_class' => $status === 'validated' ? 'text-success' : 'text-warning',
    'actor_user_id' => $userId,
    'actor_name' => app_notification_actor_name_from_session(),
]);

kodus_socket_broadcast('kodus.meb', 'meb.validation.changed', [
    'action' => 'validation_updated',
    'ids' => $ids,
    'status' => $status,
    'actor_id' => $userId,
]);

echo json_encode([
    'success' => true,
    'message' => $status === 'validated' ? 'Rows marked as validated.' : 'Rows marked as not validated.',
    'affected_rows' => $affected,
]);
