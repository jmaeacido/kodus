<?php
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/mailbox_helpers.php';

security_bootstrap_session();
header('Content-Type: application/json');

security_require_method(['POST']);
security_require_csrf_token();
mailboxEnsureSchema($conn);

if (!isset($_SESSION['user_id']) || empty($_POST['reply_id'])) {
    security_send_json(['success' => false, 'error' => 'Invalid request.'], 400);
}

$userId = (int) $_SESSION['user_id'];
$userType = $_SESSION['user_type'] ?? null;
$userEmail = (string) ($_SESSION['email'] ?? '');
$replyId = (int) $_POST['reply_id'];

$stmt = $conn->prepare("
    SELECT r.id, r.user_id, r.message_id, r.deleted_for_everyone_at, cm.user_email, cm.recipient
    FROM contact_replies r
    JOIN contact_messages cm ON cm.id = r.message_id
    WHERE r.id = ?
      AND (
          (? = 'admin' AND (
              cm.user_email = ?
              OR EXISTS (
                  SELECT 1
                  FROM users u
                  WHERE FIND_IN_SET(u.email, cm.recipient) > 0
                    AND u.userType = 'admin'
              )
          ))
          OR (? <> 'admin' AND (cm.user_email = ? OR FIND_IN_SET(?, cm.recipient) > 0))
      )
    LIMIT 1
");

if (!$stmt) {
    security_send_json(['success' => false, 'error' => 'Unable to prepare reply delete request.'], 500);
}

$stmt->bind_param('isssss', $replyId, $userType, $userEmail, $userType, $userEmail, $userEmail);
$stmt->execute();
$reply = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reply) {
    security_send_json(['success' => false, 'error' => 'Reply not found or access denied.'], 404);
}

$isSender = (int) $reply['user_id'] === $userId;
$isAdmin = $userType === 'admin';

if (!$isSender && !$isAdmin) {
    security_send_json(['success' => false, 'error' => 'Only the reply sender or an admin can delete this reply for everyone.'], 403);
}

if (!empty($reply['deleted_for_everyone_at'])) {
    security_send_json(['success' => true, 'already_deleted' => true]);
}

$deleteStmt = $conn->prepare("
    UPDATE contact_replies
    SET reply = '[deleted]',
        attachment = NULL,
        deleted_for_everyone_at = NOW(),
        deleted_by_user_id = ?
    WHERE id = ?
    LIMIT 1
");

if (!$deleteStmt) {
    security_send_json(['success' => false, 'error' => 'Unable to delete the reply right now.'], 500);
}

$deleteStmt->bind_param('ii', $userId, $replyId);
$deleteStmt->execute();
$updated = $deleteStmt->affected_rows >= 0;
$deleteStmt->close();

if (!$updated) {
    security_send_json(['success' => false, 'error' => 'Unable to delete the reply right now.'], 500);
}

security_send_json(['success' => true]);
