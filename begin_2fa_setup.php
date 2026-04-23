<?php
header('Content-Type: application/json');

require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/config.php';

security_require_method(['POST']);
security_require_csrf_token();

$userId = $_SESSION['user_id'] ?? $_SESSION['2fa_user_id'] ?? null;
if (!$userId) {
    security_send_json(['success' => false, 'message' => 'Unauthorized.'], 403);
}

$stmt = $conn->prepare('SELECT id, username, email, first_name, last_name FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
if (!$stmt) {
    security_send_json(['success' => false, 'message' => 'Unable to prepare 2FA setup right now.'], 500);
}

$stmt->bind_param('i', $userId);
$stmt->execute();
$user = db_stmt_fetch_one_assoc($stmt);
$stmt->close();

if (!$user) {
    security_send_json(['success' => false, 'message' => 'User not found.'], 404);
}

$secret = two_factor_generate_secret();
two_factor_store_pending_secret($secret);
$recoveryCodes = two_factor_generate_recovery_codes();
$_SESSION['pending_2fa_recovery_codes'] = $recoveryCodes;

security_send_json([
    'success' => true,
    'qr_code' => two_factor_get_qr_svg_data_uri($user, $secret),
    'secret' => $secret,
    'recovery_codes' => $recoveryCodes,
    'issuer' => two_factor_issuer_name(),
    'account' => two_factor_user_label($user),
    'message' => 'Scan the QR code with your authenticator app, then enter the current 6-digit code.',
]);
