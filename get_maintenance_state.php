<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helpers.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
header('Content-Type: application/json');

if (($_SESSION['user_type'] ?? '') === 'admin') {
    echo json_encode(['active' => false]);
    exit;
}

$state = kodus_maintenance_state($conn);
if (($state['phase'] ?? 'inactive') !== 'pending') {
    echo json_encode([
        'active' => false,
        'phase' => $state['phase'] ?? 'inactive',
    ]);
    exit;
}

echo json_encode([
    'active' => true,
    'phase' => 'pending',
    'message' => (string) ($state['warning_message'] ?? ''),
    'effective_at' => $state['effective_at'] ?? null,
    'seconds_remaining' => (int) ($state['seconds_remaining'] ?? 0),
]);
