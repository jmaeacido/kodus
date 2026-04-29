<?php

require_once __DIR__ . '/env_helpers.php';

function security_set_content_security_policy(?string $policy): void
{
    $GLOBALS['kodus_content_security_policy_override'] = $policy;
}

function security_get_content_security_policy_override(): ?string
{
    $policy = $GLOBALS['kodus_content_security_policy_override'] ?? null;
    return is_string($policy) && trim($policy) !== '' ? trim($policy) : null;
}

function security_compile_content_security_policy(array $directives): string
{
    if (security_is_https()) {
        $directives[] = 'upgrade-insecure-requests';
    }

    return implode('; ', $directives);
}

function security_url_origin(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    if (!isset($parts['scheme'], $parts['host'])) {
        return '';
    }

    $origin = strtolower((string) $parts['scheme']) . '://' . strtolower((string) $parts['host']);
    if (!empty($parts['port'])) {
        $origin .= ':' . (int) $parts['port'];
    }

    return $origin;
}

function security_websocket_origin(string $origin): string
{
    if (str_starts_with($origin, 'https://')) {
        return 'wss://' . substr($origin, 8);
    }

    if (str_starts_with($origin, 'http://')) {
        return 'ws://' . substr($origin, 7);
    }

    return '';
}

function security_socket_csp_sources(): array
{
    $serverOrigin = security_url_origin(app_env('KODUS_SOCKET_SERVER_URL', ''));
    $clientScriptOrigin = security_url_origin(app_env('KODUS_SOCKET_CLIENT_SCRIPT_URL', $serverOrigin !== '' ? $serverOrigin . '/socket.io/socket.io.js' : ''));

    $scriptSources = array_values(array_unique(array_filter([
        'https://caraga-connect.dswd.gov.ph',
        $clientScriptOrigin,
    ])));

    $connectSources = array_values(array_unique(array_filter([
        'https://caraga-connect.dswd.gov.ph',
        $serverOrigin,
        $serverOrigin !== '' ? security_websocket_origin($serverOrigin) : '',
    ])));

    return [
        'script' => $scriptSources,
        'connect' => $connectSources,
    ];
}

function security_configure_runtime_for_web(): void
{
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('expose_php', '0');
}

function security_request_host(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    if ($host === '') {
        return '';
    }

    return strtolower((string) preg_replace('/:\d+\z/', '', $host));
}

function security_is_http_context(): bool
{
    return PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg';
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

    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return true;
    }

    return str_ends_with($host, '.test') || str_ends_with($host, '.local');
}

function security_should_enforce_https(): bool
{
    $host = security_request_host();
    if ($host === '' || security_is_local_host($host)) {
        return false;
    }

    $configuredUrl = app_env('APP_URL', '');
    if (is_string($configuredUrl) && preg_match('#^https://#i', trim($configuredUrl))) {
        return true;
    }

    $aliasList = app_env('APP_URL_ALIASES', '') ?? '';
    if ($aliasList !== '') {
        foreach (preg_split('/[\r\n,]+/', $aliasList) as $aliasUrl) {
            $aliasUrl = trim((string) $aliasUrl);
            if ($aliasUrl !== '' && preg_match('#^https://#i', $aliasUrl)) {
                return true;
            }
        }
    }

    return security_is_https();
}

function security_build_current_url(?string $scheme = null): string
{
    $targetScheme = $scheme ?? (security_is_https() ? 'https' : 'http');
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

    return $targetScheme . '://' . $host . $requestUri;
}

function security_enforce_https(): void
{
    if (!security_is_http_context()) {
        return;
    }

    if (security_is_https() || !security_should_enforce_https()) {
        return;
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $statusCode = in_array($method, ['GET', 'HEAD'], true) ? 301 : 307;
    header('Location: ' . security_build_current_url('https'), true, $statusCode);
    exit;
}

function security_content_security_policy(): string
{
    $overridePolicy = security_get_content_security_policy_override();
    if ($overridePolicy !== null) {
        return $overridePolicy;
    }

    $socketSources = security_socket_csp_sources();
    $scriptSources = implode(' ', $socketSources['script']);
    $connectSources = implode(' ', $socketSources['connect']);

    $directives = [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
        "object-src 'none'",
        "img-src 'self' data: blob:",
        "font-src 'self' data:",
        "style-src 'self' 'unsafe-inline'",
        trim("script-src 'self' 'unsafe-inline' 'unsafe-eval' " . $scriptSources),
        trim("connect-src 'self' " . $connectSources),
        "frame-src 'self'",
        "media-src 'self' data: blob:",
        "worker-src 'self' blob:",
    ];
    return security_compile_content_security_policy($directives);
}

function security_apply_response_headers(): void
{
    static $applied = false;

    if ($applied || !security_is_http_context()) {
        return;
    }

    $applied = true;

    header_remove('X-Powered-By');
    @header_remove('Server');

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(self), microphone=(), camera=()');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Content-Security-Policy: ' . security_content_security_policy());

    if (security_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
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
    security_configure_runtime_for_web();
    security_enforce_https();
    security_apply_response_headers();

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
        header('Allow: ' . implode(', ', $allowedMethods));
        if (function_exists('kodus_abort')) {
            kodus_abort($GLOBALS['conn'] ?? null, 405, [
                'detail' => 'Allowed methods: ' . implode(', ', $allowedMethods),
                'redirect' => '',
            ]);
        }
        http_response_code(405);
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

function security_csrf_input(string $fieldName = 'csrf_token'): string
{
    $fieldName = trim($fieldName);
    if ($fieldName === '') {
        $fieldName = 'csrf_token';
    }

    $token = htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8');

    return '<input type="hidden" name="' . $name . '" value="' . $token . '">';
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
        if (function_exists('kodus_abort')) {
            kodus_abort($GLOBALS['conn'] ?? null, 400, [
                'detail' => 'The request security token is invalid or has expired.',
                'redirect' => '',
            ]);
        }
        http_response_code(400);
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

function security_password_min_length(): int
{
    return 8;
}

function security_validate_password_strength(string $password): bool
{
    if (strlen($password) < security_password_min_length()) {
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
        if (function_exists('kodus_abort')) {
            kodus_abort($GLOBALS['conn'] ?? null, 403, [
                'detail' => 'Cross-site request blocked by origin policy.',
                'redirect' => '',
            ]);
        }
        http_response_code(403);
        exit('Cross-site request blocked.');
    }

    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '' && !security_origin_matches_host($origin)) {
        if (function_exists('kodus_abort')) {
            kodus_abort($GLOBALS['conn'] ?? null, 403, [
                'detail' => 'Request origin does not match the current host.',
                'redirect' => '',
            ]);
        }
        http_response_code(403);
        exit('Invalid request origin.');
    }

    $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if ($origin === '' && $referer !== '' && !security_origin_matches_host($referer)) {
        if (function_exists('kodus_abort')) {
            kodus_abort($GLOBALS['conn'] ?? null, 403, [
                'detail' => 'Request referer does not match the current host.',
                'redirect' => '',
            ]);
        }
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
