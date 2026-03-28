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
$replyText = trim((string) ($_POST['reply'] ?? ''));

if ($replyText === '') {
    security_send_json(['success' => false, 'error' => 'Reply text cannot be empty.'], 400);
}

$stmt = $conn->prepare("
    SELECT r.id, r.user_id, r.deleted_for_everyone_at, cm.user_email, cm.recipient
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
    security_send_json(['success' => false, 'error' => 'Unable to prepare reply edit request.'], 500);
}

$stmt->bind_param('isssss', $replyId, $userType, $userEmail, $userType, $userEmail, $userEmail);
$stmt->execute();
$reply = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reply) {
    security_send_json(['success' => false, 'error' => 'Reply not found or access denied.'], 404);
}

if ((int) $reply['user_id'] !== $userId) {
    security_send_json(['success' => false, 'error' => 'Only the reply sender can edit this reply.'], 403);
}

if (!empty($reply['deleted_for_everyone_at'])) {
    security_send_json(['success' => false, 'error' => 'Deleted replies can no longer be edited.'], 400);
}

$updateStmt = $conn->prepare("
    UPDATE contact_replies
    SET reply = ?
    WHERE id = ?
    LIMIT 1
");

if (!$updateStmt) {
    security_send_json(['success' => false, 'error' => 'Unable to update the reply right now.'], 500);
}

$updateStmt->bind_param('si', $replyText, $replyId);
$updateStmt->execute();
$updateStmt->close();

security_send_json(['success' => true, 'reply' => $replyText]);
