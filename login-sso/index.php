<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../sso_helpers.php';

if (isset($_SESSION['user_id'])) {
    auth_redirect_to_home();
}

if (!sso_is_configured()) {
    $_SESSION['login_error'] = 'Caraga Connect SSO is not configured for this KODUS instance.';
    header('Location: ../');
    exit;
}

try {
    header('Location: ' . sso_build_authorize_redirect());
    exit;
} catch (Throwable $e) {
    error_log('SSO authorize redirect failed: ' . $e->getMessage());
    $_SESSION['login_error'] = 'Unable to start the Caraga Connect sign-in flow right now.';
    header('Location: ../');
    exit;
}
