<?php
require_once '../security.php';
security_bootstrap_session();
security_require_csrf_token();
security_require_method(['POST']);

require_once '../config.php';
require_once __DIR__ . '/../socket_helpers.php';
require_once __DIR__ . '/../fund_monitoring_helpers.php';

$redirectTarget = 'fund-monitoring';
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$userType = (string) ($_SESSION['user_type'] ?? '');
$selectedYear = isset($_SESSION['selected_year']) ? (int) $_SESSION['selected_year'] : 0;
$action = trim((string) ($_POST['action'] ?? ''));

function fund_monitoring_set_flash(string $type, string $message): void
{
    $_SESSION['fund_monitoring_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function fund_monitoring_broadcast_change(int $selectedYear, string $action, int $userId, array $extra = []): void
{
    kodus_socket_broadcast('kodus.fund_monitoring', 'fund_monitoring.changed', array_merge([
        'action' => $action,
        'fiscal_year' => $selectedYear,
        'actor_id' => $userId,
    ], $extra));
}

if ($userId <= 0 || $selectedYear <= 0) {
    fund_monitoring_set_flash('danger', 'Your session is missing the selected fiscal year.');
    header('Location: ' . $redirectTarget);
    exit;
}

if ($userType !== 'admin') {
    fund_monitoring_set_flash('danger', 'Only administrators can modify fund monitoring data.');
    header('Location: ' . $redirectTarget);
    exit;
}

if ($action === 'save_month_entries') {
    $month = isset($_POST['entry_month']) ? (int) $_POST['entry_month'] : 0;
    $itemIds = $_POST['item_id'] ?? [];
    $obligations = $_POST['obligations'] ?? [];
    $disbursements = $_POST['disbursement'] ?? [];
    $entries = [];

    if (is_array($itemIds)) {
        foreach ($itemIds as $index => $itemId) {
            $entries[] = [
                'item_id' => (int) $itemId,
                'obligations' => $obligations[$index] ?? 0,
                'disbursement' => $disbursements[$index] ?? 0,
            ];
        }
    }

    $success = fund_monitoring_save_month_entries($conn, $selectedYear, $month, $entries, $userId);
    if ($success) {
        fund_monitoring_broadcast_change($selectedYear, 'monthly_entries_saved', $userId, [
            'month' => max(1, min(12, $month)),
        ]);
    }
    fund_monitoring_set_flash($success ? 'success' : 'danger', $success ? 'Monthly fund utilization data saved successfully.' : 'Unable to save the selected monthly updates.');
    header('Location: ' . $redirectTarget . '?month=' . max(1, min(12, $month)));
    exit;
}

if ($action === 'save_object_code') {
    $objectCodeName = trim((string) ($_POST['object_code_name'] ?? ''));
    $success = fund_monitoring_add_object_code($conn, $selectedYear, $objectCodeName, $userId);
    if ($success) {
        fund_monitoring_broadcast_change($selectedYear, 'object_code_saved', $userId);
    }
    fund_monitoring_set_flash($success ? 'success' : 'danger', $success ? 'Object code added to this fiscal year.' : 'Unable to save the object code.');
    header('Location: ' . $redirectTarget);
    exit;
}

if ($action === 'save_item') {
    $recordId = isset($_POST['record_id']) && $_POST['record_id'] !== '' ? (int) $_POST['record_id'] : null;
    $saroNumber = trim((string) ($_POST['saro_number'] ?? ''));
    $papName = trim((string) ($_POST['pap_name'] ?? ''));
    $objectCodeSelect = trim((string) ($_POST['object_code_name'] ?? ''));
    $customObjectCode = trim((string) ($_POST['custom_object_code_name'] ?? ''));
    $objectCodeName = $objectCodeSelect === '__custom__' ? $customObjectCode : $objectCodeSelect;
    $authorizedAppropriation = fund_monitoring_normalize_amount($_POST['authorized_appropriation'] ?? 0);
    $realignment = fund_monitoring_normalize_amount($_POST['realignment'] ?? 0);
    $reasonObligation = trim((string) ($_POST['reason_obligation'] ?? ''));
    $reasonDisbursement = trim((string) ($_POST['reason_disbursement'] ?? ''));
    $displayOrder = isset($_POST['display_order']) ? (int) $_POST['display_order'] : 0;

    $success = fund_monitoring_save_item(
        $conn,
        $selectedYear,
        $saroNumber,
        $papName,
        $objectCodeName,
        $authorizedAppropriation,
        $realignment,
        $reasonObligation,
        $reasonDisbursement,
        $displayOrder,
        $userId,
        $recordId
    );

    fund_monitoring_set_flash($success ? 'success' : 'danger', $success ? 'Fund monitoring item saved successfully.' : 'Unable to save the fund monitoring item. Please complete all required fields.');
    if ($success) {
        fund_monitoring_broadcast_change($selectedYear, 'item_saved', $userId, [
            'record_id' => $recordId,
        ]);
    }
    header('Location: ' . $redirectTarget);
    exit;
}

fund_monitoring_set_flash('danger', 'Unknown fund monitoring action.');
header('Location: ' . $redirectTarget);
exit;
