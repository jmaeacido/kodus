<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/mailbox_helpers.php';
require_once __DIR__ . '/../socket_helpers.php';
header('Content-Type: application/json');

security_require_method(['POST']);
security_require_csrf_token();
mailboxEnsureSchema($conn);

if (!isset($_SESSION['user_id']) || empty($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$userId = $_SESSION['user_id'];
$messageId = (int)$_POST['id'];
$userType = $_SESSION['user_type'] ?? null;
$userEmail = $_SESSION['email'] ?? null;
$userName = trim((string) ($_SESSION['username'] ?? ''));

mailboxTouchCurrentUserPresence($conn);

if ($userType === 'admin') {
    $accessStmt = $conn->prepare("
        SELECT cm.id
        FROM contact_messages cm
        WHERE cm.id = ?
          AND (
              COALESCE(cm.conversation_type, 'direct') = 'group'
              OR
              cm.user_email = ?
              OR EXISTS (
                  SELECT 1
                  FROM contact_message_recipients cmr
                  INNER JOIN users u ON u.id = cmr.user_id
                  WHERE cmr.message_id = cm.id
                    AND u.userType = 'admin'
              )
          )
          AND " . mailboxThreadAccessPredicate('cm') . "
        LIMIT 1
    ");
    $accessStmt->bind_param("isi", $messageId, $userEmail, $userId);
} else {
    $accessStmt = $conn->prepare("
        SELECT id
        FROM contact_messages
        WHERE id = ?
          AND (user_email = ? OR user_name = ? OR EXISTS (
              SELECT 1
              FROM contact_message_recipients cmr
              WHERE cmr.message_id = contact_messages.id
                AND (cmr.user_id = ? OR LOWER(cmr.recipient_email) = LOWER(?))
          )
          OR COALESCE(conversation_type, 'direct') = 'group')
          AND " . mailboxThreadAccessPredicate('contact_messages') . "
        LIMIT 1
    ");
    $accessStmt->bind_param("issisi", $messageId, $userEmail, $userName, $userId, $userEmail, $userId);
}

$accessStmt->execute();
$isAllowed = (bool) db_stmt_fetch_one_assoc($accessStmt);
$accessStmt->close();

if (!$isAllowed) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$latestReplyId = 0;
$cursorStmt = $conn->prepare('SELECT COALESCE(MAX(id), 0) AS latest_reply_id FROM contact_replies WHERE message_id = ?');
if ($cursorStmt) {
    $cursorStmt->bind_param('i', $messageId);
    $cursorStmt->execute();
    $cursorRow = db_stmt_fetch_one_assoc($cursorStmt) ?: [];
    $latestReplyId = (int) ($cursorRow['latest_reply_id'] ?? 0);
    $cursorStmt->close();
}

// Mark the original message (thread) as read for this user
$stmt = $conn->prepare("
    INSERT INTO message_reads (message_id, user_id, is_read, read_at, last_read_reply_id)
    VALUES (?, ?, 1, NOW(), ?)
    ON DUPLICATE KEY UPDATE is_read = 1, read_at = NOW(), last_read_reply_id = VALUES(last_read_reply_id)
");
if ($stmt) {
    $stmt->bind_param("iii", $messageId, $userId, $latestReplyId);
    $stmt->execute();
    $stmt->close();
}

kodus_socket_broadcast('kodus.mailbox', 'mail.changed', [
    'action' => 'message_read',
    'message_id' => $messageId,
    'actor_id' => (int) $userId,
]);

echo json_encode(['success' => true]);
