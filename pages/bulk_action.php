<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../socket_helpers.php';
require_once __DIR__ . '/../app_notification_helpers.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    $errorMsg = "Access denied. Admins only.";
} else {

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
                $selectedIds = array_values(array_filter(array_map('intval', $selectedIds)));

                if (empty($selectedIds)) {
                    $errorMsg = "Please select at least one valid row to delete.";
                } else {
                    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                    $types = str_repeat('i', count($selectedIds));
                    $stmt = $conn->prepare("DELETE FROM meb WHERE id IN ($placeholders)");

                    if ($stmt) {
                        $stmt->bind_param($types, ...$selectedIds);

                        if ($stmt->execute() === TRUE && $stmt->affected_rows > 0) {
                            $successMsg = "Selected rows have been deleted.";
                            $deletedCount = $stmt->affected_rows;
                            app_notification_create($conn, [
                                'category' => 'meb',
                                'title' => $deletedCount === 1 ? 'MEB record deleted' : 'MEB records deleted',
                                'message' => app_notification_actor_name_from_session() . ' deleted ' . $deletedCount . ' MEB ' . ($deletedCount === 1 ? 'record' : 'records') . '.',
                                'url' => app_notification_build_url('pages/data-tracking-meb'),
                                'icon_class' => 'fas fa-trash-alt',
                                'color_class' => 'text-danger',
                                'actor_user_id' => (int) ($_SESSION['user_id'] ?? 0),
                                'actor_name' => app_notification_actor_name_from_session(),
                            ]);
                            kodus_socket_broadcast('kodus.meb', 'meb.changed', [
                                'action' => 'bulk_deleted',
                                'ids' => $selectedIds,
                                'actor_id' => (int) ($_SESSION['user_id'] ?? 0),
                            ]);
                            kodus_socket_broadcast('kodus.meb', 'meb.validation.changed', [
                                'action' => 'bulk_deleted',
                                'ids' => $selectedIds,
                                'actor_id' => (int) ($_SESSION['user_id'] ?? 0),
                            ]);
                        } else {
                            $errorMsg = "No matching rows were deleted.";
                        }

                        $stmt->close();
                    } else {
                        $errorMsg = "Failed to prepare the delete request.";
                    }
                }
            }
        } elseif ($action === 'edit') {
            // Redirect to edit page with selected IDs
            $ids = implode(',', array_values(array_filter(array_map('intval', $selectedIds))));
            header("Location: data-tracking-meb-edit?ids=$ids&return_to=data-tracking-meb");
            exit();
        }
    } else {
        $errorMsg = "Please select at least one row.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Action</title>
    <link rel="icon" href="<?php echo htmlspecialchars($app_root, ENT_QUOTES, 'UTF-8'); ?>favicon.ico" type="image/x-icon">
    <script src="../plugins/sweetalert2/sweetalert2.min.js"></script>
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
