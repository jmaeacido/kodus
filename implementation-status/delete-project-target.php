<?php
require_once '../security.php';
security_bootstrap_session();
require_once '../auth_helpers.php';
include('../config.php');
require_once '../project_targets_helpers.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();
header('Content-Type: application/json');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

if (!isset($_SESSION['selected_year'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Fiscal year not selected.']);
    exit;
}

ensureProjectLawaBinhiTargets($conn);

$selectedYear = (int) $_SESSION['selected_year'];
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid target record.']);
    exit;
}

$stmt = $conn->prepare('DELETE FROM project_lawa_binhi_targets WHERE id = ? AND fiscal_year = ?');
$stmt->bind_param('ii', $id, $selectedYear);
$stmt->execute();
$deleted = $stmt->affected_rows > 0;
$stmt->close();

if (!$deleted) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Target record not found.']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Baseline target deleted successfully.']);
