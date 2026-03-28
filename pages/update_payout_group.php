<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config.php';
require_once __DIR__ . '/../project_variable_helpers.php';
session_start();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        throw new Exception('Unauthorized. User not logged in.');
    }

    $userType = $_SESSION['user_type'] ?? '';
    if (!in_array($userType, ['admin', 'aa'], true)) {
        throw new Exception('Unauthorized. You do not have permission to update payout records.');
    }

    if (!isset($_SESSION['selected_year'])) {
        throw new Exception('Fiscal year not selected.');
    }

    $selectedYear = (int) $_SESSION['selected_year'];
    $dailyWageRate = project_variable_get_number($conn, 'daily_wage_rate', $selectedYear, 0);
    $payoutDays = (int) round(project_variable_get_number($conn, 'working_days', $selectedYear, 20));
    $payoutDays = $payoutDays > 0 ? $payoutDays : 20;
    $beneficiaryPayoutRate = (int) round($dailyWageRate * $payoutDays);

    if ($dailyWageRate <= 0) {
        throw new Exception('Missing project variable for payout daily wage rate in the selected fiscal year.');
    }

    $province = trim((string) ($_POST['province'] ?? ''));
    $lgu = trim((string) ($_POST['lgu'] ?? ''));
    $recordsRaw = $_POST['records'] ?? '[]';
    $records = json_decode((string) $recordsRaw, true);

    if ($province === '' || $lgu === '') {
        throw new Exception('Province and city / municipality are required.');
    }

    if (!is_array($records) || $records === []) {
        throw new Exception('No payout records were submitted.');
    }

    $updateStmt = $conn->prepare("UPDATE breakdown SET province = ?, lgu = ?, barangay = ?, benesNumber = ?, amount = ?, paid = ?, payoutDate = ? WHERE id = ?");
    if (!$updateStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $conn->begin_transaction();
    $updatedIds = [];

    foreach ($records as $record) {
        $id = isset($record['id']) ? (int) $record['id'] : 0;
        $barangay = trim((string) ($record['barangay'] ?? ''));
        $benesNumber = isset($record['benesNumber']) ? (int) $record['benesNumber'] : -1;
        $paid = isset($record['paid']) ? (int) $record['paid'] : -1;
        $payoutDate = !empty($record['payoutDate']) ? (string) $record['payoutDate'] : null;
        $amount = $benesNumber * $beneficiaryPayoutRate;

        if ($id <= 0) {
            throw new Exception('Invalid payout record ID.');
        }
        if ($barangay === '') {
            throw new Exception('Each payout record must have a barangay.');
        }
        if ($benesNumber < 0) {
            throw new Exception("Barangay {$barangay}: beneficiaries must be 0 or greater.");
        }
        if ($paid < 0) {
            throw new Exception("Barangay {$barangay}: paid must be 0 or greater.");
        }
        if ($paid > $benesNumber) {
            throw new Exception("Barangay {$barangay}: paid cannot be greater than beneficiaries.");
        }
        if ($payoutDate !== null) {
            $dateValue = DateTime::createFromFormat('Y-m-d', $payoutDate);
            if (!$dateValue || $dateValue->format('Y-m-d') !== $payoutDate) {
                throw new Exception("Barangay {$barangay}: invalid payout date.");
            }
        }

        $updateStmt->bind_param('sssiiisi', $province, $lgu, $barangay, $benesNumber, $amount, $paid, $payoutDate, $id);
        if (!$updateStmt->execute()) {
            throw new Exception('Failed to update payout record #' . $id . ': ' . $updateStmt->error);
        }

        $updatedIds[] = $id;
    }

    $conn->commit();
    $updateStmt->close();

    $details = 'Updated municipality payout records: province=' . $province
        . ', lgu=' . $lgu
        . ', recordIds=' . implode(',', $updatedIds)
        . ', selectedYear=' . $selectedYear
        . ', dailyWageRate=' . $dailyWageRate
        . ', payoutDays=' . $payoutDays;

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, 'Payout Municipality Update', ?, ?, NOW())");
    if ($logStmt) {
        $logStmt->bind_param('iss', $userId, $details, $ip);
        $logStmt->execute();
        $logStmt->close();
    }

    echo json_encode(['success' => true, 'message' => 'Payout records updated successfully.']);
} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli && $conn->errno !== null) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
        }
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
