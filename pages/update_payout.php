<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config.php';
require_once __DIR__ . '/../project_variable_helpers.php';
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

    $userType = $_SESSION['user_type'] ?? '';
    if (!in_array($userType, ['admin', 'aa'], true)) {
        throw new Exception("Unauthorized. You do not have permission to update payout records.");
    }

    if (!isset($_SESSION['selected_year'])) {
        throw new Exception("Fiscal year not selected.");
    }

    $selectedYear = (int) $_SESSION['selected_year'];
    $dailyWageRate = project_variable_get_number($conn, 'daily_wage_rate', $selectedYear, 0);
    $payoutDays = (int) round(project_variable_get_number($conn, 'working_days', $selectedYear, 20));
    $payoutDays = $payoutDays > 0 ? $payoutDays : 20;
    $beneficiaryPayoutRate = (int) round($dailyWageRate * $payoutDays);

    if ($dailyWageRate <= 0) {
        throw new Exception("Missing project variable for payout daily wage rate in the selected fiscal year.");
    }

    // Get form data
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $province = trim((string) ($_POST['province'] ?? ''));
    $lgu = trim((string) ($_POST['lgu'] ?? ''));
    $barangay = trim((string) ($_POST['barangay'] ?? ''));
    $benesNumber = isset($_POST['benesNumber']) ? (int) $_POST['benesNumber'] : -1;
    $paid = isset($_POST['paid']) ? (int) $_POST['paid'] : -1;
    $payoutDate = !empty($_POST['payoutDate']) ? $_POST['payoutDate'] : null;
    $amount = $benesNumber * $beneficiaryPayoutRate;

    if ($id <= 0) {
        throw new Exception("Invalid request. Missing record ID.");
    }

    if ($province === '' || $lgu === '' || $barangay === '') {
        throw new Exception("Province, city / municipality, and barangay are required.");
    }

    if ($benesNumber < 0) {
        throw new Exception("No. of Partner-Beneficiaries must be 0 or greater.");
    }

    if ($paid < 0) {
        throw new Exception("Paid must be 0 or greater.");
    }

    if ($paid > $benesNumber) {
        throw new Exception("Paid cannot be greater than the total number of beneficiaries.");
    }

    if ($payoutDate !== null) {
        $dateValue = DateTime::createFromFormat('Y-m-d', $payoutDate);
        if (!$dateValue || $dateValue->format('Y-m-d') !== $payoutDate) {
            throw new Exception("Invalid payout date.");
        }
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
                 . "paid=$paid, payoutDate=$payoutDate, selectedYear=$selectedYear, dailyWageRate=$dailyWageRate, payoutDays=$payoutDays";

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
