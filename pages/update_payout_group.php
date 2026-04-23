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

    $recordIds = [];
    foreach ($records as $record) {
        $recordIds[] = isset($record['id']) ? (int) $record['id'] : 0;
    }
    $recordIds = array_values(array_filter($recordIds, static fn ($value) => $value > 0));
    if ($recordIds === []) {
        throw new Exception('No valid payout record IDs were submitted.');
    }

    $placeholders = implode(',', array_fill(0, count($recordIds), '?'));
    $existingSql = "SELECT id, province, lgu, barangay, benesNumber, amount, paid, payoutDate FROM breakdown WHERE id IN ($placeholders)";
    $existingStmt = $conn->prepare($existingSql);
    $existingStmt->bind_param(str_repeat('i', count($recordIds)), ...$recordIds);
    $existingStmt->execute();
    $existingRows = db_stmt_fetch_all_assoc($existingStmt);
    $existingStmt->close();

    $existingById = [];
    foreach ($existingRows as $existingRow) {
        $existingById[(int) $existingRow['id']] = $existingRow;
    }

    $updateStmt = $conn->prepare("UPDATE breakdown SET province = ?, lgu = ?, barangay = ?, benesNumber = ?, amount = ?, paid = ?, payoutDate = ? WHERE id = ?");
    if (!$updateStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $conn->begin_transaction();
    $updatedIds = [];
    $changeParts = [];

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
        $previous = $existingById[$id] ?? [];
        $changes = audit_collect_field_changes(
            [
                'province' => $previous['province'] ?? null,
                'lgu' => $previous['lgu'] ?? null,
                'barangay' => $previous['barangay'] ?? null,
                'benesNumber' => $previous['benesNumber'] ?? null,
                'amount' => $previous['amount'] ?? null,
                'paid' => $previous['paid'] ?? null,
                'payoutDate' => $previous['payoutDate'] ?? null,
            ],
            [
                'province' => $province,
                'lgu' => $lgu,
                'barangay' => $barangay,
                'benesNumber' => $benesNumber,
                'amount' => $amount,
                'paid' => $paid,
                'payoutDate' => $payoutDate,
            ]
        );
        $changeParts[] = 'Record ID ' . $id . ' [' . audit_format_field_changes($changes) . ']';
    }

    $conn->commit();
    $updateStmt->close();

    $details = 'Updated municipality payout records: province=' . $province
        . ', lgu=' . $lgu
        . ', recordIds=' . implode(',', $updatedIds)
        . ', selectedYear=' . $selectedYear
        . ', dailyWageRate=' . $dailyWageRate
        . ', payoutDays=' . $payoutDays
        . ' | Changes: ' . implode(' | ', $changeParts);

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    audit_log($conn, (int) $userId, 'Payout Municipality Update', $details, $ip);

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
