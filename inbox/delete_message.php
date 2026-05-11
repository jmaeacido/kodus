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

$userId = (int) $_SESSION['user_id'];
$messageId = (int) $_POST['id'];
$userType = $_SESSION['user_type'] ?? null;
$userEmail = $_SESSION['email'] ?? null;
$userName = trim((string) ($_SESSION['username'] ?? ''));
$scope = strtolower(trim((string) ($_POST['scope'] ?? 'self')));

if (!in_array($scope, ['self', 'everyone', 'restore'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid delete scope']);
    exit;
}

if ($userType === 'admin') {
    $accessStmt = $conn->prepare("
        SELECT cm.id, cm.attachment, cm.user_email
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
        SELECT id, attachment, user_email, user_name
        FROM contact_messages
        WHERE id = ?
          AND (user_email = ? OR user_name = ? OR EXISTS (
              SELECT 1
              FROM contact_message_recipients cmr
              WHERE cmr.message_id = contact_messages.id
                AND (cmr.user_id = ? OR LOWER(cmr.recipient_email) = LOWER(?))
          ))
        LIMIT 1
    ");
    $accessStmt->bind_param("issis", $messageId, $userEmail, $userName, $userId, $userEmail);
}

$accessStmt->execute();
$message = db_stmt_fetch_one_assoc($accessStmt);
$accessStmt->close();

if (!$message) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$canDeleteForEveryone = $userType === 'admin'
    || mailboxOwnerMatchesCurrentUser(
        (string) ($message['user_email'] ?? ''),
        (string) ($message['user_name'] ?? ''),
        (string) $userEmail,
        $userName
    );

$stateStmt = $conn->prepare("
    INSERT INTO message_reads (message_id, user_id, is_read, is_trashed, read_at, trashed_at)
    VALUES (?, ?, 0, ?, NULL, ?)
    ON DUPLICATE KEY UPDATE
        is_trashed = VALUES(is_trashed),
        trashed_at = VALUES(trashed_at)
");

if (!$stateStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to update mailbox state right now.']);
    exit;
}

if ($scope === 'self') {
    $isTrashed = 1;
    $trashedAt = date('Y-m-d H:i:s');
    $stateStmt->bind_param('iiis', $messageId, $userId, $isTrashed, $trashedAt);
    if (!$stateStmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Unable to move the conversation to Trash right now.']);
        $stateStmt->close();
        exit;
    }
    $stateStmt->close();
    kodus_socket_broadcast('kodus.mailbox', 'mail.changed', [
        'action' => 'message_trashed',
        'message_id' => $messageId,
        'actor_id' => $userId,
    ]);
    echo json_encode(['success' => true, 'scope' => 'self']);
    exit;
}

if ($scope === 'restore') {
    $stateStmt->close();
    $restoreStmt = $conn->prepare("
        INSERT INTO message_reads (message_id, user_id, is_read, is_trashed, read_at, trashed_at)
        VALUES (?, ?, 0, 0, NULL, NULL)
        ON DUPLICATE KEY UPDATE
            is_trashed = 0,
            trashed_at = NULL
    ");
    if (!$restoreStmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Unable to restore the conversation right now.']);
        exit;
    }
    $restoreStmt->bind_param('ii', $messageId, $userId);
    if (!$restoreStmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Unable to restore the conversation right now.']);
        $restoreStmt->close();
        exit;
    }
    $restoreStmt->close();
    kodus_socket_broadcast('kodus.mailbox', 'mail.changed', [
        'action' => 'message_restored',
        'message_id' => $messageId,
        'actor_id' => $userId,
    ]);
    echo json_encode(['success' => true, 'scope' => 'restore']);
    exit;
}

$stateStmt->close();

if (!$canDeleteForEveryone) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only the original sender or an admin can delete this conversation for everyone.']);
    exit;
}

$deleted = mailboxDeleteConversationForEveryone($conn, $messageId);

if (!$deleted) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to delete the message right now.']);
    exit;
}

kodus_socket_broadcast('kodus.mailbox', 'mail.changed', [
    'action' => 'message_deleted',
    'message_id' => $messageId,
    'actor_id' => $userId,
]);

echo json_encode(['success' => true]);
