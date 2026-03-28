<?php
include('../config.php');

header('Content-Type: application/json');

// Fetch incoming descriptions and their file names
$sql = "SELECT id, description, file_name FROM incoming ORDER BY id DESC";
$result = $conn->query($sql);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);