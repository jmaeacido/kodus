<?php

function security_configure_runtime_for_web(): void
{
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
}

function security_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($forwardedProto === 'https') {
        return true;
    }

    $requestScheme = strtolower(trim((string) ($_SERVER['REQUEST_SCHEME'] ?? '')));
    if ($requestScheme === 'https') {
        return true;
    }

    return (($_SERVER['SERVER_PORT'] ?? null) === '443');
}

function security_is_local_host(string $host): bool
{
    $host = strtolower(trim($host));

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function security_base_cookie_options(): array
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return [
        'path' => '/',
        'secure' => security_is_https() && !security_is_local_host($host),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function security_bootstrap_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        $options = security_base_cookie_options();
        $options['lifetime'] = 0;
        session_set_cookie_params($options);
        session_start();
    }
}

function security_send_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload);
    exit;
}

function security_require_method(array $allowedMethods): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, $allowedMethods, true)) {
        http_response_code(405);
        header('Allow: ' . implode(', ', $allowedMethods));
        exit('Method not allowed.');
    }
}

function security_get_csrf_token(): string
{
    security_bootstrap_session();

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function security_validate_csrf_token(?string $token): bool
{
    security_bootstrap_session();
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    return is_string($token) && is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);
}

function security_require_csrf_token(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    if (!security_validate_csrf_token(is_string($token) ? $token : null)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

function security_get_client_ip(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'UNKNOWN';
}

function security_rate_limit_check(string $key, int $maxAttempts, int $windowSeconds): bool
{
    security_bootstrap_session();

    $now = time();
    $entries = $_SESSION['security_rate_limits'][$key] ?? [];
    if (!is_array($entries)) {
        $entries = [];
    }

    $entries = array_values(array_filter($entries, static function ($timestamp) use ($now, $windowSeconds) {
        return is_int($timestamp) && ($timestamp > ($now - $windowSeconds));
    }));

    if (count($entries) >= $maxAttempts) {
        $_SESSION['security_rate_limits'][$key] = $entries;
        return false;
    }

    $entries[] = $now;
    $_SESSION['security_rate_limits'][$key] = $entries;
    return true;
}

function security_rate_limit_reset(string $key): void
{
    security_bootstrap_session();
    unset($_SESSION['security_rate_limits'][$key]);
}

function security_validate_password_strength(string $password): bool
{
    if (strlen($password) < 8) {
        return false;
    }

    return preg_match('/[a-z]/', $password)
        && preg_match('/[A-Z]/', $password)
        && preg_match('/\d/', $password)
        && preg_match('/[^a-zA-Z0-9]/', $password);
}

function security_clear_cookie(string $name): void
{
    $options = security_base_cookie_options();
    $options['expires'] = time() - 3600;
    setcookie($name, '', $options);
}

function security_origin_matches_host(string $url): bool
{
    $originHost = parse_url($url, PHP_URL_HOST);
    $requestHost = $_SERVER['HTTP_HOST'] ?? '';

    if (!$originHost || !$requestHost) {
        return false;
    }

    return strcasecmp($originHost, $requestHost) === 0;
}

function security_enforce_same_origin(): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return;
    }

    $fetchSite = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
    if ($fetchSite === 'cross-site') {
        http_response_code(403);
        exit('Cross-site request blocked.');
    }

    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '' && !security_origin_matches_host($origin)) {
        http_response_code(403);
        exit('Invalid request origin.');
    }

    $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if ($origin === '' && $referer !== '' && !security_origin_matches_host($referer)) {
        http_response_code(403);
        exit('Invalid request referer.');
    }
}

function security_hash_token(string $token): string
{
    return password_hash($token, PASSWORD_DEFAULT);
}

function security_verify_stored_token(string $plainToken, ?string $storedToken): bool
{
    if (!$storedToken) {
        return false;
    }

    if (hash_equals($storedToken, $plainToken)) {
        return true;
    }

    return password_verify($plainToken, $storedToken);
}

function security_find_user_by_token(mysqli $conn, string $column, string $token, string $extraWhere = ''): ?array
{
    $allowedColumns = ['remember_token', 'reset_token'];
    if (!in_array($column, $allowedColumns, true)) {
        return null;
    }

    $sql = "SELECT * FROM users WHERE {$column} IS NOT NULL";
    if ($extraWhere !== '') {
        $sql .= " AND {$extraWhere}";
    }

    $result = $conn->query($sql);
    if (!$result) {
        return null;
    }

    while ($row = $result->fetch_assoc()) {
        if (security_verify_stored_token($token, $row[$column] ?? null)) {
            return $row;
        }
    }

    return null;
}

function security_set_remember_cookie(string $token): void
{
    $options = security_base_cookie_options();
    $options['expires'] = time() + (86400 * 30);
    setcookie('remember_token', $token, $options);
}

function security_detect_upload_mime(string $path): string
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        return 'application/octet-stream';
    }

    $mime = finfo_file($finfo, $path) ?: 'application/octet-stream';
    finfo_close($finfo);

    return $mime;
}
