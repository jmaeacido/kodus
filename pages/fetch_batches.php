<?php
header('Content-Type: application/json');

$conn = new mysqli('localhost', 'root', '', 'kodus_db');

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

$sql = "SELECT DISTINCT batch_id FROM meb ORDER BY batch_id ASC";
$result = $conn->query($sql);

$batches = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $batches[] = $row['batch_id'];
    }
}

$conn->close();
echo json_encode($batches);
?>