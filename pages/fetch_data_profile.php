<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'kodus_db'); // Update with your DB details
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Retrieve the start (page) and length (number of rows) for pagination
$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$limit = isset($_GET['length']) ? (int)$_GET['length'] : 10;

// Prevent excessive data loading
if ($limit == -1) {
    $limit = 10000; // Limit max rows to 10,000 to avoid server crashes
}

// Retrieve the search query if any
$searchValue = isset($_GET['search']['value']) ? $_GET['search']['value'] : '';

// Retrieve the column to sort by and the direction (ascending/descending)
$orderColumnIndex = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 0;
$orderDir = isset($_GET['order'][0]['dir']) ? $_GET['order'][0]['dir'] : 'asc';

// Define the columns that can be sorted and searched
$columns = ['id', 'lastName', 'firstName', 'middleName', 'ext', 'purok', 'barangay', 'lgu', 'province', 'birthDate', 'age', 'sex', 'civilStatus', 'nhts1', 'nhts2', 'fourPs', 'F', 'FF', 'IP', 'SC', 'SP', 'PW', 'PWD', 'OSY', 'FR', 'ybDs', 'lgbtqia'];

// Ensure the order column index is within valid range
if ($orderColumnIndex < 0 || $orderColumnIndex >= count($columns)) {
    $orderColumnIndex = 0; // Default to first column if invalid
}

// Build the WHERE clause for search
$whereClause = '';
if (!empty($searchValue)) {
    $whereClause = "WHERE lastName LIKE '%$searchValue%' 
                    OR firstName LIKE '%$searchValue%' 
                    OR middleName LIKE '%$searchValue%' 
                    OR ext LIKE '%$searchValue%' 
                    OR purok LIKE '%$searchValue%' 
                    OR barangay LIKE '%$searchValue%' 
                    OR lgu LIKE '%$searchValue%' 
                    OR province LIKE '%$searchValue%'";
}

// Force sort by specific columns: province, lgu, barangay, lastName, firstName, middleName, ext
$orderByClause = "ORDER BY province ASC, lgu ASC, barangay ASC, lastName ASC, firstName ASC, middleName ASC, ext ASC";

// Query to fetch data for the current page with search and sorting applied
$sql = "SELECT * FROM meb $whereClause $orderByClause LIMIT $start, $limit";
$result = $conn->query($sql);

// Prepare the data to be sent back
$data = [];
while ($row = $result->fetch_assoc()) {
    $row['Name'] = trim(
        $row['firstName'] . ' ' .
        (!empty($row['middleName']) ? strtoupper(substr($row['middleName'], 0, 1)) . '. ' : '') .
        $row['lastName'] .
        (!empty($row['ext']) ? ' ' . $row['ext'] : '')
    );
    $data[] = $row;
}

// Query to get the total number of rows (for pagination) with search filter applied
$sql_count = "SELECT COUNT(*) AS total FROM meb $whereClause";
$count_result = $conn->query($sql_count);
$total_rows = $count_result->fetch_assoc()['total'];

// Prepare the response in JSON format
$response = [
    "draw" => isset($_GET['draw']) ? (int)$_GET['draw'] : 1,  // DataTables' draw value
    "recordsTotal" => $total_rows,  // Total number of records
    "recordsFiltered" => $total_rows,  // Total number of records after filtering
    "data" => $data  // The actual data for the current page
];

// Output the response as JSON
echo json_encode($response);

// Close the database connection
$conn->close();
?>