<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../socket_helpers.php';

security_require_method(['POST']);
security_require_csrf_token();

if (($_SESSION['user_type'] ?? '') !== 'admin') {
    kodus_abort($conn, 403, [
        'detail' => 'Administrator privileges are required to update maintenance mode.',
        'popup' => true,
        'redirect' => '../home',
    ]);
}

$userId = isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$maintenanceEnabled = isset($_POST['maintenance_enabled']) ? '1' : '0';
$maintenanceMessage = trim((string) ($_POST['maintenance_message'] ?? ''));
$warningMessage = trim((string) ($_POST['maintenance_warning_message'] ?? ''));
$warningSeconds = (int) ($_POST['maintenance_warning_seconds'] ?? 300);
$warningSeconds = max(0, min($warningSeconds, 7200));
$redirectSeconds = (int) ($_POST['maintenance_redirect_seconds'] ?? 12);
$redirectSeconds = max(0, min($redirectSeconds, 60));
$effectiveAt = '';

if ($maintenanceMessage === '') {
    kodus_queue_popup([
        'icon' => 'error',
        'title' => 'Maintenance Not Saved',
        'text' => 'Please provide a maintenance message for the 503 page.',
    ]);
    header('Location: maintenance.php');
    exit;
}

if ($warningMessage === '') {
    $warningMessage = 'Scheduled maintenance will begin soon. Please save or complete your current work before the countdown ends.';
}

if ($maintenanceEnabled === '1') {
    $effectiveAt = date('Y-m-d H:i:s', time() + $warningSeconds);
}

$saved = kodus_setting_set($conn, 'maintenance_enabled', $maintenanceEnabled, $userId)
    && kodus_setting_set($conn, 'maintenance_message', $maintenanceMessage, $userId)
    && kodus_setting_set($conn, 'maintenance_warning_message', $warningMessage, $userId)
    && kodus_setting_set($conn, 'maintenance_warning_seconds', (string) $warningSeconds, $userId)
    && kodus_setting_set($conn, 'maintenance_effective_at', $effectiveAt, $userId)
    && kodus_setting_set($conn, 'maintenance_redirect_seconds', (string) $redirectSeconds, $userId);

if ($saved) {
    $maintenanceState = kodus_maintenance_state($conn);
    kodus_socket_broadcast('kodus.session', 'maintenance.changed', [
        'state' => [
            'active' => ($maintenanceState['phase'] ?? 'inactive') === 'pending',
            'phase' => $maintenanceState['phase'] ?? 'inactive',
            'message' => (string) ($maintenanceState['warning_message'] ?? ''),
            'effective_at' => $maintenanceState['effective_at'] ?? null,
            'seconds_remaining' => (int) ($maintenanceState['seconds_remaining'] ?? 0),
        ],
    ]);

    $stateLabel = $maintenanceEnabled === '1' ? 'enabled' : 'disabled';
    audit_log(
        $conn,
        $userId,
        'Maintenance Mode Updated',
        'Maintenance mode ' . $stateLabel . '. Warning window: ' . $warningSeconds . 's. Redirect: ' . $redirectSeconds . 's. Message: ' . $maintenanceMessage,
        security_get_client_ip()
    );

    kodus_queue_popup([
        'icon' => 'success',
        'title' => 'Maintenance Settings Saved',
        'text' => 'KODUS maintenance mode was updated successfully.',
    ]);
} else {
    kodus_queue_popup([
        'icon' => 'error',
        'title' => 'Maintenance Save Failed',
        'text' => 'KODUS could not save the maintenance settings right now.',
    ]);
}

header('Location: maintenance.php');
exit;
