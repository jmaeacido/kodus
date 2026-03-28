<?php

require_once __DIR__ . '/security.php';

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
    $stmt->execute();
    $stmt->close();
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
