<?php
session_start();
include('config.php');

// Make sure a fiscal year was selected
if (!isset($_SESSION['selected_year'])) {
    echo json_encode(["error" => "Fiscal year not selected"]);
    exit;
}

$year = (int) $_SESSION['selected_year'];

// SQL with time_stamp filter
$sql = "SELECT
            COUNT(lastName) AS beneficiary_count,
            COUNT(DISTINCT CONCAT(barangay, lgu, province)) AS barangay_count,
            COUNT(DISTINCT CONCAT(lgu, province)) AS municipality_count,
            COUNT(DISTINCT province) AS province_count,
            SUM(CASE WHEN sex = 'FEMALE' THEN 1 ELSE 0 END) AS female_count, 
            SUM(CASE WHEN sex = 'MALE' THEN 1 ELSE 0 END) AS male_count,
            SUM(CASE WHEN nhts1 = '✓' THEN 1 ELSE 0 END) AS nhts1_count,
            SUM(CASE WHEN nhts2 = '✓' THEN 1 ELSE 0 END) AS nhts2_count,
            SUM(CASE WHEN fourPs = '✓' THEN 1 ELSE 0 END) AS fourPs_count,
            SUM(CASE WHEN F = '✓' THEN 1 ELSE 0 END) AS farmer_count,
            SUM(CASE WHEN FF = '✓' THEN 1 ELSE 0 END) AS fisherfolk_count,
            SUM(CASE WHEN IP = '✓' THEN 1 ELSE 0 END) AS ip_count,
            SUM(CASE WHEN SC = '✓' THEN 1 ELSE 0 END) AS sc_count,
            SUM(CASE WHEN SP = '✓' THEN 1 ELSE 0 END) AS sp_count,
            SUM(CASE WHEN PW = '✓' THEN 1 ELSE 0 END) AS pw_count,
            SUM(CASE WHEN PWD REGEXP '^[A-Z]$' THEN 1 ELSE 0 END) AS pwd_count,
            SUM(CASE WHEN OSY = '✓' THEN 1 ELSE 0 END) AS osy_count,
            SUM(CASE WHEN FR = '✓' THEN 1 ELSE 0 END) AS fr_count,
            SUM(CASE WHEN ybDs = '✓' THEN 1 ELSE 0 END) AS ybDs_count,
            SUM(CASE WHEN lgbtqia = '✓' THEN 1 ELSE 0 END) AS lgbtqia_count,
            SUM(CASE WHEN sex = 'FEMALE' AND nhts1 = '✓' THEN 1 ELSE 0 END) AS female_nhts1_count,
            SUM(CASE WHEN sex = 'MALE' AND nhts1 = '✓' THEN 1 ELSE 0 END) AS male_nhts1_count,
            SUM(CASE WHEN sex = 'FEMALE' AND nhts2 = '✓' THEN 1 ELSE 0 END) AS female_nhts2_count,
            SUM(CASE WHEN sex = 'MALE' AND nhts2 = '✓' THEN 1 ELSE 0 END) AS male_nhts2_count,
            SUM(CASE WHEN sex = 'FEMALE' AND F = '✓' THEN 1 ELSE 0 END) AS female_farmer_count,
            SUM(CASE WHEN sex = 'MALE' AND F = '✓' THEN 1 ELSE 0 END) AS male_farmer_count
        FROM meb
        WHERE YEAR(time_stamp) = ?";

// Prepare & execute
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $year);
$stmt->execute();

$result = $stmt->get_result();

if ($result) {
    $row = $result->fetch_assoc();

    // Cast all values to int
    foreach ($row as $key => $value) {
        $row[$key] = (int) $value;
    }

    echo json_encode($row);
} else {
    echo json_encode(["error" => "Query failed"]);
}

$stmt->close();
$conn->close();
?>