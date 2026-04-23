<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../notification_helpers.php';
require_once __DIR__ . '/../sso_helpers.php';

if (!sso_is_configured()) {
    sso_handle_callback_error('Caraga Connect SSO is not configured for this KODUS instance.');
}

try {
    $request = sso_validate_callback_request();
    $tokens = sso_exchange_code_for_tokens($request['code']);
    $accessToken = trim((string) ($tokens['access_token'] ?? ''));
    if ($accessToken === '') {
        throw new RuntimeException('Caraga Connect did not return an access token.');
    }

    $profile = sso_fetch_userinfo($accessToken);
    $user = sso_create_or_update_user($conn, $profile);

    $_SESSION['is_sso_authenticated'] = true;
    $_SESSION['idp_access_token'] = $accessToken;
    $_SESSION['idp_refresh_token'] = $tokens['refresh_token'] ?? null;
    $_SESSION['idp_expires_in'] = $tokens['expires_in'] ?? null;

    if (!empty($user['two_fa_enabled'])) {
        $_SESSION['2fa_user_id'] = $user['id'];
        header('Location: ../verify-2fa');
        exit;
    }

    auth_complete_login($conn, $user);

    $_SESSION['auth_notice'] = [
        'icon' => 'success',
        'title' => 'Login Successful',
        'text' => 'Welcome, ' . (string) ($user['first_name'] ?? 'User') . '!',
    ];

    $ip = security_get_client_ip();
    notification_log_audit($conn, (int) $user['id'], 'Login', 'SSO login via Caraga Connect. IP: ' . $ip, $ip);

    header('Location: ../home');
    exit;
} catch (Throwable $e) {
    error_log('SSO callback failed: ' . $e->getMessage());
    sso_handle_callback_error($e->getMessage());
}
