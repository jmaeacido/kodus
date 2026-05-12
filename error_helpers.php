<?php

require_once __DIR__ . '/security.php';

function kodus_error_catalog(): array
{
    return [
        400 => [
            'headline' => 'Request format problem',
            'title' => 'Bad Request',
            'message' => 'KODUS could not understand the request that was sent.',
            'suggestion' => 'Review the submitted data and try again from the intended screen.',
            'icon' => 'exclamation-triangle',
            'theme' => 'warning',
        ],
        401 => [
            'headline' => 'Session required',
            'title' => 'Unauthorized',
            'message' => 'You need an active KODUS session before opening this area.',
            'suggestion' => 'Sign in, then retry the action.',
            'icon' => 'lock',
            'theme' => 'warning',
        ],
        403 => [
            'headline' => 'Access restricted',
            'title' => 'Forbidden',
            'message' => 'Your account does not currently have permission to open this page.',
            'suggestion' => 'Return to your dashboard or contact an administrator if you need access.',
            'icon' => 'shield-alt',
            'theme' => 'danger',
        ],
        404 => [
            'headline' => 'Route not found',
            'title' => 'Page Missing',
            'message' => 'KODUS could not find the resource you requested.',
            'suggestion' => 'Check the address, then head back to a known page.',
            'icon' => 'map-signs',
            'theme' => 'info',
        ],
        405 => [
            'headline' => 'Request blocked',
            'title' => 'Method Not Allowed',
            'message' => 'This action used an HTTP method that the target endpoint does not accept.',
            'suggestion' => 'Retry using the intended form or button instead of a direct request.',
            'icon' => 'ban',
            'theme' => 'warning',
        ],
        408 => [
            'headline' => 'Request timed out',
            'title' => 'Request Timeout',
            'message' => 'The request took too long to finish and KODUS stopped waiting for it.',
            'suggestion' => 'Retry the action once your connection is stable.',
            'icon' => 'stopwatch',
            'theme' => 'warning',
        ],
        429 => [
            'headline' => 'Too many attempts',
            'title' => 'Slow Down',
            'message' => 'Too many requests were sent in a short period.',
            'suggestion' => 'Wait a moment before trying again.',
            'icon' => 'tachometer-alt',
            'theme' => 'warning',
        ],
        500 => [
            'headline' => 'Application interrupted',
            'title' => 'Server Error',
            'message' => 'An unexpected problem interrupted the request.',
            'suggestion' => 'Try again shortly. If the issue persists, review the audit trail and server logs.',
            'icon' => 'server',
            'theme' => 'danger',
        ],
        502 => [
            'headline' => 'Gateway communication failed',
            'title' => 'Bad Gateway',
            'message' => 'An upstream dependency returned an invalid response.',
            'suggestion' => 'Retry once the dependent service stabilizes.',
            'icon' => 'exchange-alt',
            'theme' => 'danger',
        ],
        503 => [
            'headline' => 'KODUS is in maintenance mode',
            'title' => 'Service Unavailable',
            'message' => 'The platform is temporarily unavailable while maintenance is in progress.',
            'suggestion' => 'You will be redirected shortly. KODUS will remain unavailable until maintenance is completed.',
            'icon' => 'tools',
            'theme' => 'warning',
        ],
        504 => [
            'headline' => 'Upstream timeout',
            'title' => 'Gateway Timeout',
            'message' => 'A dependent service took too long to respond to KODUS.',
            'suggestion' => 'Retry after the upstream service recovers.',
            'icon' => 'clock',
            'theme' => 'danger',
        ],
    ];
}

function kodus_error_meta(int $code, array $overrides = []): array
{
    $catalog = kodus_error_catalog();
    $default = $catalog[$code] ?? $catalog[500];

    return array_merge($default, $overrides);
}

function kodus_error_request_path(): string
{
    $uri = trim((string) ($_SERVER['REQUEST_URI'] ?? ''));
    if ($uri !== '') {
        return $uri;
    }

    return trim((string) ($_SERVER['PHP_SELF'] ?? 'unknown'));
}

