<?php

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/theme_helpers.php';
require_once __DIR__ . '/role_change_helpers.php';

function auth_public_pages(): array
{
    return [
        'forgot-password.php',
        'index.php',
        'login.php',
        'register.php',
        'registration.php',
        'reset-password.php',
        'terms.php',
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
    $currentFolder = basename(dirname((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')));

    return $currentPage === 'index.php' && $currentFolder === 'kodus';
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
    $_SESSION['user_type'] = $user['userType'];
    $themePreference = $user['theme_preference'] ?? 'light';
    theme_store_session_preference($themePreference);
    theme_store_client_preference($themePreference);
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

    auth_upgrade_legacy_remember_token($conn, $user, $token);
    auth_complete_login($conn, $user);

    return true;
}

function auth_redirect_to_home(): void
{
    header('Location: home');
    exit();
}

function auth_redirect_to_login(): void
{
    $scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $redirect = str_contains($scriptPath, '/kodus/pages/') ? '../' : './';

    header("Location: {$redirect}");
    exit();
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
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('X-Permitted-Cross-Domain-Policies: none');

    if (security_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
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

    if ($logoutSuccess) {
        return ['icon' => 'success', 'title' => 'Logged Out', 'text' => 'You have successfully logged out.'];
    }

    return ['icon' => 'error', 'title' => 'Logout Completed With Issues', 'text' => 'You were signed out, but part of the cleanup failed. Please sign in again if needed.'];
}

function auth_logout_user(mysqli $conn, string $reason = 'manual'): array
{
    $logoutSuccess = true;
    $allowedReasons = ['manual', 'timeout', 'role_changed', 'deactivated'];
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
            default => 'Manual logout by user',
        };

        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("isss", $userId, $action, $details, $ip);
            $stmt->execute();
            $stmt->close();
        } else {
            $logoutSuccess = false;
            error_log('Logout prepare failed while writing audit log.');
        }
    }

    session_unset();
    session_destroy();
    security_clear_cookie('remember_token');
    security_clear_cookie(session_name());

    return auth_logout_feedback($reason, $logoutSuccess);
}
