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

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = strtolower(trim((string) ($_POST['action'] ?? '')));
$rawIds = $_POST['message_ids'] ?? ($_POST['message_ids[]'] ?? []);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$userType = (string) ($_SESSION['user_type'] ?? '');
$userEmail = (string) ($_SESSION['email'] ?? '');
$userName = trim((string) ($_SESSION['username'] ?? ''));

if (!in_array($action, ['mark_read', 'delete', 'restore', 'delete_permanent'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid bulk action.']);
    exit;
}

if (!is_array($rawIds)) {
    $rawIds = [$rawIds];
}

$messageIds = array_values(array_unique(array_filter(array_map(static function ($value) {
    return (int) $value;
}, $rawIds), static function ($value) {
    return $value > 0;
})));

if ($messageIds === []) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Select at least one conversation first.']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($messageIds), '?'));
$types = str_repeat('i', count($messageIds));

if ($userType === 'admin') {
    $sql = "
        SELECT cm.id, cm.user_email, cm.user_name
        FROM contact_messages cm
        WHERE cm.id IN ($placeholders)
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
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Unable to prepare the bulk action request.']);
        exit;
    }

    $params = array_merge($messageIds, [$userEmail]);
    $bindTypes = $types . 's';
} else {
    $sql = "
        SELECT cm.id, cm.user_email, cm.user_name
        FROM contact_messages cm
        WHERE cm.id IN ($placeholders)
          AND (
              cm.user_email = ?
              OR cm.user_name = ?
              OR EXISTS (
                  SELECT 1
                  FROM contact_message_recipients cmr
                  WHERE cmr.message_id = cm.id
                    AND (cmr.user_id = ? OR LOWER(cmr.recipient_email) = LOWER(?))
              )
          )
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Unable to prepare the bulk action request.']);
        exit;
    }

    $params = array_merge($messageIds, [$userEmail, $userName, $userId, $userEmail]);
    $bindTypes = $types . 'ssis';
}

$bindValues = [$bindTypes];
foreach ($params as $param) {
    $bindValues[] = $param;
}
$references = [];
foreach ($bindValues as $index => $value) {
    $references[$index] = &$bindValues[$index];
}
call_user_func_array([$stmt, 'bind_param'], $references);
$stmt->execute();
$allowedRows = db_stmt_fetch_all_assoc($stmt);
$stmt->close();

$allowedIds = array_values(array_map(static function ($row) {
    return (int) ($row['id'] ?? 0);
}, $allowedRows));

if ($allowedIds === []) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You can only bulk update conversations that belong to your mailbox.']);
    exit;
}

$processedIds = [];
$conn->begin_transaction();

try {
    if ($action === 'mark_read') {
        $markStmt = $conn->prepare("
            INSERT INTO message_reads (message_id, user_id, is_read, read_at, last_read_reply_id)
            SELECT ?, ?, 1, NOW(), COALESCE(MAX(cr.id), 0)
            FROM contact_replies cr
            WHERE cr.message_id = ?
            ON DUPLICATE KEY UPDATE is_read = 1, read_at = NOW(), last_read_reply_id = VALUES(last_read_reply_id)
        ");
        if (!$markStmt) {
            throw new RuntimeException('Unable to prepare the mark read statement.');
        }

        foreach ($allowedIds as $messageId) {
            $markStmt->bind_param('iii', $messageId, $userId, $messageId);
            if (!$markStmt->execute()) {
                throw new RuntimeException('Unable to mark the selected conversations as read.');
            }
            $processedIds[] = $messageId;
        }
        $markStmt->close();
    } elseif ($action === 'delete') {
        $trashStmt = $conn->prepare("
            INSERT INTO message_reads (message_id, user_id, is_read, is_trashed, read_at, trashed_at)
            VALUES (?, ?, 0, 1, NULL, NOW())
            ON DUPLICATE KEY UPDATE is_trashed = 1, trashed_at = NOW()
        ");
        if (!$trashStmt) {
            throw new RuntimeException('Unable to prepare the bulk delete statement.');
        }

        foreach ($allowedIds as $messageId) {
            $trashStmt->bind_param('ii', $messageId, $userId);
            if (!$trashStmt->execute()) {
                throw new RuntimeException('Unable to move the selected conversations to Trash.');
            }
            $processedIds[] = $messageId;
        }
        $trashStmt->close();
    } elseif ($action === 'restore') {
        $restoreStmt = $conn->prepare("
            INSERT INTO message_reads (message_id, user_id, is_read, is_trashed, read_at, trashed_at)
            VALUES (?, ?, 0, 0, NULL, NULL)
            ON DUPLICATE KEY UPDATE is_trashed = 0, trashed_at = NULL
        ");
        if (!$restoreStmt) {
            throw new RuntimeException('Unable to prepare the restore statement.');
        }

        foreach ($allowedIds as $messageId) {
            $restoreStmt->bind_param('ii', $messageId, $userId);
            if (!$restoreStmt->execute()) {
                throw new RuntimeException('Unable to restore the selected conversations.');
            }
            $processedIds[] = $messageId;
        }
        $restoreStmt->close();
    } else {
        $permanentRows = array_filter($allowedRows, static function ($row) use ($userType, $userEmail, $userName) {
            if ($userType === 'admin') {
                return true;
            }

            return mailboxOwnerMatchesCurrentUser(
                (string) ($row['user_email'] ?? ''),
                (string) ($row['user_name'] ?? ''),
                $userEmail,
                $userName
            );
        });

        if ($permanentRows === []) {
            throw new RuntimeException('Only the original sender or an admin can permanently delete these conversations.');
        }

        foreach ($permanentRows as $row) {
            $messageId = (int) ($row['id'] ?? 0);
            if ($messageId <= 0) {
                continue;
            }

            if (mailboxDeleteConversationForEveryone($conn, $messageId)) {
                $processedIds[] = $messageId;
            }
        }
    }

    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
    exit;
}

foreach ($processedIds as $messageId) {
    kodus_socket_broadcast('kodus.mailbox', 'mail.changed', [
        'action' => $action === 'mark_read'
            ? 'message_read'
            : ($action === 'restore'
                ? 'message_restored'
                : ($action === 'delete_permanent' ? 'message_deleted' : 'message_trashed')),
        'message_id' => $messageId,
        'actor_id' => $userId,
    ]);
}

$skippedCount = max(0, count($messageIds) - count($processedIds));
$message = $action === 'mark_read'
    ? count($processedIds) . ' conversation' . (count($processedIds) === 1 ? ' was' : 's were') . ' marked as read.'
    : ($action === 'restore'
        ? count($processedIds) . ' conversation' . (count($processedIds) === 1 ? ' was' : 's were') . ' restored.'
        : ($action === 'delete_permanent'
            ? count($processedIds) . ' conversation' . (count($processedIds) === 1 ? ' was' : 's were') . ' deleted permanently.'
            : count($processedIds) . ' conversation' . (count($processedIds) === 1 ? ' was' : 's were') . ' moved to Trash.'));

if ($skippedCount > 0) {
    $message .= ' ' . $skippedCount . ' selection' . ($skippedCount === 1 ? ' was' : 's were') . ' skipped because access was not allowed.';
}

echo json_encode([
    'success' => true,
    'processed_ids' => $processedIds,
    'processed_count' => count($processedIds),
    'skipped_count' => $skippedCount,
    'message' => $message,
]);