function kodus_is_ajax_request(): bool
{
    $requestedWith = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
    if ($requestedWith === 'xmlhttprequest') {
        return true;
    }

    $accept = strtolower(trim((string) ($_SERVER['HTTP_ACCEPT'] ?? '')));
    return str_contains($accept, 'application/json');
}

function kodus_app_settings_ensure_schema(mysqli $conn): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value LONGTEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL;

    $conn->query($sql);
}

function kodus_setting_get(mysqli $conn, string $key, ?string $default = null): ?string
{
    kodus_app_settings_ensure_schema($conn);

    $stmt = $conn->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
    if (!$stmt) {
        return $default;
    }

    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt->bind_result($value);
    $found = $stmt->fetch();
    $stmt->close();

    return $found ? (string) $value : $default;
}

function kodus_setting_set(mysqli $conn, string $key, string $value, ?int $updatedBy = null): bool
{
    kodus_app_settings_ensure_schema($conn);

    $stmt = $conn->prepare('
        INSERT INTO app_settings (setting_key, setting_value, updated_by, updated_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()
    ');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ssi', $key, $value, $updatedBy);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

function kodus_maintenance_message(mysqli $conn): string
{
    $defaultMessage = 'Routine maintenance is underway to keep KODUS stable, secure, and ready for the next working session.';
    return trim((string) kodus_setting_get($conn, 'maintenance_message', $defaultMessage));
}

function kodus_maintenance_warning_message(mysqli $conn): string
{
    $defaultMessage = 'Scheduled maintenance will begin soon. Please save or complete your current work before the countdown ends.';
    return trim((string) kodus_setting_get($conn, 'maintenance_warning_message', $defaultMessage));
}

function kodus_maintenance_warning_seconds(mysqli $conn): int
{
    $value = (int) kodus_setting_get($conn, 'maintenance_warning_seconds', '300');
    return max(0, min($value, 7200));
}

function kodus_maintenance_redirect_seconds(mysqli $conn): int
{
    $value = (int) kodus_setting_get($conn, 'maintenance_redirect_seconds', '12');
    return max(0, min($value, 60));
}

function kodus_maintenance_effective_at(mysqli $conn): ?int
{
    $rawValue = trim((string) kodus_setting_get($conn, 'maintenance_effective_at', ''));
    if ($rawValue === '') {
        return null;
    }

    $timestamp = strtotime($rawValue);
    return $timestamp === false ? null : $timestamp;
}

function kodus_maintenance_state(mysqli $conn): array
{
    $enabled = kodus_setting_get($conn, 'maintenance_enabled', '0') === '1';
    $warningSeconds = kodus_maintenance_warning_seconds($conn);
    $effectiveAt = kodus_maintenance_effective_at($conn);
    $now = time();

    $phase = 'inactive';
    $secondsRemaining = 0;

    if ($enabled) {
        if ($effectiveAt !== null && $effectiveAt > $now) {
            $phase = 'pending';
            $secondsRemaining = max(0, $effectiveAt - $now);
        } else {
            $phase = 'active';
        }
    }

    return [
        'enabled' => $enabled,
        'phase' => $phase,
        'message' => kodus_maintenance_message($conn),
        'warning_message' => kodus_maintenance_warning_message($conn),
        'warning_seconds' => $warningSeconds,
        'redirect_seconds' => kodus_maintenance_redirect_seconds($conn),
        'effective_at' => $effectiveAt !== null ? date(DATE_ATOM, $effectiveAt) : null,
        'seconds_remaining' => $secondsRemaining,
    ];
}

function kodus_maintenance_is_enabled(mysqli $conn): bool
{
    return (kodus_maintenance_state($conn)['phase'] ?? 'inactive') === 'active';
}

function kodus_queue_popup(array $payload): void
{
    security_bootstrap_session();

    $_SESSION['kodus_popup'] = [
        'icon' => (string) ($payload['icon'] ?? 'error'),
        'title' => (string) ($payload['title'] ?? 'KODUS Alert'),
        'text' => (string) ($payload['text'] ?? ''),
        'timer' => isset($payload['timer']) ? (int) $payload['timer'] : null,
    ];
}

function kodus_error_redirect_url(int $code): string
{
    require __DIR__ . '/base_url.php';

    if ($code === 401 || !isset($_SESSION['user_id'])) {
        return (string) $app_root;
    }

    if ($code === 503) {
        $token = rawurlencode(security_get_csrf_token());
        return (string) ($app_root . 'logout?reason=maintenance&token=' . $token);
    }

    return (string) ($app_root . 'home');
}

function kodus_error_log(?mysqli $conn, int $code, array $meta, array $context = []): void
{
    $summaryParts = [
        'Code: ' . $code,
        'Title: ' . ($meta['title'] ?? 'Error'),
        'Path: ' . kodus_error_request_path(),
        'Method: ' . strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
    ];

    $detail = trim((string) ($context['detail'] ?? ''));
    if ($detail !== '') {
        $summaryParts[] = 'Detail: ' . $detail;
    }

    if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
        $summaryParts[] = 'Exception: ' . $context['exception']->getMessage();
    }

    if (isset($context['fatal']) && is_array($context['fatal'])) {
        $summaryParts[] = 'Fatal: ' . (string) ($context['fatal']['message'] ?? 'Unknown fatal error');
    }

    $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($userAgent !== '') {
        $summaryParts[] = 'User Agent: ' . $userAgent;
    }

    $details = implode(' | ', $summaryParts);

    if ($conn instanceof mysqli) {
        $userId = isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        audit_log($conn, $userId, 'HTTP ' . $code, $details, security_get_client_ip());
        return;
    }

    error_log('KODUS HTTP ' . $code . ': ' . $details);
}

function kodus_maintenance_should_bypass(): bool
{
    $currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));

    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        return true;
    }

    $bypassPages = [
        'index.php',
        'login.php',
        'login-sso.php',
        'callback.php',
        'forgot-password.php',
        'reset-password.php',
        'register.php',
        'verify-2fa.php',
        'verify_2fa_code.php',
        'ajax_login.php',
        'logout.php',
        'maintenance.php',
        'get_maintenance_state.php',
        'save_maintenance_settings.php',
    ];

    return in_array($currentPage, $bypassPages, true);
}

