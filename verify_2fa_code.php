<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/notification_helpers.php';
require_once __DIR__ . '/app_notification_helpers.php';
include('config.php');

security_require_method(['POST']);
security_require_csrf_token();

$login2faUserId = $_SESSION['2fa_user_id'] ?? null;
$sessionUserId = $_SESSION['user_id'] ?? null;
$userId = $login2faUserId ?? $sessionUserId;
$pendingLoginPasswordIsWeak = !empty($_SESSION['pending_login_password_is_weak']);
$mode = strtolower(trim((string) ($_POST['mode'] ?? 'verify')));

if (!$userId) {
    security_send_json(['success' => false, 'message' => 'Unauthorized.'], 403);
}

$enteredCode = two_factor_normalize_code((string) ($_POST['code'] ?? ''));
if ($enteredCode === '') {
    security_send_json(['success' => false, 'message' => 'No code entered.'], 422);
}

$rateLimitKey = '2fa-verify:' . $userId;
if (!security_rate_limit_check($rateLimitKey, 5, 300)) {
    security_send_json(['success' => false, 'message' => 'Too many invalid attempts. Please wait and try again.'], 429);
}

$stmt = $conn->prepare('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = db_stmt_fetch_one_assoc($stmt);
$stmt->close();

if (!$user) {
    security_send_json(['success' => false, 'message' => 'User not found.'], 404);
}

$storedSecret = trim((string) ($user['two_fa_secret'] ?? ''));
$pendingSecret = two_factor_get_pending_secret();
$pendingRecoveryCodes = $_SESSION['pending_2fa_recovery_codes'] ?? null;
$isSetupMode = $mode === 'setup';
$verified = false;
$alreadyEnabled = !empty($user['two_fa_enabled']) && $storedSecret !== '';
$verifiedWithStoredSecret = false;
$usedRecoveryCode = false;
$generatedRecoveryCodes = null;

if ($isSetupMode) {
    if ($pendingSecret === null) {
        security_send_json(['success' => false, 'message' => 'No pending authenticator setup was found. Please start setup again.'], 409);
    }
    if (!is_array($pendingRecoveryCodes) || $pendingRecoveryCodes === []) {
        security_send_json(['success' => false, 'message' => 'No pending recovery codes were found. Please start setup again.'], 409);
    }

    if (!two_factor_verify_totp_code($pendingSecret, $enteredCode)) {
        security_send_json(['success' => false, 'message' => 'Invalid authenticator code.'], 422);
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare(
        'UPDATE users
         SET two_fa_enabled = 1, two_fa_secret = ?, two_fa_confirmed_at = ?, two_fa_code = NULL, two_fa_code_expiry = NULL
         WHERE id = ?'
    );
    $stmt->bind_param('ssi', $pendingSecret, $now, $userId);
    $stmt->execute();
    $stmt->close();
    two_factor_store_recovery_codes($conn, $userId, $pendingRecoveryCodes);

    $user['two_fa_enabled'] = 1;
    $user['two_fa_secret'] = $pendingSecret;
    $user['two_fa_confirmed_at'] = $now;
    $generatedRecoveryCodes = $pendingRecoveryCodes;
    $_SESSION['print_recovery_codes'] = $pendingRecoveryCodes;
    $verified = true;
    two_factor_clear_pending_secret();
    unset($_SESSION['pending_2fa_recovery_codes']);
} elseif ($storedSecret !== '') {
    if (two_factor_verify_totp_code($storedSecret, $enteredCode)) {
        $verified = true;
        $verifiedWithStoredSecret = true;
    } elseif (two_factor_consume_recovery_code($conn, $user, $enteredCode)) {
        $verified = true;
        $usedRecoveryCode = true;
        $user['two_fa_recovery_codes'] = json_encode(array_values(array_filter(
            two_factor_parse_recovery_code_hashes($user),
            static fn(string $hash): bool => !hash_equals($hash, two_factor_hash_recovery_code($enteredCode))
        )));
    } else {
        security_send_json(['success' => false, 'message' => 'Invalid authenticator or recovery code.'], 422);
    }
} else {
    security_send_json(['success' => false, 'message' => 'Authenticator setup is required before verification can continue.'], 409);
}

if (!$verified) {
    security_send_json(['success' => false, 'message' => 'Unable to verify the authenticator code.'], 422);
}

if ($login2faUserId !== null) {
    if ($pendingLoginPasswordIsWeak || password_policy_needs_upgrade($user)) {
        $resetState = password_policy_issue_reset_for_user($conn, $user, true);
        unset($_SESSION['2fa_user_id'], $_SESSION['remember_me'], $_SESSION['pending_login_password_is_weak']);
        two_factor_clear_pending_secret();
        unset($_SESSION['pending_2fa_recovery_codes']);
        security_rate_limit_reset($rateLimitKey);

        if (!$resetState || empty($resetState['token'])) {
            security_send_json(['success' => false, 'message' => 'Your password must be updated before you can continue, but the reset flow could not be prepared.'], 500);
        }

        $_SESSION['password_policy_notice'] = [
            'icon' => 'warning',
            'title' => 'Password Update Required',
            'text' => 'Your password does not meet the current KODUS security policy. Please choose a new password to continue.',
        ];

        security_send_json([
            'success' => false,
            'requires_password_reset' => true,
            'redirect' => 'reset-password.php?token=' . urlencode((string) $resetState['token']) . '&enforced=1',
            'message' => 'Password update required.',
        ], 403);
    }

    auth_complete_login($conn, $user);
}

unset($_SESSION['pending_login_password_is_weak']);
unset($_SESSION['pending_2fa_recovery_codes']);
if ($login2faUserId !== null) {
    unset($_SESSION['2fa_user_id']);
}
security_rate_limit_reset($rateLimitKey);

if ($login2faUserId !== null && !empty($_SESSION['remember_me'])) {
    auth_issue_remember_me_token($conn, (int) $user['id']);
    unset($_SESSION['remember_me']);
}

$ip = security_get_client_ip();
$time = date('Y-m-d H:i:s');
$mailResult = notification_send_two_factor_alert($user, $ip, $time, $verifiedWithStoredSecret);
notification_log_mail($conn, $user['email'], $mailResult['subject'], $mailResult['status'], $mailResult['message']);

if ($login2faUserId !== null) {
    notification_log_audit($conn, (int) $user['id'], 'Login', $isSetupMode ? '2FA setup completed during login' : ($usedRecoveryCode ? '2FA verified login using recovery code' : '2FA verified login'), $ip);
    if (!empty($_SESSION['is_first_login'])) {
        $fullName = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
        app_notification_create($conn, [
            'category' => 'first_login',
            'title' => $isSetupMode ? 'First login completed with 2FA setup' : 'First login detected',
            'message' => $fullName . ($isSetupMode ? ' enrolled an authenticator app and completed first login.' : ' completed a first login with 2FA.'),
            'url' => app_notification_build_url('admin/users_management'),
            'icon_class' => 'fas fa-user-shield',
            'color_class' => 'text-success',
            'actor_user_id' => (int) $user['id'],
            'actor_name' => $fullName,
        ]);
    }

    $_SESSION['auth_notice'] = [
        'icon' => 'success',
        'title' => $isSetupMode ? 'Authenticator Setup Complete' : 'Login Successful',
        'text' => $usedRecoveryCode
            ? 'Welcome back. Your recovery code was accepted and your workspace is ready.'
            : 'Welcome, ' . (string) ($user['first_name'] ?? 'User') . '!',
    ];

    security_send_json([
        'success' => true,
        'message' => $isSetupMode ? 'Authenticator setup complete.' : '2FA verified.',
        'recovery_codes' => $generatedRecoveryCodes,
        'used_recovery_code' => $usedRecoveryCode,
        'remaining_recovery_codes' => two_factor_recovery_code_count($user),
    ]);
}

notification_log_audit($conn, (int) $user['id'], 'Security', $isSetupMode ? 'Authenticator-based 2FA enabled from settings' : ($usedRecoveryCode ? '2FA verified from settings using recovery code' : '2FA verified from settings'), $ip);
security_send_json([
    'success' => true,
    'message' => $isSetupMode ? 'Two-factor authentication enabled.' : 'Two-factor authentication verified.',
    'recovery_codes' => $generatedRecoveryCodes,
    'used_recovery_code' => $usedRecoveryCode,
    'remaining_recovery_codes' => two_factor_recovery_code_count($user),
]);
