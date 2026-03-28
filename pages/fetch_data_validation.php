<?php
session_start();
include('../config.php');

// Ensure fiscal year is selected
if (!isset($_SESSION['selected_year'])) {
    echo json_encode(["error" => "Fiscal year not selected"]);
    exit;
}

$selectedYear = (int) $_SESSION['selected_year'];

// Pagination
$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$limit = isset($_GET['length']) ? (int)$_GET['length'] : 10;
if ($limit == -1) $limit = 10000;

// Search
$searchValue = isset($_GET['search']['value']) ? $_GET['search']['value'] : '';

// Columns for sorting
$columns = ['province', 'lgu', 'barangay', 'beneficiary_count', 'validation'];
$orderColumnIndex = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 0;
$orderDir = isset($_GET['order'][0]['dir']) && in_array(strtoupper($_GET['order'][0]['dir']), ['ASC','DESC']) ? $_GET['order'][0]['dir'] : 'ASC';
$orderByClause = "ORDER BY " . $columns[$orderColumnIndex] . " $orderDir";

// Base WHERE clause: filter by year
$whereClause = "WHERE YEAR(time_stamp) = ?";
$params = [$selectedYear];
$types  = "i";

// Append search conditions
if (!empty($searchValue)) {
    $whereClause .= " AND (province LIKE ? OR lgu LIKE ? OR barangay LIKE ?)";
    $searchLike = "%$searchValue%";
    for ($i = 0; $i < 3; $i++) {
        $params[] = $searchLike;
        $types   .= "s";
    }
}

// Main query: grouped with validation
$sql = "SELECT 
            province, 
            lgu, 
            barangay, 
            COUNT(lastName) AS beneficiary_count,
            (SELECT 
                CASE 
                    WHEN SUM(CASE WHEN validation != '✓' THEN 1 ELSE 0 END) = 0 THEN '✓' 
                    ELSE '' 
                END 
             FROM meb AS m2 
             WHERE m2.lgu = meb.lgu AND YEAR(m2.time_stamp) = ?) AS validation
        FROM meb
        $whereClause
        GROUP BY province, lgu, barangay
        $orderByClause
        LIMIT ?, ?";

// Add the extra year param for the subquery + limit params
$params[] = $selectedYear;
$params[] = $start;
$params[] = $limit;
$types .= "iii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$stmt->close();

// Count filtered rows
$sql_count = "SELECT COUNT(DISTINCT CONCAT(province,'-',lgu,'-',barangay)) AS total
              FROM meb
              $whereClause";
$countParams = array_slice($params, 0, count($params) - 3); // remove subquery + limit params
$countTypes  = substr($types, 0, strlen($types) - 3);

$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param($countTypes, ...$countParams);
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$stmt_count->close();

// Count total rows without filters (for DataTables)
$sql_total = "SELECT COUNT(DISTINCT CONCAT(province,'-',lgu,'-',barangay)) AS total FROM meb WHERE YEAR(time_stamp) = ?";
$stmt_total = $conn->prepare($sql_total);
$stmt_total->bind_param("i", $selectedYear);
$stmt_total->execute();
$total_result = $stmt_total->get_result();
$total_records = $total_result->fetch_assoc()['total'];
$stmt_total->close();

// DataTables response
$response = [
    "draw" => isset($_GET['draw']) ? (int)$_GET['draw'] : 1,
    "recordsTotal" => $total_records,
    "recordsFiltered" => $total_rows,
    "data" => $data
];

echo json_encode($response);
$conn->close();
?>