function kodus_enforce_maintenance_mode(mysqli $conn): void
{
    if (!kodus_maintenance_is_enabled($conn) || kodus_maintenance_should_bypass()) {
        return;
    }

    kodus_abort($conn, 503, [
        'detail' => kodus_maintenance_message($conn),
        'popup' => false,
        'redirect_label' => isset($_SESSION['user_id']) ? 'Sign Out Safely' : 'Back to Login',
    ]);
}

function kodus_abort(?mysqli $conn, int $code, array $context = []): void
{
    $meta = kodus_error_meta($code, $context['meta'] ?? []);

    if (empty($context['skip_log'])) {
        kodus_error_log($conn, $code, $meta, $context);
    }

    if (kodus_is_ajax_request()) {
        security_send_json([
            'success' => false,
            'error' => [
                'code' => $code,
                'title' => $meta['title'],
                'message' => $context['detail'] ?? $meta['message'],
            ],
        ], $code);
    }

    http_response_code($code);

    $shouldPopup = !empty($context['popup']);
    $redirect = array_key_exists('redirect', $context) ? (string) $context['redirect'] : kodus_error_redirect_url($code);

    if ($shouldPopup && $redirect !== '' && !headers_sent()) {
        kodus_queue_popup([
            'icon' => $code >= 500 ? 'error' : ($code === 401 ? 'warning' : 'info'),
            'title' => $meta['title'],
            'text' => (string) ($context['detail'] ?? $meta['message']),
        ]);

        header('Location: ' . $redirect);
        exit;
    }

    kodus_render_error_page($code, $context);
}

