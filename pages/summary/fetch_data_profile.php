<?php
session_start();

// Database connection
include('../../config.php');

// Ensure fiscal year is selected
if (!isset($_SESSION['selected_year'])) {
    echo json_encode(["error" => "Fiscal year not selected"]);
    exit;
}

$year = (int) $_SESSION['selected_year'];

// Pagination
$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$limit = isset($_GET['length']) ? (int)$_GET['length'] : 10;

if ($limit == -1) {
    $limit = 10000;
}

// Search
$searchValue = isset($_GET['search']['value']) ? $_GET['search']['value'] : '';
$searchEscaped = $conn->real_escape_string($searchValue);

// Sorting
$orderColumnIndex = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 0;
$orderDir = isset($_GET['order'][0]['dir']) ? $_GET['order'][0]['dir'] : 'asc';

// Columns
$columns = ['id', 'lastName', 'firstName', 'middleName', 'ext', 'purok', 'barangay', 'lgu', 'province', 'birthDate', 'age', 'sex', 'civilStatus', 'nhts1', 'nhts2', 'fourPs', 'F', 'FF', 'IP', 'SC', 'SP', 'PW', 'PWD', 'OSY', 'FR', 'ybDs', 'lgbtqia'];

if ($orderColumnIndex < 0 || $orderColumnIndex >= count($columns)) {
    $orderColumnIndex = 0;
}

// =====================
// PWD mapping
// =====================
$pwdDescriptions = [
    "A" => "Multiple Disabilities",
    "B" => "Intellectual Disability",
    "C" => "Learning Disability",
    "D" => "Mental Disability",
    "E" => "Physical Disability (Orthopedic)",
    "F" => "Psychosocial Disability",
    "G" => "Non-apparent Visual Disability",
    "H" => "Non-apparent Speech and Language Impairment",
    "I" => "Non-apparent Cancer",
    "J" => "Non-apparent Rare Disease",
    "K" => "Deaf/Hard of Hearing Disability"
];

// =====================
// BASE WHERE (YEAR FILTER FIRST)
// =====================
$whereParts = [];
$whereParts[] = "YEAR(time_stamp) = $year";

// =====================
// SEARCH FILTER
// =====================
if (!empty($searchValue)) {

    // Match PWD descriptions
    $matchedPwdCodes = [];
    foreach ($pwdDescriptions as $code => $desc) {
        if (stripos($desc, $searchValue) !== false) {
            $matchedPwdCodes[] = $code;
        }
    }

    $pwdSearch = '';
    if (!empty($matchedPwdCodes)) {
        $pwdConditions = array_map(function ($code) use ($conn) {
            return "PWD LIKE '%" . $conn->real_escape_string($code) . "%'";
        }, $matchedPwdCodes);
        $pwdSearch = implode(' OR ', $pwdConditions);
    }

    $searchParts = [
        "lastName LIKE '%$searchEscaped%'",
        "firstName LIKE '%$searchEscaped%'",
        "middleName LIKE '%$searchEscaped%'",
        "ext LIKE '%$searchEscaped%'",
        "age LIKE '%$searchEscaped%'",
        "lgu LIKE '%$searchEscaped%'",
        "province LIKE '%$searchEscaped%'"
    ];

    if (!empty($pwdSearch)) {
        $searchParts[] = "($pwdSearch)";
    } else {
        $searchParts[] = "PWD LIKE '%$searchEscaped%'";
    }

    $whereParts[] = "(" . implode(" OR ", $searchParts) . ")";
}

// Final WHERE clause
$whereClause = "WHERE " . implode(" AND ", $whereParts);

// =====================
// ORDERING (FIXED)
// =====================
$orderByClause = "ORDER BY province ASC, lgu ASC, barangay ASC, 
                         lastName ASC, firstName ASC, middleName ASC, ext ASC";

// =====================
// MAIN QUERY
// =====================
$sql = "SELECT * FROM meb $whereClause $orderByClause LIMIT $start, $limit";
$result = $conn->query($sql);

// =====================
// FETCH DATA
// =====================
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

// =====================
// COUNT FILTERED
// =====================
$sql_count = "SELECT COUNT(*) AS total FROM meb $whereClause";
$count_result = $conn->query($sql_count);
$total_rows = $count_result->fetch_assoc()['total'];

// =====================
// RESPONSE
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