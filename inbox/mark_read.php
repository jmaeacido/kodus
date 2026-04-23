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

if ($userType === 'admin') {
    $accessStmt = $conn->prepare("
        SELECT cm.id
        FROM contact_messages cm
        WHERE cm.id = ?
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
    ");
    $accessStmt->bind_param("is", $messageId, $userEmail);
} else {
    $accessStmt = $conn->prepare("
        SELECT id
        FROM contact_messages
        WHERE id = ?
          AND (user_email = ? OR user_name = ? OR EXISTS (
              SELECT 1
              FROM contact_message_recipients cmr
              WHERE cmr.message_id = contact_messages.id
                AND LOWER(cmr.recipient_email) = LOWER(?)
          ))
        LIMIT 1
    ");
    $accessStmt->bind_param("isss", $messageId, $userEmail, $userName, $userEmail);
}

$accessStmt->execute();
$isAllowed = (bool) db_stmt_fetch_one_assoc($accessStmt);
$accessStmt->close();

if (!$isAllowed) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Mark the original message (thread) as read for this user
$stmt = $conn->prepare("
    INSERT INTO message_reads (message_id, user_id, is_read, read_at)
    VALUES (?, ?, 1, NOW())
    ON DUPLICATE KEY UPDATE is_read = 1, read_at = NOW()
");
if ($stmt) {
    $stmt->bind_param("ii", $messageId, $userId);
    $stmt->execute();
    $stmt->close();
}

kodus_socket_broadcast('kodus.mailbox', 'mail.changed', [
    'action' => 'message_read',
    'message_id' => $messageId,
    'actor_id' => (int) $userId,
]);

echo json_encode(['success' => true]);
