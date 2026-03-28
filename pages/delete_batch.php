<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow frontend to access backend

$conn = new mysqli('localhost', 'root', '', 'kodus_db');

if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Connection failed: " . $conn->connect_error]);
    exit;
}

// Ensure batchId is provided
if (!isset($_POST['batchId']) || empty(trim($_POST['batchId']))) {
    echo json_encode(["success" => false, "error" => "Batch ID is required."]);
    exit;
}

$batchId = $conn->real_escape_string($_POST['batchId']);
$sql = "DELETE FROM meb WHERE batch_id = '$batchId'";

if ($conn->query($sql) === TRUE) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}

$conn->close();
exit;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete by Batch</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <script>
        <?php if (!empty($errorMsg)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= htmlspecialchars($errorMsg) ?>',
            }).then(() => {
                window.history.back(); // Redirect to main page
            });
        <?php elseif (!empty($successMsg)): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '<?= htmlspecialchars($successMsg) ?>',
            }).then(() => {
                window.history.back(); // Redirect to main page
            });
        <?php endif; ?>
    </script>
</body>
</html>
