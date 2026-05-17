<?php

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/socket_helpers.php';

function audit_log(mysqli $conn, ?int $userId, string $action, string $details, ?string $ipAddress = null): void
{
    static $requestLogFingerprints = [];

    $fingerprint = md5(json_encode([$userId, $action, $details]));
    if (isset($requestLogFingerprints[$fingerprint])) {
        return;
    }

    $requestLogFingerprints[$fingerprint] = true;

    $ipAddress = $ipAddress ?: security_get_client_ip();
    $stmt = $conn->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())');
    if (!$stmt) {
        error_log('Audit log prepare failed for action: ' . $action);
        return;
    }

    $stmt->bind_param('isss', $userId, $action, $details, $ipAddress);
    $inserted = $stmt->execute();
    $auditLogId = $inserted ? (int) $conn->insert_id : 0;
    $stmt->close();

    if ($inserted) {
        kodus_socket_broadcast('kodus.audit_logs', 'audit_logs.changed', [
            'action' => $action,
            'audit_log_id' => $auditLogId,
            'user_id' => $userId,
        ]);

        if ($action !== 'Page Visit') {
            kodus_socket_broadcast('kodus.tables', 'tables.changed', [
                'source_channel' => 'kodus.audit_logs',
                'source_event' => 'audit_log.inserted',
                'action' => $action,
                'audit_log_id' => $auditLogId,
                'user_id' => $userId,
            ]);
            kodus_socket_broadcast('kodus.ui', 'ui.changed', [
                'source_channel' => 'kodus.audit_logs',
                'source_event' => 'audit_log.inserted',
                'action' => $action,
                'audit_log_id' => $auditLogId,
                'user_id' => $userId,
            ]);
        }
    }
}

function audit_describe_value(mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if (is_array($value) || is_object($value)) {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? '[complex value]' : $encoded;
    }

    $stringValue = trim((string) $value);
    return $stringValue === '' ? '[empty]' : $stringValue;
}

function audit_collect_field_changes(array $before, array $after, ?array $fieldLabels = null): array
{
    $changes = [];
    $fields = array_unique(array_merge(array_keys($before), array_keys($after)));

    foreach ($fields as $field) {
        $oldValue = $before[$field] ?? null;
        $newValue = $after[$field] ?? null;

        if (audit_describe_value($oldValue) === audit_describe_value($newValue)) {
            continue;
        }

        $changes[] = [
            'field' => $fieldLabels[$field] ?? $field,
            'before' => audit_describe_value($oldValue),
            'after' => audit_describe_value($newValue),
        ];
    }

    return $changes;
}

function audit_format_field_changes(array $changes): string
{
    if ($changes === []) {
        return 'No field changes detected.';
    }

    $parts = [];
    foreach ($changes as $change) {
        $parts[] = $change['field'] . ": '" . $change['before'] . "' -> '" . $change['after'] . "'";
    }

    return implode('; ', $parts);
}

function audit_request_path(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if ($uri !== '') {
        return $uri;
    }

    return (string) ($_SERVER['PHP_SELF'] ?? 'unknown');
}

function audit_request_summary(): string
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $path = audit_request_path();
    $postKeys = array_keys($_POST ?? []);
    sort($postKeys);

    $detailParts = [
        "Method: {$method}",
        "Path: {$path}",
    ];

    if (!empty($postKeys)) {
        $detailParts[] = 'Fields: ' . implode(', ', $postKeys);
    }

    $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($userAgent !== '') {
        $detailParts[] = 'User Agent: ' . $userAgent;
    }

    return implode(' | ', $detailParts);
}

function audit_log_state_change_request(mysqli $conn): void
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return;
    }

    $userId = $_SESSION['user_id'] ?? null;
    if (!is_numeric($userId)) {
        return;
    }

    $path = strtolower(audit_request_path());
    $skipPatterns = [
        '/ajax_login.php',
    ];

    foreach ($skipPatterns as $pattern) {
        if (str_contains($path, $pattern)) {
            return;
        }
    }

    $action = 'Request ' . $method;
    audit_log($conn, (int) $userId, $action, audit_request_summary());
}

function audit_log_page_visit(mysqli $conn): void
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'GET') {
        return;
    }

    $userId = $_SESSION['user_id'] ?? null;
    if (!is_numeric($userId)) {
        return;
    }

    $path = audit_request_path();
    $action = 'Page Visit';
    $details = 'Visited ' . $path;

    audit_log($conn, (int) $userId, $action, $details);
}
