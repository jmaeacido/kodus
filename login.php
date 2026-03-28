<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/base_url.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/notification_helpers.php';
require_once __DIR__ . '/theme_helpers.php';

function redirect_with_login_error(string $message): void
{
    $_SESSION['login_error'] = $message;
    header('Location: ./');
    exit;
}

$send2FA = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    security_require_csrf_token();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);
    $rateLimitKey = 'login:' . hash('sha256', strtolower($username) . '|' . security_get_client_ip());

    if (!security_rate_limit_check($rateLimitKey, 5, 300)) {
        redirect_with_login_error('Too many login attempts. Please wait a few minutes and try again.');
    }

    $stmt = $conn->prepare('SELECT * FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        redirect_with_login_error('Invalid username or password.');
    }

    $user = $result->fetch_assoc();
    if (!password_verify($password, $user['password'])) {
        redirect_with_login_error('Invalid username or password.');
    }

    security_rate_limit_reset($rateLimitKey);
    $ip = security_get_client_ip();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $time = date('Y-m-d H:i:s');
    $location = 'Unavailable';

    if (!empty($user['two_fa_enabled'])) {
        $_SESSION['2fa_user_id'] = $user['id'];
        $_SESSION['remember_me'] = $remember;
        $_SERVER['pending_2fa'] = true;
        header('Location: verify-2fa');
        exit;
    } else {
        auth_complete_login($conn, $user);
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
        exit;
    }
}
?>
