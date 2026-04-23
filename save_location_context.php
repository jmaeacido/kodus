<?php

declare(strict_types=1);

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/env_helpers.php';
require_once __DIR__ . '/app_location_helpers.php';

security_configure_runtime_for_web();
security_bootstrap_session();
app_load_environment();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();

$rawBody = file_get_contents('php://input');
$payload = json_decode(is_string($rawBody) ? $rawBody : '', true);

if (!is_array($payload)) {
    security_send_json([
        'success' => false,
        'error' => 'Invalid location payload.',
    ], 400);
}

$changed = app_store_client_location_context($payload);
app_apply_current_timezone();

security_send_json([
    'success' => true,
    'changed' => $changed,
    'location' => app_location_session_snapshot(),
]);
