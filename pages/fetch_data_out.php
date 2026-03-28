<?php
session_start();
include('../config.php');

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

// Columns
$columns = ['date_out', 'tracking_number', 'description', 'remarks', 'file_name', 'receiving_office', 'date_forwarded', 'user_log'];

// Sorting
$orderColumnIndex = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 0;
$orderDir = isset($_GET['order'][0]['dir']) && in_array(strtoupper($_GET['order'][0]['dir']), ['ASC','DESC']) ? $_GET['order'][0]['dir'] : 'ASC';
$orderByClause = "ORDER BY " . $columns[$orderColumnIndex] . " $orderDir";

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
        ORDER BY date_out DESC, id DESC
        LIMIT ?, ?";

$params[] = $start;
$params[] = $limit;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
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
$count_result = $stmt_count->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$stmt_count->close();

// =======================
// Count total records (without filters)
// =======================
$sql_total = "SELECT COUNT(*) AS total FROM outgoing";
$total_result = $conn->query($sql_total);
$total_records = $total_result->fetch_assoc()['total'];

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
