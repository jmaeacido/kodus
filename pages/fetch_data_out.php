<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
include('../config.php');

auth_handle_page_access($conn);
auth_apply_security_headers();

// Ensure a fiscal year is selected
if (!isset($_SESSION['selected_year'])) {
    echo json_encode(["error" => "Fiscal year not selected"]);
    exit;
}

$year = (int) $_SESSION['selected_year'];

// Pagination
$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$limit = isset($_GET['length']) ? (int)$_GET['length'] : 10;
if ($limit == -1) $limit = 10000;

// Search
$searchValue = isset($_GET['search']['value']) ? $_GET['search']['value'] : '';

// Columns, aligned to the DataTables column indexes including the action column.
$columns = [null, 'date_out', 'tracking_number', 'description', 'remarks', 'file_name', 'receiving_office', 'date_forwarded', 'user_log'];

// Sorting
$orderColumnIndex = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 0;
$orderDir = isset($_GET['order'][0]['dir']) && in_array(strtoupper($_GET['order'][0]['dir']), ['ASC','DESC']) ? $_GET['order'][0]['dir'] : 'ASC';
$orderColumn = $columns[$orderColumnIndex] ?? null;
$orderByClause = $orderColumn
    ? "ORDER BY {$orderColumn} {$orderDir}, id DESC"
    : "ORDER BY date_out DESC, id DESC";

// =======================
// Build WHERE clause
// =======================
$whereClause = "WHERE YEAR(date_out) = ?";
$params = [$year];
$types = "i";

if (!empty($searchValue)) {
    $whereClause .= " AND (
        date_out LIKE ? OR
        tracking_number LIKE ? OR
        description LIKE ? OR
        remarks LIKE ? OR
        file_name LIKE ? OR
        receiving_office LIKE ? OR
        date_forwarded LIKE ? OR
        user_log LIKE ?
    )";

    $searchLike = "%$searchValue%";
    for ($i = 0; $i < 8; $i++) {
        $params[] = $searchLike;
        $types .= "s";
    }
}

// =======================
// Fetch paginated data
// =======================
$sql = "SELECT id, date_out, tracking_number, description, remarks, file_name, receiving_office, date_forwarded, user_log
        FROM outgoing $whereClause
        $orderByClause
        LIMIT ?, ?";

$params[] = $start;
$params[] = $limit;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = db_stmt_fetch_all_assoc($stmt);

$data = [];
foreach ($result as $row) {
    $data[] = $row;
}
$stmt->close();

// =======================
// Count total filtered
// =======================
$sql_count = "SELECT COUNT(*) AS total FROM outgoing $whereClause";
$countParams = array_slice($params, 0, count($params)-2); // remove LIMIT params
$countTypes  = substr($types, 0, strlen($types)-2);

$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param($countTypes, ...$countParams);
$stmt_count->execute();
$count_result = db_stmt_fetch_one_assoc($stmt_count);
$total_rows = $count_result['total'];
$stmt_count->close();

// =======================
// Count total records (without filters)
// =======================
$sql_total = "SELECT COUNT(*) AS total FROM outgoing WHERE YEAR(date_out) = ?";
$stmt_total = $conn->prepare($sql_total);
$stmt_total->bind_param('i', $year);
$stmt_total->execute();
$total_result = db_stmt_fetch_one_assoc($stmt_total);
$total_records = $total_result['total'] ?? 0;
$stmt_total->close();

// =======================
// Prepare response
// =======================
$response = [
    "draw" => isset($_GET['draw']) ? (int)$_GET['draw'] : 1,
    "recordsTotal" => $total_records,
    "recordsFiltered" => $total_rows,
    "data" => $data
];

echo json_encode($response);
$conn->close();
?>
