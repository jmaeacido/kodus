<?php
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/mailbox_helpers.php';
require_once __DIR__ . '/../socket_helpers.php';

security_bootstrap_session();
include('../config.php');
mailboxEnsureSchema($conn);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

if (!security_validate_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$messageId = (int) ($_POST['message_id'] ?? 0);
$isTyping = !empty($_POST['is_typing']) && $_POST['is_typing'] !== '0';

if ($userId <= 0 || $messageId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing typing target.']);
    exit;
}

if ($isTyping) {
    $stmt = $conn->prepare("
        INSERT INTO mailbox_typing_status (message_id, user_id, started_at, last_seen_at)
        VALUES (?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE last_seen_at = NOW()
    ");
    $stmt->bind_param('ii', $messageId, $userId);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare('DELETE FROM mailbox_typing_status WHERE message_id = ? AND user_id = ?');
    $stmt->bind_param('ii', $messageId, $userId);
    $stmt->execute();
    $stmt->close();
}

kodus_socket_broadcast('kodus.mailbox', 'mail.changed', [
    'action' => $isTyping ? 'typing_started' : 'typing_stopped',
    'message_id' => $messageId,
    'actor_id' => $userId,
]);

echo json_encode(['success' => true]);
