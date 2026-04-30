<?php

require_once __DIR__ . '/env_helpers.php';
require_once __DIR__ . '/base_url.php';

function kodus_socket_env_bool(string $key, bool $default = false): bool
{
    $value = strtolower((string) app_env($key, $default ? 'true' : 'false'));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function kodus_socket_origin_from_sso(): string
{
    $authorizeUrl = (string) app_env('CC_OAUTH_AUTHORIZE_URL', '');
    if ($authorizeUrl === '') {
        return '';
    }

    $parts = parse_url($authorizeUrl);
    if (!isset($parts['scheme'], $parts['host'])) {
        return '';
    }

    $origin = $parts['scheme'] . '://' . $parts['host'];
    if (!empty($parts['port'])) {
        $origin .= ':' . (int) $parts['port'];
    }

    return $origin;
}

function kodus_socket_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $origin = rtrim((string) app_env('KODUS_SOCKET_SERVER_URL', ''), '/');
    $broadcastUrl = (string) app_env('KODUS_SOCKET_BROADCAST_URL', $origin !== '' ? $origin . '/socket/broadcast' : '');
    $configuredClientScriptUrl = trim((string) app_env('KODUS_SOCKET_CLIENT_SCRIPT_URL', ''));
    $localClientScriptUrl = app_url('dist/js/socket.io.js');
    $clientScriptUrl = $configuredClientScriptUrl !== '' ? $configuredClientScriptUrl : $localClientScriptUrl;
    if (preg_match('~/socket\.io/socket\.io\.js(?:[?#].*)?$~i', $clientScriptUrl) === 1) {
        $clientScriptUrl = $localClientScriptUrl;
    }
    $enabled = kodus_socket_env_bool('KODUS_SOCKET_ENABLED', $broadcastUrl !== '' || $origin !== '');

    $config = [
        'enabled' => $enabled,
        'server_url' => $origin,
        'broadcast_url' => $broadcastUrl,
        'client_script_url' => $clientScriptUrl,
        'join_event' => (string) app_env('KODUS_SOCKET_JOIN_EVENT', 'subscribe'),
        'channel_prefix' => (string) app_env('KODUS_SOCKET_CHANNEL_PREFIX', 'kodus'),
        'service_token' => (string) app_env('KODUS_SOCKET_BEARER_TOKEN', ''),
    ];

    return $config;
}

function kodus_socket_is_enabled(): bool
{
    $config = kodus_socket_config();
    return !empty($config['enabled']) && $config['broadcast_url'] !== '';
}

function kodus_socket_frontend_config(): array
{
    $config = kodus_socket_config();

    return [
        'enabled' => (bool) ($config['enabled'] ?? false),
        'serverUrl' => (string) ($config['server_url'] ?? ''),
        'clientScriptUrl' => (string) ($config['client_script_url'] ?? ''),
        'joinEvent' => (string) ($config['join_event'] ?? 'subscribe'),
        'accessToken' => '',
    ];
}

function kodus_socket_broadcast(string $channel, string $event, array $data = []): bool
{
    $config = kodus_socket_config();
    $broadcastUrl = trim((string) ($config['broadcast_url'] ?? ''));
    if (empty($config['enabled']) || $broadcastUrl === '') {
        return false;
    }

    $token = trim((string) ($config['service_token'] ?? ''), " \t\n\r\0\x0B\"'");

    if ($token === '') {
        error_log('KODUS socket broadcast skipped: missing bearer token.');
        return false;
    }

    error_log('KODUS socket broadcast token length: ' . strlen($token));

    if (!function_exists('curl_init')) {
        error_log('KODUS socket broadcast skipped: cURL extension is unavailable.');
        return false;
    }

    $payload = json_encode([
        'channel' => $channel,
        'event' => $event,
        'data' => $data,
    ]);

    if (!is_string($payload) || $payload === '') {
        error_log('KODUS socket broadcast skipped: failed to encode payload.');
        return false;
    }

    $curl = curl_init($broadcastUrl);
    if ($curl === false) {
        error_log('KODUS socket broadcast skipped: failed to initialize cURL.');
        return false;
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => kodus_socket_env_bool('CC_OAUTH_VERIFY_SSL', false),
        CURLOPT_SSL_VERIFYHOST => kodus_socket_env_bool('CC_OAUTH_VERIFY_SSL', false) ? 2 : 0,
    ]);

    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    error_log('KODUS socket broadcast HTTP status: ' . $status);

    if ($response === false || $status < 200 || $status >= 300) {
        error_log('KODUS socket broadcast failed: HTTP ' . $status);
        return false;
    }

    return true;
}
