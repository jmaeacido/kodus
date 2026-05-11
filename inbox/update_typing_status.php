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
$userType = $_SESSION['user_type'] ?? null;
$userEmail = $_SESSION['email'] ?? null;
$userName = trim((string) ($_SESSION['username'] ?? ''));
$messageId = (int) ($_POST['message_id'] ?? 0);
$isTyping = !empty($_POST['is_typing']) && $_POST['is_typing'] !== '0';

if ($userId <= 0 || $messageId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing typing target.']);
    exit;
}

mailboxTouchCurrentUserPresence($conn, $isTyping);

if (!mailboxCanAccessMessage($conn, $messageId, $userType, $userEmail, $userName)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You are not allowed to update typing for this conversation.']);
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

$receiverIds = [];
$recipientStmt = $conn->prepare("
    SELECT DISTINCT u.id
    FROM users u
    INNER JOIN contact_messages cm ON cm.id = ?
    LEFT JOIN contact_message_recipients cmr
        ON cmr.message_id = cm.id
       AND (
           cmr.user_id = u.id
           OR LOWER(cmr.recipient_email) = LOWER(u.email)
       )
    WHERE u.id <> ?
      AND (
          (
              COALESCE(cm.conversation_type, 'direct') = 'group'
              AND cmr.user_id IS NOT NULL
              AND cmr.left_at IS NULL
              AND cmr.hidden_at IS NULL
          )
          OR (
              COALESCE(cm.conversation_type, 'direct') <> 'group'
              AND (LOWER(u.email) = LOWER(cm.user_email) OR cmr.message_id IS NOT NULL)
          )
      )
");
if ($recipientStmt) {
    $recipientStmt->bind_param('ii', $messageId, $userId);
    $recipientStmt->execute();
    foreach (db_stmt_fetch_all_assoc($recipientStmt) as $row) {
        $receiverIds[] = (int) ($row['id'] ?? 0);
    }
    $recipientStmt->close();
}
$receiverIds = array_values(array_unique(array_filter($receiverIds)));

$typingBroadcasted = kodus_socket_broadcast('kodus.mailbox', 'mail.typing', [
    'action' => $isTyping ? 'typing_started' : 'typing_stopped',
    'thread_id' => $messageId,
    'message_id' => $messageId,
    'sender_id' => $userId,
    'actor_id' => $userId,
    'sender_name' => $userName !== '' ? $userName : 'Someone',
    'receiver_ids' => $receiverIds,
]);
error_log('KODUS typing broadcast status: ' . ($typingBroadcasted ? 'sent' : 'failed'));

echo json_encode(['success' => true]);
