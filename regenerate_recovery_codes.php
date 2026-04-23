<?php
header('Content-Type: application/json');

require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/config.php';

security_require_method(['POST']);
security_require_csrf_token();

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    security_send_json(['success' => false, 'message' => 'Unauthorized.'], 403);
}

$stmt = $conn->prepare('SELECT id, two_fa_enabled, two_fa_secret FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = db_stmt_fetch_one_assoc($stmt);
$stmt->close();

if (!$user) {
    security_send_json(['success' => false, 'message' => 'User not found.'], 404);
}

if (empty($user['two_fa_enabled']) || trim((string) ($user['two_fa_secret'] ?? '')) === '') {
    security_send_json(['success' => false, 'message' => 'Enable and finish authenticator setup before generating recovery codes.'], 422);
}

$codes = two_factor_generate_recovery_codes();
if (!two_factor_store_recovery_codes($conn, (int) $userId, $codes)) {
    security_send_json(['success' => false, 'message' => 'Unable to regenerate recovery codes right now.'], 500);
}

$_SESSION['print_recovery_codes'] = $codes;

notification_log_audit($conn, (int) $userId, 'Security', '2FA recovery codes regenerated', security_get_client_ip());

security_send_json([
    'success' => true,
    'codes' => $codes,
    'message' => 'Recovery codes regenerated successfully.',
]);
