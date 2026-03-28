<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'kodus_db'); // Update with your DB details
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables for messages
$errorMsg = "";
$successMsg = "";

// Check if the action and selected rows are set
if (isset($_POST['action']) && isset($_POST['selected'])) {
    $action = $_POST['action'];
    $selectedIds = $_POST['selected'];

    if ($action === 'delete') {
        if (empty($selectedIds)) {
            $errorMsg = "Please select at least one row to delete.";
        } else {
            // Delete selected rows
            $ids = implode(',', array_map('intval', $selectedIds)); // Sanitize input to prevent SQL injection
            $sql = "DELETE FROM meb WHERE id IN ($ids)";
            if ($conn->query($sql) === TRUE) {
                $successMsg = "Selected rows have been deleted.";
            } else {
                $errorMsg = "Failed to delete rows.";
            }
        }
    } elseif ($action === 'edit') {
        // Redirect to edit page with selected IDs
        $ids = implode(',', $selectedIds);
        header("Location: data-tracking-meb-edit?ids=$ids");
        exit();
    }
} else {
    $errorMsg = "Please select at least one row.";
}

$conn->close();
?>

<?php
// Debugging: Check the selected data
echo '<pre style="color:grey;">';
var_dump($_POST['selected']);
echo '</pre>';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Action</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body style="background-color: grey;">
    <script>
        <?php if (!empty($errorMsg)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= htmlspecialchars($errorMsg) ?>',
            }).then(() => {
                window.history.back();  // Correct redirect after error
            });
        <?php elseif (!empty($successMsg)): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '<?= htmlspecialchars($successMsg) ?>',
            }).then(() => {
                window.history.back();  // Correct redirect after success
            });
        <?php endif; ?>
    </script>
</body>
</html>