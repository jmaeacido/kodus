<?php

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/theme_helpers.php';
require_once __DIR__ . '/role_change_helpers.php';

function auth_public_pages(): array
{
    return [
        'forgot-password.php',
        'index.php',
        'login-sso.php',
        'login.php',
        'register.php',
        'registration.php',
        'reset-password.php',
        'terms.php',
        'callback.php',
        'verify-2fa.php',
    ];
}

function auth_is_public_page(string $pageName): bool
{
    return in_array($pageName, auth_public_pages(), true);
}

function auth_is_root_index_request(): bool
{
    $currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
    $scriptDirectory = realpath(dirname((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')));
    $appDirectory = realpath(__DIR__);

    return $currentPage === 'index.php' && $scriptDirectory !== false && $appDirectory !== false && $scriptDirectory === $appDirectory;
}

function auth_store_user_session(array $user): void
{
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['middle_name'] = $user['middle_name'];
    $_SESSION['ext'] = $user['ext'];
    $_SESSION['picture'] = $user['picture'];
    $_SESSION['sso_avatar_url'] = $user['sso_avatar_url'] ?? '';
    $_SESSION['user_type'] = $user['userType'];
    $themePreference = $user['theme_preference'] ?? 'light';
    theme_store_session_preference($themePreference);
    theme_store_client_preference($themePreference);
}

function auth_current_user_type(): string
{
    return (string) ($_SESSION['user_type'] ?? 'user');
}

function auth_implementation_editor_types(): array
{
    return ['admin', 'editor'];
}

function auth_can_view_operations(): bool
{
    return auth_current_user_type() !== 'user';
}

function auth_can_manage_program_targets(): bool
{
    return in_array(auth_current_user_type(), auth_implementation_editor_types(), true);
}

function auth_can_manage_program_activities(): bool
{
    return in_array(auth_current_user_type(), auth_implementation_editor_types(), true);
}

function auth_can_manage_project_variables(): bool
{
    return in_array(auth_current_user_type(), auth_implementation_editor_types(), true);
}

function auth_admin_generator_directories(): array
{
    return [
        'mebis-consolidator',
        'mebis-lgu-template',
    ];
}

function auth_is_admin_generator_request(): bool
{
    $currentDirectory = basename(dirname((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')));
    return in_array($currentDirectory, auth_admin_generator_directories(), true);
}

function auth_ensure_login_tracking_schema(mysqli $conn): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login_at'");
    if ($result && $result->num_rows > 0) {
        return;
    }

    $conn->query("ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL DEFAULT NULL AFTER date_registered");
}

function auth_complete_login(mysqli $conn, array $user): void
{
    auth_ensure_login_tracking_schema($conn);
    $_SESSION['is_first_login'] = empty($user['last_login_at']);

    if (function_exists('two_factor_recovery_code_count') && !empty($user['two_fa_enabled'])) {
        $remainingRecoveryCodes = two_factor_recovery_code_count($user);
        if ($remainingRecoveryCodes > 0 && $remainingRecoveryCodes <= 2) {
            $_SESSION['low_recovery_codes_notice'] = [
                'remaining' => $remainingRecoveryCodes,
            ];
        } else {
            unset($_SESSION['low_recovery_codes_notice']);
        }
    }

    $userId = isset($user['id']) ? (int) $user['id'] : 0;
    if ($userId > 0) {
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare('UPDATE users SET last_login_at = ? WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('si', $now, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    session_regenerate_id(true);
    auth_store_user_session($user);
}

function auth_issue_remember_me_token(mysqli $conn, int $userId): void
{
    $token = bin2hex(random_bytes(32));
    $hashedToken = security_hash_token($token);
    security_set_remember_cookie($token);

    $stmt = $conn->prepare('UPDATE users SET remember_token = ? WHERE id = ?');
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('si', $hashedToken, $userId);
    $stmt->execute();
    $stmt->close();
}

function auth_upgrade_legacy_remember_token(mysqli $conn, array $user, string $plainToken): void
{
    $storedToken = $user['remember_token'] ?? '';
    $hashInfo = is_string($storedToken) ? password_get_info($storedToken) : ['algo' => null];

    if (!empty($hashInfo['algo'])) {
        return;
    }

    $stmt = $conn->prepare('UPDATE users SET remember_token = ? WHERE id = ?');
    if (!$stmt) {
        return;
    }

    $hashedToken = security_hash_token($plainToken);
    $stmt->bind_param('si', $hashedToken, $user['id']);
    $stmt->execute();
    $stmt->close();
}

function auth_restore_user_from_remember_me(mysqli $conn): bool
{
    $token = $_COOKIE['remember_token'] ?? null;
    if (!is_string($token) || $token === '') {
        return false;
    }

    $user = security_find_user_by_token($conn, 'remember_token', $token, 'deleted_at IS NULL');
    if (!$user) {
        security_clear_cookie('remember_token');
        return false;
    }

    if (password_policy_needs_upgrade($user)) {
        security_clear_cookie('remember_token');

        $stmt = $conn->prepare('UPDATE users SET remember_token = NULL WHERE id = ?');
        if ($stmt) {
            $userId = (int) $user['id'];
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }

        $_SESSION['login_error'] = 'Your saved sign-in was cleared because your password needs to be updated to meet the current security policy.';
        return false;
    }

    auth_upgrade_legacy_remember_token($conn, $user, $token);
    auth_complete_login($conn, $user);

    return true;
}

function auth_redirect_to_home(): void
{
    header('Location: home');
    exit();
}

function auth_relative_prefix_to_app_root(): string
{
    $scriptFilename = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $appRoot = str_replace('\\', '/', __DIR__);

    if ($scriptFilename === '' || !str_starts_with($scriptFilename, $appRoot)) {
        return './';
    }

    $relativePath = ltrim(substr($scriptFilename, strlen($appRoot)), '/');
    $relativeDirectory = trim(str_replace('\\', '/', dirname($relativePath)), '/.');

    if ($relativeDirectory === '') {
        return './';
    }

    $depth = count(array_filter(explode('/', $relativeDirectory), static fn(string $segment): bool => $segment !== ''));
    return str_repeat('../', max(1, $depth));
}

function auth_redirect_to_login(): void
{
    $redirect = auth_relative_prefix_to_app_root();

    header("Location: {$redirect}");
    exit();
}

function auth_redirect_to_public_landing(): void
{
    $redirect = auth_relative_prefix_to_app_root() . 'select_year';

    header("Location: {$redirect}");
    exit();
}

function auth_operations_pages(): array
{
    return [
        'beneficiary-profile.php',
        'data-tracking.php',
        'data-tracking-in.php',
        'data-tracking-meb-edit.php',
        'data-tracking-meb-validation.php',
        'data-tracking-meb.php',
        'data-tracking-meb_.php',
        'meb-batch-summary.php',
        'data-tracking-out.php',
        'fund-monitoring.php',
        'payout.php',
    ];
}

function auth_admin_only_pages(): array
{
    return [
        'audit_logs.php',
        'classify_users.php',
        'deactivate_users.php',
        'deactivate_user.php',
        'maintenance.php',
        'password_security.php',
        'restore_user.php',
        'restore_users.php',
        'users_management.php',
    ];
}

function auth_is_operations_page_request(): bool
{
    $currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
    return in_array($currentPage, auth_operations_pages(), true);
}

function auth_is_admin_only_page_request(): bool
{
    $currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
    $currentDirectory = basename(dirname((string) ($_SERVER['PHP_SELF'] ?? '')));

    return $currentDirectory === 'admin' && in_array($currentPage, auth_admin_only_pages(), true);
}

function auth_is_safe_internal_url(string $url): bool
{
    if ($url === '') {
        return false;
    }

    $target = parse_url($url);
    if ($target === false) {
        return false;
    }

    $targetHost = strtolower((string) ($target['host'] ?? ''));
    $currentHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($targetHost !== '' && $currentHost !== '' && $targetHost !== $currentHost) {
        return false;
    }

    return true;
}

function auth_build_internal_redirect(string $path): string
{
    $relativePath = ltrim($path, '/');
    $prefix = auth_relative_prefix_to_app_root();

    return $prefix === './' ? $relativePath : $prefix . $relativePath;
}

function auth_redirect_to_previous_page_or_home(): void
{
    $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if (auth_is_safe_internal_url($referer)) {
        $refererPath = basename((string) parse_url($referer, PHP_URL_PATH));
        if (!in_array($refererPath, auth_operations_pages(), true)) {
            header('Location: ' . $referer);
            exit();
        }
    }

    header('Location: ' . auth_build_internal_redirect('home'));
    exit();
}

function auth_redirect_to_previous_page_or_home_excluding(array $restrictedPages = [], array $restrictedDirectories = []): void
{
    $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if (auth_is_safe_internal_url($referer)) {
        $refererUrlPath = (string) parse_url($referer, PHP_URL_PATH);
        $refererPath = basename($refererUrlPath);
        $refererDirectory = basename(dirname($refererUrlPath));

        if (!in_array($refererPath, $restrictedPages, true) && !in_array($refererDirectory, $restrictedDirectories, true)) {
            header('Location: ' . $referer);
            exit();
        }
    }

    header('Location: ' . auth_build_internal_redirect('home'));
    exit();
}

function auth_enforce_operations_access(?mysqli $conn = null): void
{
    if (auth_is_operations_page_request() && !auth_can_view_operations()) {
        if (function_exists('kodus_abort')) {
            kodus_abort($conn, 403, [
                'detail' => 'Your current role does not include access to the operations workspace.',
                'popup' => true,
                'redirect' => auth_build_internal_redirect('home'),
            ]);
        }
        auth_redirect_to_previous_page_or_home();
    }
}

function auth_enforce_admin_page_access(?mysqli $conn = null): void
{
    if (!auth_is_admin_only_page_request()) {
        return;
    }

    if (auth_current_user_type() !== 'admin') {
        if (function_exists('kodus_abort')) {
            kodus_abort($conn, 403, [
                'detail' => 'Administrator privileges are required to open this Administration tool.',
                'popup' => true,
                'redirect' => auth_build_internal_redirect('home'),
            ]);
        }
        auth_redirect_to_previous_page_or_home();
    }
}

function auth_enforce_admin_generator_access(?mysqli $conn = null): void
{
    if (!auth_is_admin_generator_request()) {
        return;
    }

    if (!isset($_SESSION['user_id'])) {
        if (function_exists('kodus_abort')) {
            kodus_abort($conn, 401, [
                'detail' => 'Please sign in before opening this generator.',
                'popup' => true,
                'redirect' => auth_relative_prefix_to_app_root(),
            ]);
        }
        auth_redirect_to_login();
    }

    if (auth_current_user_type() !== 'admin') {
        if (function_exists('kodus_abort')) {
            kodus_abort($conn, 403, [
                'detail' => 'Administrator privileges are required to open this generator.',
                'popup' => true,
                'redirect' => auth_build_internal_redirect('home'),
            ]);
        }
        auth_redirect_to_previous_page_or_home_excluding([], auth_admin_generator_directories());
    }
}

function auth_enforce_session_timeout(string $logoutUrl, int $timeoutSeconds = 3600): void
{
    $lastActivity = $_SESSION['last_activity'] ?? null;
    $hasTimedOut = is_int($lastActivity) && (time() - $lastActivity > $timeoutSeconds);

    if ($hasTimedOut) {
        $token = rawurlencode(security_get_csrf_token());
        header("Location: {$logoutUrl}?reason=timeout&token={$token}");
        exit();
    }

    $_SESSION['last_activity'] = time();
}

function auth_handle_page_access(mysqli $conn): void
{
    $currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));

    if (auth_is_public_page($currentPage)) {
        if (isset($_SESSION['user_id']) && auth_is_root_index_request()) {
            auth_redirect_to_home();
        }
        return;
    }

    if (!isset($_SESSION['user_id']) && auth_restore_user_from_remember_me($conn)) {
        auth_redirect_to_home();
    }

    if (!isset($_SESSION['user_id'])) {
        if (function_exists('kodus_abort')) {
            kodus_abort($conn, 401, [
                'detail' => 'Please sign in to continue in KODUS.',
                'popup' => true,
                'redirect' => auth_relative_prefix_to_app_root(),
            ]);
        }
        auth_redirect_to_login();
    }
}

function auth_mark_user_online(mysqli $conn): void
{
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_numeric($userId)) {
        return;
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare('UPDATE users SET last_activity = ?, is_online = 1 WHERE id = ?');
    if (!$stmt) {
        return;
    }

    $userId = (int) $userId;
    $stmt->bind_param('si', $now, $userId);
    $stmt->execute();
    $stmt->close();
}

function auth_apply_security_headers(): void
{
    security_apply_response_headers();
}

function auth_logout_feedback(string $reason, bool $logoutSuccess): array
{
    if ($reason === 'timeout') {
        return ['icon' => 'warning', 'title' => 'Session Expired', 'text' => 'You were logged out due to 1 hour of inactivity.'];
    }

    if ($reason === 'role_changed') {
        return ['icon' => 'info', 'title' => 'Role Updated', 'text' => 'You were signed out so your updated role can take effect.'];
    }

    if ($reason === 'deactivated') {
        return ['icon' => 'warning', 'title' => 'Account Deactivated', 'text' => 'Your account has been deactivated. Please contact your administrator if you think this is a mistake.'];
    }

    if ($reason === 'maintenance') {
        return ['icon' => 'info', 'title' => 'Maintenance Mode Enabled', 'text' => 'KODUS is temporarily unavailable while maintenance is in progress. Please try again later.'];
    }

    if ($logoutSuccess) {
        return ['icon' => 'success', 'title' => 'Logged Out', 'text' => 'You have successfully logged out.'];
    }

    return ['icon' => 'error', 'title' => 'Logout Completed With Issues', 'text' => 'You were signed out, but part of the cleanup failed. Please sign in again if needed.'];
}

function auth_logout_user(mysqli $conn, string $reason = 'manual'): array
{
    $logoutSuccess = true;
    $allowedReasons = ['manual', 'timeout', 'role_changed', 'deactivated', 'maintenance'];
    if (!in_array($reason, $allowedReasons, true)) {
        $reason = 'manual';
    }

    if (isset($_SESSION['user_id'])) {
        $userId = (int) $_SESSION['user_id'];
        role_change_clear($conn, $userId);

        $stmt = $conn->prepare("UPDATE users SET is_online = 0, remember_token = NULL, two_fa_code = NULL, two_fa_code_expiry = NULL WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
        } else {
            $logoutSuccess = false;
            error_log('Logout prepare failed while clearing user session state.');
        }

        $ip = security_get_client_ip();
        $action = 'Logout';
        $details = match ($reason) {
            'timeout' => 'Auto-logout due to inactivity',
            'role_changed' => 'Forced logout after role update',
            'deactivated' => 'Forced logout after account deactivation',
            'maintenance' => 'Forced logout because maintenance mode is active',
            default => 'Manual logout by user',
        };

        audit_log($conn, $userId, $action, $details, $ip);
    }

    if (function_exists('two_factor_clear_pending_secret')) {
        two_factor_clear_pending_secret();
    }

    session_unset();
    session_destroy();
    security_clear_cookie('remember_token');
    security_clear_cookie(session_name());

    return auth_logout_feedback($reason, $logoutSuccess);
}
