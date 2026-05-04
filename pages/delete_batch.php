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

if (!isset($_SESSION['selected_year'])) {
    security_send_json(["success" => false, "error" => "Fiscal year not selected."], 400);
}

$selectedYear = (int) $_SESSION['selected_year'];
$batchIdsRaw = $_POST['batchIds'] ?? $_POST['batchId'] ?? [];
if (!is_array($batchIdsRaw)) {
    $batchIdsRaw = [$batchIdsRaw];
}

$batchIds = [];
foreach ($batchIdsRaw as $batchIdRaw) {
    $batchIdRaw = trim((string) $batchIdRaw);
    if ($batchIdRaw === '') {
        continue;
    }

    $batchIdDigits = preg_replace('/\D+/', '', $batchIdRaw);
    if ($batchIdDigits === '') {
        security_send_json(["success" => false, "error" => "Batch IDs must be numeric."], 400);
    }

    $batchIds[] = (int) $batchIdDigits;
}

$batchIds = array_values(array_unique(array_filter($batchIds)));
if (empty($batchIds)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Please select at least one batch ID."]);
    exit;
}

$placeholders = implode(',', array_fill(0, count($batchIds), '?'));
$types = str_repeat('i', count($batchIds)) . 'i';
$params = array_merge($batchIds, [$selectedYear]);

$lookupStmt = $conn->prepare("SELECT DISTINCT batch_id FROM meb WHERE batch_id IN ($placeholders) AND YEAR(time_stamp) = ? ORDER BY batch_id ASC");
if (!$lookupStmt) {
    security_send_json(["success" => false, "error" => "Failed to verify the selected batches."], 500);
}
$lookupStmt->bind_param($types, ...$params);
$lookupStmt->execute();
$lookupResult = $lookupStmt->get_result();
$matchedBatchIds = [];
while ($row = $lookupResult->fetch_assoc()) {
    $matchedBatchIds[] = (int) $row['batch_id'];
}
$lookupStmt->close();

if (empty($matchedBatchIds)) {
    security_send_json(["success" => false, "error" => "The selected batches were not found in the current fiscal year or have already been deleted."], 400);
}

$batchIds = $matchedBatchIds;
$placeholders = implode(',', array_fill(0, count($batchIds), '?'));
$types = str_repeat('i', count($batchIds)) . 'i';
$params = array_merge($batchIds, [$selectedYear]);
$stmt = $conn->prepare("DELETE FROM meb WHERE batch_id IN ($placeholders) AND YEAR(time_stamp) = ?");
if (!$stmt) {
    security_send_json(["success" => false, "error" => "Failed to prepare the batch delete request."], 500);
}
$stmt->bind_param($types, ...$params);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    $deletedCount = $stmt->affected_rows;
    $deletedBatchCount = count($batchIds);
    $batchList = implode(', ', $batchIds);

    app_notification_create($conn, [
        'category' => 'meb',
        'title' => $deletedBatchCount === 1 ? 'MEB batch deleted' : 'MEB batches deleted',
        'message' => app_notification_actor_name_from_session() . ' deleted ' . $deletedBatchCount . ' MEB ' . ($deletedBatchCount === 1 ? 'batch' : 'batches') . " ({$batchList}) for fiscal year {$selectedYear}.",
        'url' => app_notification_build_url('pages/data-tracking-meb'),
        'icon_class' => 'fas fa-trash',
        'color_class' => 'text-danger',
        'actor_user_id' => (int) ($_SESSION['user_id'] ?? 0),
        'actor_name' => app_notification_actor_name_from_session(),
    ]);
    kodus_socket_broadcast('kodus.meb', 'meb.changed', [
        'action' => $deletedBatchCount === 1 ? 'batch_deleted' : 'batches_deleted',
        'batch_id' => $deletedBatchCount === 1 ? $batchIds[0] : null,
        'batch_ids' => $batchIds,
        'year' => $selectedYear,
        'actor_id' => (int) ($_SESSION['user_id'] ?? 0),
    ]);
    kodus_socket_broadcast('kodus.meb', 'meb.validation.changed', [
        'action' => $deletedBatchCount === 1 ? 'batch_deleted' : 'batches_deleted',
        'batch_id' => $deletedBatchCount === 1 ? $batchIds[0] : null,
        'batch_ids' => $batchIds,
        'year' => $selectedYear,
        'actor_id' => (int) ($_SESSION['user_id'] ?? 0),
    ]);
    $stmt->close();
    security_send_json([
        "success" => true,
        "deletedRows" => $deletedCount,
        "deletedBatches" => $deletedBatchCount,
        "year" => $selectedYear,
    ], 200);
} else {
    $stmt->close();
    security_send_json(["success" => false, "error" => "The selected batches were not found in the current fiscal year or have already been deleted."], 400);
}
