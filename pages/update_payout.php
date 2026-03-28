<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
session_start();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }

    // Get logged in user ID
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        throw new Exception("Unauthorized. User not logged in.");
    }

    // Get form data
    $id = $_POST['id'] ?? null;
    $province = $_POST['province'] ?? null;
    $lgu = $_POST['lgu'] ?? null;
    $barangay = $_POST['barangay'] ?? null;
    $benesNumber = $_POST['benesNumber'] ?? null;
    $amount = $_POST['amount'] ?? null;
    $paid = $_POST['paid'] ?? null;
    $payoutDate = !empty($_POST['payoutDate']) ? $_POST['payoutDate'] : null;

    if (!$id) {
        throw new Exception("Invalid request. Missing record ID.");
    }

    // Update breakdown
    $sql = "UPDATE breakdown SET 
                province = ?, 
                lgu = ?, 
                barangay = ?, 
                benesNumber = ?, 
                amount = ?, 
                paid = ?, 
                payoutDate = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "sssiiisi", 
        $province, $lgu, $barangay, $benesNumber, $amount, $paid, $payoutDate, $id
    );

    if ($stmt->execute()) {
        // Log to audit_logs
        $details = "Updated payout ID #$id: "
                 . "province=$province, lgu=$lgu, barangay=$barangay, "
                 . "benesNumber=$benesNumber, amount=$amount, "
                 . "paid=$paid, payoutDate=$payoutDate";

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

        $logSql = "INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) 
                   VALUES (?, 'Payout Update', ?, ?, NOW())";
        $logStmt = $conn->prepare($logSql);
        $logStmt->bind_param("iss", $userId, $details, $ip);
        $logStmt->execute();
        $logStmt->close();

        echo json_encode(['success' => true, 'message' => 'Payout updated successfully.']);
    } else {
        throw new Exception("Execution failed: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>