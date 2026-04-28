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
$userType = (string) ($_SESSION['user_type'] ?? '');
$userEmail = trim((string) ($_SESSION['email'] ?? ''));
$userName = trim((string) ($_SESSION['username'] ?? ''));
$messageId = (int) ($_POST['message_id'] ?? 0);
$replyId = isset($_POST['reply_id']) && $_POST['reply_id'] !== '' ? (int) $_POST['reply_id'] : null;
$emoji = trim((string) ($_POST['emoji'] ?? ''));
$targetKey = $replyId !== null && $replyId > 0 ? 'reply:' . $replyId : 'message';

if ($userId <= 0 || $messageId <= 0 || $emoji === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing required reaction data.']);
    exit;
}

$allowedEmojis = ['👍','❤️','😂','🎉','🔥','👏','🙏','✅','👀','💡'];
if (!in_array($emoji, $allowedEmojis, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Unsupported reaction.']);
    exit;
}

$threadAccessSql = "
    SELECT cm.id
    FROM contact_messages cm
    WHERE cm.id = ?
";

if ($userType === 'admin') {
    $threadAccessSql .= "
      AND (
          cm.user_email = ?
          OR EXISTS (
              SELECT 1
              FROM contact_message_recipients cmr
              INNER JOIN users u ON u.id = cmr.user_id
              WHERE cmr.message_id = cm.id
                AND u.userType = 'admin'
          )
      )
      LIMIT 1
    ";
    $accessStmt = $conn->prepare($threadAccessSql);
    $accessStmt->bind_param('is', $messageId, $userEmail);
} else {
    $threadAccessSql .= "
      AND (
          cm.user_email = ?
          OR cm.user_name = ?
          OR EXISTS (
              SELECT 1
              FROM contact_message_recipients cmr
              WHERE cmr.message_id = cm.id
                AND LOWER(cmr.recipient_email) = LOWER(?)
          )
      )
      LIMIT 1
    ";
    $accessStmt = $conn->prepare($threadAccessSql);
    $accessStmt->bind_param('isss', $messageId, $userEmail, $userName, $userEmail);
}

$accessStmt->execute();
$thread = db_stmt_fetch_one_assoc($accessStmt);
$accessStmt->close();

if (!$thread) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You are not allowed to react to this conversation.']);
    exit;
}

if ($replyId !== null && $replyId > 0) {
    $replyStmt = $conn->prepare('SELECT id FROM contact_replies WHERE id = ? AND message_id = ? LIMIT 1');
    $replyStmt->bind_param('ii', $replyId, $messageId);
    $replyStmt->execute();
    $replyRow = db_stmt_fetch_one_assoc($replyStmt);
    $replyStmt->close();

    if (!$replyRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Reply not found.']);
        exit;
    }
}

$existingSql = 'SELECT id FROM message_reactions WHERE message_id = ? AND target_key = ? AND user_id = ? AND emoji = ? LIMIT 1';
$existingStmt = $conn->prepare($existingSql);
$existingStmt->bind_param('isis', $messageId, $targetKey, $userId, $emoji);
$existingStmt->execute();
$existing = db_stmt_fetch_one_assoc($existingStmt);
$existingStmt->close();

$reacted = false;
if ($existing) {
    $deleteStmt = $conn->prepare('DELETE FROM message_reactions WHERE id = ? LIMIT 1');
    $reactionId = (int) ($existing['id'] ?? 0);
    $deleteStmt->bind_param('i', $reactionId);
    $deleteStmt->execute();
    $deleteStmt->close();
} else {
    $insertSql = 'INSERT INTO message_reactions (message_id, reply_id, target_key, user_id, emoji) VALUES (?, ?, ?, ?, ?)';
    $insertStmt = $conn->prepare($insertSql);
    $insertReplyId = $replyId !== null && $replyId > 0 ? $replyId : null;
    $insertStmt->bind_param('iisis', $messageId, $insertReplyId, $targetKey, $userId, $emoji);
    $insertStmt->execute();
    $insertStmt->close();
    $reacted = true;
}

$summary = mailboxFetchReactionSummary($conn, $messageId, $replyId > 0 ? $replyId : null, $userId);

kodus_socket_broadcast('kodus.mailbox', 'mail.changed', [
    'action' => 'reaction_toggled',
    'message_id' => $messageId,
    'reply_id' => $replyId > 0 ? $replyId : null,
    'actor_id' => $userId,
]);

echo json_encode([
    'success' => true,
    'reacted' => $reacted,
    'summary' => $summary,
]);
