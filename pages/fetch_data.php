<?php
session_start();

include('../config.php');

// Ensure fiscal year is selected
if (!isset($_SESSION['selected_year'])) {
    echo json_encode(["error" => "Fiscal year not selected"]);
    exit;
}

$time_stamp = (int) $_SESSION['selected_year'];

// Pagination
$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$limit = isset($_GET['length']) ? (int)$_GET['length'] : 10;

// Prevent excessive data loading
if ($limit == -1) {
    $limit = 10000;
}

// Search
$searchValue = isset($_GET['search']['value']) ? $_GET['search']['value'] : '';

// Sorting (fixed order as per your logic)
$orderByClause = "ORDER BY province ASC, lgu ASC, barangay ASC, 
                         lastName ASC, firstName ASC, middleName ASC, ext ASC, id ASC";

// Base WHERE clause (fiscal year is mandatory)
$whereClause = "WHERE YEAR(time_stamp) = ?";
$params = [$time_stamp];
$types  = "i";

// Append search conditions
if (!empty($searchValue)) {
    $whereClause .= " AND (
        lastName LIKE ? OR
        firstName LIKE ? OR
        middleName LIKE ? OR
        ext LIKE ? OR
        purok LIKE ? OR
        barangay LIKE ? OR
        lgu LIKE ? OR
        province LIKE ?
    )";

    $searchLike = "%$searchValue%";
    for ($i = 0; $i < 8; $i++) {
        $params[] = $searchLike;
        $types   .= "s";
    }
}

// =====================
// Fetch paginated data
// =====================
$sql = "SELECT * FROM meb $whereClause $orderByClause LIMIT ?, ?";
$params[] = $start;
$params[] = $limit;
$types   .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$stmt->close();

// =====================
// Count total records
// =====================
$sql_count = "SELECT COUNT(*) AS total FROM meb $whereClause";
$stmt_count = $conn->prepare($sql_count);

// Remove LIMIT params for count query
$countParams = array_slice($params, 0, count($params) - 2);
$countTypes  = substr($types, 0, strlen($types) - 2);

$stmt_count->bind_param($countTypes, ...$countParams);
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$stmt_count->close();

// =====================
// DataTables response
// =====================
$response = [
    "draw" => isset($_GET['draw']) ? (int)$_GET['draw'] : 1,
    "recordsTotal" => $total_rows,
    "recordsFiltered" => $total_rows,
    "data" => $data
];

echo json_encode($response);
$conn->close();
?>
