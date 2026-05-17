<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

function audit_logs_data_display_name(array $row): string
{
    $parts = array_filter([
        trim((string) ($row['first_name'] ?? '')),
        trim((string) ($row['middle_name'] ?? '')),
        trim((string) ($row['last_name'] ?? '')),
    ], static fn(string $value): bool => $value !== '');

    $fullName = trim(ucwords(strtolower(implode(' ', $parts))));
    if (!empty($row['ext'])) {
        $fullName .= ' ' . trim((string) $row['ext']);
    }

    if ($fullName !== '') {
        return $fullName;
    }

    $username = trim((string) ($row['username'] ?? ''));
    if ($username !== '') {
        return $username;
    }

    $userId = isset($row['user_id']) ? (int) $row['user_id'] : 0;
    return $userId > 0 ? 'User #' . $userId : 'System';
}

function audit_logs_data_bind_params(mysqli_stmt $stmt, string $types, array &$values): bool
{
    if ($types === '' || $values === []) {
        return true;
    }

    $references = [$types];
    foreach ($values as &$value) {
        $references[] = &$value;
    }

    return call_user_func_array([$stmt, 'bind_param'], $references);
}

function audit_logs_data_error(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['data' => [], 'error' => $message]);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId || !is_numeric($userId)) {
    audit_logs_data_error(401, 'Authentication required.');
}

$adminStmt = $conn->prepare('SELECT userType FROM users WHERE id = ?');
if (!$adminStmt) {
    audit_logs_data_error(500, 'Unable to verify permissions.');
}

$currentUserId = (int) $userId;
$adminStmt->bind_param('i', $currentUserId);
$adminStmt->execute();
$admin = db_stmt_fetch_one_assoc($adminStmt);
$adminStmt->close();

if (($admin['userType'] ?? '') !== 'admin') {
    audit_logs_data_error(403, 'Access denied.');
}

$selectedUserId = trim((string) ($_GET['user'] ?? ''));
$selectedAction = trim((string) ($_GET['action'] ?? ''));
$selectedDateFrom = trim((string) ($_GET['date_from'] ?? ''));
$selectedDateTo = trim((string) ($_GET['date_to'] ?? ''));

if ($selectedUserId !== '' && !ctype_digit($selectedUserId)) {
    $selectedUserId = '';
}

if ($selectedDateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDateFrom)) {
    $selectedDateFrom = '';
}

if ($selectedDateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDateTo)) {
    $selectedDateTo = '';
}

$whereClauses = [];
$bindTypes = '';
$bindValues = [];

if ($selectedUserId !== '') {
    $whereClauses[] = 'a.user_id = ?';
    $bindTypes .= 'i';
    $bindValues[] = (int) $selectedUserId;
}

if ($selectedAction !== '') {
    $whereClauses[] = 'a.action = ?';
    $bindTypes .= 's';
    $bindValues[] = $selectedAction;
}

if ($selectedDateFrom !== '') {
    $whereClauses[] = 'DATE(a.created_at) >= ?';
    $bindTypes .= 's';
    $bindValues[] = $selectedDateFrom;
}

if ($selectedDateTo !== '') {
    $whereClauses[] = 'DATE(a.created_at) <= ?';
    $bindTypes .= 's';
    $bindValues[] = $selectedDateTo;
}

$logsSql = "
    SELECT
        a.id,
        a.user_id,
        a.action,
        a.details,
        a.ip_address,
        a.created_at,
        u.username,
        u.first_name,
        u.middle_name,
        u.last_name,
        u.ext
    FROM audit_logs a
    LEFT JOIN users u ON u.id = a.user_id
";

if ($whereClauses !== []) {
    $logsSql .= ' WHERE ' . implode(' AND ', $whereClauses);
}

$logsSql .= ' ORDER BY a.created_at DESC, a.id DESC';

$logsStmt = $conn->prepare($logsSql);
if (!$logsStmt) {
    audit_logs_data_error(500, 'Unable to load audit logs.');
}

audit_logs_data_bind_params($logsStmt, $bindTypes, $bindValues);
$logsStmt->execute();

$rows = [];
foreach (db_stmt_fetch_all_assoc($logsStmt) as $row) {
    $rows[] = [
        'id' => (int) ($row['id'] ?? 0),
        'user' => audit_logs_data_display_name($row),
        'action' => (string) ($row['action'] ?? ''),
        'details' => (string) ($row['details'] ?? ''),
        'ip_address' => (string) ($row['ip_address'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'created_at_sort' => strtotime((string) ($row['created_at'] ?? '')) ?: 0,
    ];
}
$logsStmt->close();

echo json_encode([
    'data' => $rows,
    'recordsTotal' => count($rows),
    'recordsFiltered' => count($rows),
]);