function kodus_render_error_page(int $code, array $context = []): void
{
    require __DIR__ . '/base_url.php';
    require_once __DIR__ . '/theme_helpers.php';

    $meta = kodus_error_meta($code, $context['meta'] ?? []);
    $themePreference = function_exists('theme_current_preference') ? theme_current_preference() : 'light';
    $isDarkTheme = $themePreference === 'dark';
    $primaryUrl = array_key_exists('redirect', $context) ? (string) $context['redirect'] : kodus_error_redirect_url($code);
    $primaryLabel = $context['redirect_label'] ?? ($code === 401 ? 'Back to Login' : 'Back to Home');
    $detailMessage = trim((string) ($context['detail'] ?? $meta['message']));
    $suggestion = trim((string) ($meta['suggestion'] ?? ''));
    $countdown = $code === 503 && isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli
        ? kodus_maintenance_redirect_seconds($GLOBALS['conn'])
        : 0;

    http_response_code($code);
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KODUS | <?= htmlspecialchars((string) $meta['title'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="shortcut icon" href="<?= htmlspecialchars((string) $app_root, ENT_QUOTES, 'UTF-8') ?>favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="<?= htmlspecialchars((string) $app_root, ENT_QUOTES, 'UTF-8') ?>fonts.googleapis.com/css/fontfamily.css">
    <link rel="stylesheet" href="<?= htmlspecialchars((string) $app_root, ENT_QUOTES, 'UTF-8') ?>plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars((string) $app_root, ENT_QUOTES, 'UTF-8') ?>plugins/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars((string) $app_root, ENT_QUOTES, 'UTF-8') ?>dist/css/adminlte.min.css">
    <style>
        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(0, 123, 255, 0.18), transparent 30%),
                radial-gradient(circle at bottom right, rgba(40, 167, 69, 0.16), transparent 22%),
                linear-gradient(135deg, <?= $isDarkTheme ? '#0f1720, #121b2b 60%, #091018' : '#eef4fb, #ffffff 62%, #edf7f3' ?>);
            color: <?= $isDarkTheme ? '#eaf1f8' : '#1f2d3d' ?>;
            font-family: 'Source Sans Pro', sans-serif;
        }
        .kodus-error-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .kodus-error-card {
            width: min(1080px, 100%);
            border-radius: 1.5rem;
            overflow: hidden;
            border: 1px solid <?= $isDarkTheme ? 'rgba(255,255,255,0.08)' : 'rgba(15,23,42,0.08)' ?>;
            background: <?= $isDarkTheme ? 'rgba(12, 20, 31, 0.9)' : 'rgba(255, 255, 255, 0.92)' ?>;
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.22);
            backdrop-filter: blur(12px);
        }
        .kodus-error-brand {
            position: relative;
            padding: 2rem;
            background: linear-gradient(140deg, rgba(0, 123, 255, 0.18), rgba(32, 201, 151, 0.12));
            border-bottom: 1px solid <?= $isDarkTheme ? 'rgba(255,255,255,0.08)' : 'rgba(15,23,42,0.08)' ?>;
        }
        .kodus-error-brand::after {
            content: "";
            position: absolute;
            top: -60px;
            right: -60px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }
        .kodus-error-body {
            padding: 2rem;
        }
        .kodus-logo-lockup {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
        }
        .kodus-logo-wrap {
            position: relative;
            width: 88px;
            height: 88px;
            display: grid;
            place-items: center;
        }
        .kodus-logo-ring,
        .kodus-logo-ring::before,
        .kodus-logo-ring::after {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            content: "";
        }
        .kodus-logo-ring {
            border: 2px solid rgba(0, 123, 255, 0.35);
            animation: kodusSpin 9s linear infinite;
        }
        .kodus-logo-ring::before {
            inset: 8px;
            border: 2px dashed rgba(40, 167, 69, 0.34);
            animation: kodusSpinReverse 12s linear infinite;
        }
        .kodus-logo-ring::after {
            inset: 18px;
            background: radial-gradient(circle, rgba(0,123,255,0.16), transparent 70%);
            animation: kodusPulse 2.6s ease-in-out infinite;
        }
        .kodus-logo {
            position: relative;
            width: 56px;
            height: 56px;
            object-fit: contain;
            animation: kodusFloat 3s ease-in-out infinite;
            z-index: 1;
        }
        .kodus-error-code {
            font-size: clamp(4rem, 10vw, 7rem);
            font-weight: 800;
            line-height: 0.95;
            margin: 0;
            letter-spacing: -0.04em;
        }
        .kodus-error-copy h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.65rem;
        }
        .kodus-error-copy p {
            margin-bottom: 0.85rem;
            font-size: 1.05rem;
        }
        .kodus-error-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            margin-top: 1rem;
        }
        .kodus-error-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.55rem 0.9rem;
            border-radius: 999px;
            font-weight: 700;
            background: <?= $isDarkTheme ? 'rgba(255,255,255,0.08)' : 'rgba(15,23,42,0.06)' ?>;
        }
        .kodus-error-panel {
            height: 100%;
            padding: 1.2rem;
            border-radius: 1.2rem;
            background: <?= $isDarkTheme ? 'rgba(255,255,255,0.04)' : '#f8fbff' ?>;
            border: 1px solid <?= $isDarkTheme ? 'rgba(255,255,255,0.08)' : 'rgba(15,23,42,0.08)' ?>;
        }
        .kodus-error-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.25rem;
        }
        .kodus-error-meta {
            font-size: 0.92rem;
            opacity: 0.75;
            margin-top: 1rem;
        }
        .kodus-error-counter {
            margin-top: 1.1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.1rem;
            border-radius: 1.1rem;
            border: 1px solid <?= $isDarkTheme ? 'rgba(125, 196, 255, 0.2)' : 'rgba(13, 110, 253, 0.14)' ?>;
            background: <?= $isDarkTheme ? 'linear-gradient(135deg, rgba(37, 99, 235, 0.12), rgba(16, 185, 129, 0.08))' : 'linear-gradient(135deg, rgba(13, 110, 253, 0.08), rgba(32, 201, 151, 0.08))' ?>;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
        }
        .kodus-error-counter-copy {
            flex: 1 1 auto;
        }
        .kodus-error-counter-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            margin-bottom: 0.3rem;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.75;
        }
        .kodus-error-counter-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
        }
        .kodus-error-counter-subtitle {
            margin: 0.3rem 0 0;
            font-size: 0.92rem;
            opacity: 0.82;
        }
        .kodus-error-timer {
            --timer-size: 108px;
            --timer-stroke: 8;
            position: relative;
            width: var(--timer-size);
            height: var(--timer-size);
            flex: 0 0 auto;
            display: grid;
            place-items: center;
        }
        .kodus-error-timer-svg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            transform: rotate(-90deg);
            overflow: visible;
            filter: drop-shadow(0 10px 24px rgba(37, 99, 235, 0.18));
        }
        .kodus-error-timer-track {
            fill: none;
            stroke: <?= $isDarkTheme ? 'rgba(255,255,255,0.1)' : 'rgba(15,23,42,0.1)' ?>;
            stroke-width: var(--timer-stroke);
        }
        .kodus-error-timer-progress {
            fill: none;
            stroke: url(#kodusTimerGradient);
            stroke-width: var(--timer-stroke);
            stroke-linecap: round;
            stroke-dasharray: 283;
            stroke-dashoffset: 0;
            transition: stroke-dashoffset 1s linear;
        }
        .kodus-error-timer-core {
            position: relative;
            width: calc(var(--timer-size) - 28px);
            height: calc(var(--timer-size) - 28px);
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: <?= $isDarkTheme ? 'radial-gradient(circle at top, rgba(255,255,255,0.06), rgba(15,23,42,0.28))' : 'radial-gradient(circle at top, rgba(255,255,255,0.96), rgba(227, 240, 255, 0.92))' ?>;
            border: 1px solid <?= $isDarkTheme ? 'rgba(255,255,255,0.08)' : 'rgba(13,110,253,0.1)' ?>;
            animation: kodusTimerPulse 1.8s ease-in-out infinite;
        }
        .kodus-error-timer-number {
            font-size: 2rem;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
        }
        .kodus-error-timer-label {
            margin-top: 0.1rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            opacity: 0.72;
            font-weight: 700;
        }
        }
        @keyframes kodusFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        @keyframes kodusPulse {
            0%, 100% { transform: scale(0.95); opacity: 0.45; }
            50% { transform: scale(1.06); opacity: 0.85; }
        }
        @keyframes kodusSpin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes kodusSpinReverse {
            from { transform: rotate(360deg); }
            to { transform: rotate(0deg); }
        }
        @keyframes kodusTimerPulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.08); }
            50% { transform: scale(1.03); box-shadow: 0 0 0 10px rgba(37, 99, 235, 0.02); }
        }
        @media (max-width: 767.98px) {
            .kodus-error-brand,
            .kodus-error-body {
                padding: 1.4rem;
            }
            .kodus-error-counter {
                flex-direction: column-reverse;
                align-items: stretch;
                text-align: center;
            }
            .kodus-error-timer {
                margin: 0 auto;
            }
        }
    </style>
