<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/base_url.php';
require_once __DIR__ . '/auth_helpers.php';

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    auth_redirect_to_public_landing();
}

require_once __DIR__ . '/notification_helpers.php';
require_once __DIR__ . '/app_notification_helpers.php';
require_once __DIR__ . '/app_location_helpers.php';
require_once __DIR__ . '/theme_helpers.php';

function redirect_with_login_error(string $message, bool $preserveFormValues = false): void
{
    $_SESSION['login_error'] = $message;
    if ($preserveFormValues) {
        $_SESSION['login_form_old'] = [
            'username' => (string) ($_POST['username'] ?? ''),
            'password' => (string) ($_POST['password'] ?? ''),
        ];
    } else {
        unset($_SESSION['login_form_old']);
    }
    unset($_SESSION['auth_notice']);
    header('Location: ./');
    exit;
}

function redirect_to_required_password_reset(mysqli $conn, array $user): void
{
    $resetState = password_policy_issue_reset_for_user($conn, $user, true);
    if (!$resetState || empty($resetState['token'])) {
        redirect_with_login_error('We could not prepare the required password update. Please try again or contact support.');
    }

    $_SESSION['password_policy_notice'] = [
        'icon' => 'warning',
        'title' => 'Password Update Required',
        'text' => 'Your account uses an older password that no longer meets the current KODUS security policy. Please choose a new password to continue.',
    ];

    header('Location: reset-password.php?token=' . urlencode((string) $resetState['token']) . '&enforced=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_once __DIR__ . '/config.php';

        security_require_csrf_token();

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $remember = isset($_POST['remember']);
        $rateLimitKey = 'login:' . hash('sha256', strtolower($username) . '|' . security_get_client_ip());

        if (!security_rate_limit_check($rateLimitKey, 5, 300)) {
            redirect_with_login_error('Too many login attempts. Please wait a few minutes and try again.');
        }

        $stmt = $conn->prepare('SELECT * FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1');
        if (!$stmt) {
            error_log('Login prepare failed: ' . $conn->error);
            redirect_with_login_error('Sign-in is temporarily unavailable. Please try again in a few minutes.');
        }

        $stmt->bind_param('s', $username);
        if (!$stmt->execute()) {
            error_log('Login execute failed: ' . $stmt->error);
            $stmt->close();
            redirect_with_login_error('Sign-in is temporarily unavailable. Please try again in a few minutes.');
        }

        $result = $stmt->store_result();
        if ($result === false || $stmt->num_rows !== 1) {
            $stmt->close();
            redirect_with_login_error('Invalid username or password.', true);
        }

        $resultMetadata = $stmt->result_metadata();
        if (!$resultMetadata) {
            error_log('Login result metadata unavailable for username: ' . $username);
            $stmt->close();
            redirect_with_login_error('Sign-in is temporarily unavailable. Please try again in a few minutes.');
        }

        $row = [];
        $bindValues = [];
        while ($field = $resultMetadata->fetch_field()) {
            $row[$field->name] = null;
            $bindValues[] = &$row[$field->name];
        }
        $resultMetadata->free();

        call_user_func_array([$stmt, 'bind_result'], $bindValues);
        if (!$stmt->fetch()) {
            $stmt->close();
            redirect_with_login_error('Invalid username or password.', true);
        }
        $stmt->close();

        $user = $row;
        if (!password_verify($password, $user['password'])) {
            redirect_with_login_error('Invalid username or password.', true);
        }

        $enteredPasswordIsWeak = !security_validate_password_strength($password);
        if (password_policy_needs_upgrade($user) || $enteredPasswordIsWeak) {
            security_rate_limit_reset($rateLimitKey);
            redirect_to_required_password_reset($conn, $user);
        }

        security_rate_limit_reset($rateLimitKey);
        $ip = security_get_client_ip();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $time = date('Y-m-d H:i:s');
        $location = app_describe_client_location();

        if (!empty($user['two_fa_enabled'])) {
            $_SESSION['2fa_user_id'] = $user['id'];
            $_SESSION['remember_me'] = $remember;
            $_SESSION['pending_login_password_is_weak'] = $enteredPasswordIsWeak;
            $_SERVER['pending_2fa'] = true;
            header('Location: verify-2fa');
            exit;
        }

        auth_complete_login($conn, $user);
        unset($_SESSION['pending_login_password_is_weak']);
        unset($_SESSION['login_form_old']);
        if ($remember) {
            auth_issue_remember_me_token($conn, (int) $user['id']);
        }

        $_SESSION['auth_notice'] = [
            'icon' => 'success',
            'title' => 'Login Successful',
            'text' => 'Welcome, ' . (string) $user['first_name'] . '!',
        ];

        header('Location: home');
        notification_finish_response();
        $mailResult = notification_send_login_alert($user, $ip, $time, $location);
        notification_log_mail($conn, $user['email'], $mailResult['subject'], $mailResult['status'], $mailResult['message']);
        notification_log_audit($conn, (int) $user['id'], 'Login', "Non-2FA login. IP: {$ip}, Location: {$location}, User Agent: {$userAgent}", $ip);
        if (!empty($_SESSION['is_first_login'])) {
            $fullName = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
            app_notification_create($conn, [
                'category' => 'first_login',
                'title' => 'First login detected',
                'message' => $fullName . ' signed in for the first time.',
                'url' => app_notification_build_url('admin/users_management'),
                'icon_class' => 'fas fa-sign-in-alt',
                'color_class' => 'text-success',
                'actor_user_id' => (int) $user['id'],
                'actor_name' => $fullName,
            ]);
        }
        exit;
    } catch (Throwable $e) {
        error_log('Login fatal: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        redirect_with_login_error('Sign-in is temporarily unavailable. Please try again in a few minutes.');
    }
}
?>
