<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

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
$details = sprintf(
    'Marked %d MEB row(s) as %s. IDs: %s',
    count($ids),
    $status === 'validated' ? 'validated' : 'not validated',
    implode(',', $ids)
);
$logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
$logStmt->bind_param("isss", $userId, $action, $details, $ipAddress);
$logStmt->execute();
$logStmt->close();

echo json_encode([
    'success' => true,
    'message' => $status === 'validated' ? 'Rows marked as validated.' : 'Rows marked as not validated.',
    'affected_rows' => $affected,
]);