</head>
<body class="<?= $isDarkTheme ? 'dark-mode' : '' ?>" data-theme="<?= htmlspecialchars($themePreference, ENT_QUOTES, 'UTF-8') ?>">
    <div class="kodus-error-shell">
        <div class="kodus-error-card">
            <div class="kodus-error-brand">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center" style="gap: 1.5rem;">
                    <div class="kodus-logo-lockup">
                        <div class="kodus-logo-wrap">
                            <div class="kodus-logo-ring"></div>
                            <img class="kodus-logo" src="<?= htmlspecialchars((string) $app_root, ENT_QUOTES, 'UTF-8') ?>dist/img/kodus.png" alt="KODUS logo">
                        </div>
                        <div>
                            <div class="text-uppercase font-weight-bold" style="letter-spacing: 0.12em; opacity: 0.75;">KODUS Response Center</div>
                            <h2 class="h3 mb-1 font-weight-bold">KliMalasakit Operational Data Unified System</h2>
                            <div><?= htmlspecialchars((string) $meta['headline'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                    <div class="text-lg-right">
                        <p class="kodus-error-code"><?= (int) $code ?></p>
                        <div class="text-uppercase font-weight-bold" style="letter-spacing: 0.1em; opacity: 0.8;"><?= htmlspecialchars((string) $meta['title'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
            </div>
            <div class="kodus-error-body">
                <div class="row">
                    <div class="col-lg-7 mb-4 mb-lg-0">
                        <div class="kodus-error-copy">
                            <h1><?= htmlspecialchars((string) $meta['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                            <p><?= htmlspecialchars($detailMessage, ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mb-0"><?= htmlspecialchars($suggestion, ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="kodus-error-pills">
                                <span class="kodus-error-pill"><i class="fas fa-<?= htmlspecialchars((string) $meta['icon'], ENT_QUOTES, 'UTF-8') ?>"></i> <?= htmlspecialchars((string) $meta['headline'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="kodus-error-pill"><i class="fas fa-route"></i> <?= htmlspecialchars(kodus_error_request_path(), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <?php if ($countdown > 0 && $primaryUrl !== ''): ?>
                            <div class="kodus-error-counter">
                                <div class="kodus-error-counter-copy">
                                    <div class="kodus-error-counter-eyebrow">
                                        <i class="fas fa-bolt"></i>
                                        Redirect Sequence Armed
                                    </div>
                                    <p class="kodus-error-counter-title">Automatic redirect in <span id="kodusMaintenanceCountdownLabel"><?= $countdown ?></span> second<?= $countdown === 1 ? '' : 's' ?></p>
                                    <p class="kodus-error-counter-subtitle">This timer only moves you away from the protected area. Maintenance may still be in progress after it ends.</p>
                                </div>
                                <div class="kodus-error-timer" aria-hidden="true">
                                    <svg viewBox="0 0 108 108" class="kodus-error-timer-svg">
                                        <defs>
                                            <linearGradient id="kodusTimerGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                                <stop offset="0%" stop-color="#34d399"></stop>
                                                <stop offset="55%" stop-color="#60a5fa"></stop>
                                                <stop offset="100%" stop-color="#2563eb"></stop>
                                            </linearGradient>
                                        </defs>
                                        <circle class="kodus-error-timer-track" cx="54" cy="54" r="45"></circle>
                                        <circle class="kodus-error-timer-progress" id="kodusMaintenanceProgress" cx="54" cy="54" r="45"></circle>
                                    </svg>
                                    <div class="kodus-error-timer-core">
                                        <div>
                                            <div class="kodus-error-timer-number" id="kodusMaintenanceCountdown"><?= $countdown ?></div>
                                            <div class="kodus-error-timer-label">seconds</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="kodus-error-actions">
                                <?php if ($primaryUrl !== ''): ?>
                                <a href="<?= htmlspecialchars($primaryUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary btn-lg">
                                    <i class="fas fa-arrow-left mr-2"></i><?= htmlspecialchars((string) $primaryLabel, ENT_QUOTES, 'UTF-8') ?>
                                </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline-secondary btn-lg" onclick="window.location.reload()">
                                    <i class="fas fa-redo mr-2"></i>Retry
                                </button>
                            </div>
                            <div class="kodus-error-meta">
                                <?= htmlspecialchars(strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')), ENT_QUOTES, 'UTF-8') ?>
                                · <?= htmlspecialchars(date('M d, Y h:i A'), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="kodus-error-panel">
                            <h3 class="h5 font-weight-bold mb-3">Need help?</h3>
                            <?php if ($code === 503): ?>
                            <p class="mb-2">This countdown only redirects you from the current page. KODUS may still be unavailable after the timer ends while maintenance continues.</p>
                            <p class="mb-2">Please try again later. If maintenance lasts longer than expected, contact your KODUS administrator for an update.</p>
                            <p class="mb-0">That helps the team confirm whether the downtime is still planned or needs follow-up.</p>
                            <?php else: ?>
                            <p class="mb-2">Try returning to a safe page, refreshing the request, or signing in again if your session has expired.</p>
                            <p class="mb-2">If the problem keeps happening, contact your KODUS administrator and share the time the issue occurred.</p>
                            <p class="mb-0">That helps the team trace the event faster and restore normal service more quickly.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php if ($countdown > 0 && $primaryUrl !== ''): ?>
    <script>
    (function () {
        var remaining = <?= (int) $countdown ?>;
        var total = Math.max(remaining, 1);
        var target = <?= json_encode($primaryUrl) ?>;
        var counter = document.getElementById('kodusMaintenanceCountdown');
        var label = document.getElementById('kodusMaintenanceCountdownLabel');
        var progress = document.getElementById('kodusMaintenanceProgress');
        var circumference = 2 * Math.PI * 45;

        if (progress) {
            progress.style.strokeDasharray = String(circumference);
            progress.style.strokeDashoffset = '0';
        }

        function renderCountdown() {
            var safeRemaining = Math.max(remaining, 0);
            if (counter) {
                counter.textContent = String(safeRemaining);
            }
            if (label) {
                label.textContent = String(safeRemaining);
            }
            if (progress) {
                var ratio = safeRemaining / total;
                progress.style.strokeDashoffset = String(circumference * (1 - ratio));
            }
        }

        renderCountdown();

        var timer = window.setInterval(function () {
            remaining -= 1;
            renderCountdown();
            if (remaining <= 0) {
                window.clearInterval(timer);
                window.location.href = target;
            }
        }, 1000);
    })();
    </script>
    <?php endif; ?>
</body>
</html>
<?php
    exit;
}
