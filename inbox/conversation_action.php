<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/mailbox_helpers.php';
require_once __DIR__ . '/../socket_helpers.php';

header('Content-Type: application/json; charset=utf-8');

security_require_method(['POST']);
security_require_csrf_token();
mailboxEnsureSchema($conn);

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userType = $_SESSION['user_type'] ?? null;
$userEmail = $_SESSION['email'] ?? null;
$userName = trim((string) ($_SESSION['username'] ?? ''));
$actorName = $userName !== '' ? $userName : 'Someone';
$messageId = (int) ($_POST['message_id'] ?? 0);
$action = strtolower(trim((string) ($_POST['action'] ?? '')));

if ($userId <= 0 || $messageId <= 0 || $action === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing conversation action.']);
    exit;
}

if (!mailboxCanAccessMessage($conn, $messageId, $userType, $userEmail, $userName)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You cannot manage this conversation.']);
    exit;
}

$threadStmt = $conn->prepare("SELECT id, conversation_type FROM contact_messages WHERE id = ? LIMIT 1");
$threadStmt->bind_param('i', $messageId);
$threadStmt->execute();
$thread = db_stmt_fetch_one_assoc($threadStmt);
$threadStmt->close();

if (!$thread) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Conversation not found.']);
    exit;
}

$isGroup = mailboxIsGroupThread($thread);
$broadcastAction = $action;
$broadcastReceiverIds = [];

try {
    if ($action === 'delete') {
        if ($isGroup) {
            $stmt = $conn->prepare("
                UPDATE contact_message_recipients
                SET hidden_at = NOW()
                WHERE message_id = ? AND user_id = ?
            ");
            $stmt->bind_param('ii', $messageId, $userId);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("
                INSERT INTO message_reads (message_id, user_id, is_read, is_trashed, read_at, trashed_at)
                VALUES (?, ?, 1, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE is_trashed = 1, trashed_at = NOW()
            ");
            $stmt->bind_param('ii', $messageId, $userId);
            $stmt->execute();
            $stmt->close();
        }
        $broadcastAction = 'conversation_hidden';
    } elseif ($action === 'mute' || $action === 'unmute') {
        if (!$isGroup) {
            throw new RuntimeException('Only group chats can be muted here.');
        }
        $mutedAtSql = $action === 'mute' ? 'NOW()' : 'NULL';
        $stmt = $conn->prepare("
            UPDATE contact_message_recipients
            SET muted_at = {$mutedAtSql}
            WHERE message_id = ? AND user_id = ? AND left_at IS NULL
        ");
        $stmt->bind_param('ii', $messageId, $userId);
        $stmt->execute();
        $stmt->close();
        $broadcastAction = $action === 'mute' ? 'group_muted' : 'group_unmuted';
    } elseif ($action === 'leave') {
        if (!$isGroup) {
            throw new RuntimeException('Only group chats can be left.');
        }
        $stmt = $conn->prepare("
            UPDATE contact_message_recipients
            SET left_at = NOW(), muted_at = NULL
            WHERE message_id = ? AND user_id = ? AND left_at IS NULL
        ");
        $stmt->bind_param('ii', $messageId, $userId);
        $stmt->execute();
        $leftGroup = $stmt->affected_rows > 0;
        $stmt->close();
        if ($leftGroup) {
            mailboxCreateSystemReply(
                $conn,
                $messageId,
                $userId,
                $actorName . ' left the group.',
                'group_left'
            );
        }
        $broadcastAction = 'group_left';
    } elseif ($action === 'add_member') {
        if (!$isGroup || !mailboxCanParticipateInThread($conn, $messageId, $userId)) {
            throw new RuntimeException('Only current group members can add members.');
        }

        $memberRaw = $_POST['members'] ?? [];
        if (!is_array($memberRaw)) {
            $memberRaw = [$memberRaw];
        }

        $memberIds = array_values(array_unique(array_filter(array_map(static function ($value): int {
            if (is_numeric($value)) {
                return (int) $value;
            }
            if (is_string($value) && preg_match('/^user_(\d+)$/', $value, $matches)) {
                return (int) $matches[1];
            }
            return 0;
        }, $memberRaw), static fn(int $id): bool => $id > 0)));

        if ($memberIds === []) {
            throw new RuntimeException('Choose at least one member to add.');
        }

        $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
        $types = str_repeat('i', count($memberIds));
        $userStmt = $conn->prepare("SELECT id, email, username, first_name FROM users WHERE id IN ({$placeholders})");
        if (!$userStmt) {
            throw new RuntimeException('Unable to validate selected members.');
        }
        $userStmt->bind_param($types, ...$memberIds);
        $userStmt->execute();
        $users = db_stmt_fetch_all_assoc($userStmt);
        $userStmt->close();

        if ($users === []) {
            throw new RuntimeException('No selected members could be found.');
        }

        $upsertStmt = $conn->prepare("
            INSERT INTO contact_message_recipients (message_id, user_id, recipient_email, recipient_name)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                recipient_name = VALUES(recipient_name),
                left_at = NULL,
                hidden_at = NULL
        ");
        if (!$upsertStmt) {
            throw new RuntimeException('Unable to add members right now.');
        }

        $readStmt = $conn->prepare("
            INSERT INTO message_reads (message_id, user_id, is_read, read_at)
            VALUES (?, ?, 0, NULL)
            ON DUPLICATE KEY UPDATE is_trashed = 0, trashed_at = NULL
        ");

        $addedIds = [];
        $addedNames = [];
        foreach ($users as $row) {
            $memberId = (int) ($row['id'] ?? 0);
            $email = trim((string) ($row['email'] ?? ''));
            $name = mailboxDisplayName($row, $email);
            if ($memberId <= 0 || $email === '') {
                continue;
            }

            $upsertStmt->bind_param('iiss', $messageId, $memberId, $email, $name);
            $upsertStmt->execute();
            if ($upsertStmt->affected_rows > 0) {
                $addedIds[] = $memberId;
                $addedNames[] = $name !== '' ? $name : $email;
            }

            if ($readStmt) {
                $readStmt->bind_param('ii', $messageId, $memberId);
                $readStmt->execute();
            }
        }
        $upsertStmt->close();
        if ($readStmt) {
            $readStmt->close();
        }

        if ($addedIds === []) {
            throw new RuntimeException('No members were added.');
        }

        mailboxCreateSystemReply(
            $conn,
            $messageId,
            $userId,
            $actorName . ' added ' . implode(', ', $addedNames) . ' to the group.',
            'group_member_added'
        );

        $broadcastReceiverIds = $addedIds;
        $broadcastAction = 'group_member_added';
    } elseif ($action === 'remove_member') {
        if (!$isGroup || !mailboxCanParticipateInThread($conn, $messageId, $userId)) {
            throw new RuntimeException('Only current group members can remove members.');
        }

        $memberId = (int) ($_POST['member_id'] ?? 0);
        if ($memberId <= 0) {
            throw new RuntimeException('Choose a member to remove.');
        }
        if ($memberId === $userId) {
            throw new RuntimeException('Use Leave group to remove yourself.');
        }

        $memberName = 'Someone';
        $memberStmt = $conn->prepare("
            SELECT COALESCE(NULLIF(SUBSTRING_INDEX(TRIM(u.first_name), ' ', 1), ''), NULLIF(u.username, ''), COALESCE(NULLIF(cmr.recipient_name, ''), cmr.recipient_email)) AS display_name
            FROM contact_message_recipients cmr
            LEFT JOIN users u ON u.id = cmr.user_id
            WHERE cmr.message_id = ? AND cmr.user_id = ?
            LIMIT 1
        ");
        if ($memberStmt) {
            $memberStmt->bind_param('ii', $messageId, $memberId);
            $memberStmt->execute();
            $memberRow = db_stmt_fetch_one_assoc($memberStmt);
            $memberStmt->close();
            $memberName = trim((string) ($memberRow['display_name'] ?? '')) ?: $memberName;
        }

        $stmt = $conn->prepare("
            UPDATE contact_message_recipients
            SET left_at = NOW(), muted_at = NULL
            WHERE message_id = ?
              AND user_id = ?
              AND left_at IS NULL
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to remove member right now.');
        }
        $stmt->bind_param('ii', $messageId, $memberId);
        $stmt->execute();
        $updated = $stmt->affected_rows > 0;
        $stmt->close();

        if (!$updated) {
            throw new RuntimeException('Unable to remove member right now.');
        }

        mailboxCreateSystemReply(
            $conn,
            $messageId,
            $userId,
            $actorName . ' removed ' . $memberName . ' from the group.',
            'group_member_removed'
        );

        $broadcastReceiverIds = [$memberId];
        $broadcastAction = 'group_member_removed';
    } elseif ($action === 'update_group') {
        if (!$isGroup || !mailboxCanParticipateInThread($conn, $messageId, $userId)) {
            throw new RuntimeException('Only current group members can update group details.');
        }

        $groupName = trim((string) ($_POST['group_name'] ?? ''));
        if ($groupName === '') {
            throw new RuntimeException('Group name is required.');
        }

        $photoSql = '';
        $photoParam = null;
        if (!empty($_FILES['group_photo']['name']) && is_uploaded_file($_FILES['group_photo']['tmp_name'])) {
            $tmp = $_FILES['group_photo']['tmp_name'];
            $mime = security_detect_upload_mime($tmp);
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/avif' => 'avif',
            ];
            if (!isset($allowed[$mime]) || (int) ($_FILES['group_photo']['size'] ?? 0) > 5 * 1024 * 1024) {
                throw new RuntimeException('Upload a JPG, PNG, GIF, WEBP, or AVIF photo up to 5 MB.');
            }
            $uploadDir = __DIR__ . '/uploads/group_photos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $photoParam = time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
            if (!move_uploaded_file($tmp, $uploadDir . $photoParam)) {
                throw new RuntimeException('Unable to save the group photo.');
            }
            $photoSql = ', group_photo = ?';
        }

        if ($photoSql !== '') {
            $stmt = $conn->prepare("UPDATE contact_messages SET group_name = ? {$photoSql} WHERE id = ?");
            $stmt->bind_param('ssi', $groupName, $photoParam, $messageId);
        } else {
            $stmt = $conn->prepare("UPDATE contact_messages SET group_name = ? WHERE id = ?");
            $stmt->bind_param('si', $groupName, $messageId);
        }
        $stmt->execute();
        $stmt->close();
        $broadcastAction = 'group_updated';
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown conversation action.']);
        exit;
    }
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
    exit;
}

kodus_socket_broadcast('kodus.mailbox', 'mail.changed', [
    'action' => $broadcastAction,
    'message_id' => $messageId,
    'actor_id' => $userId,
    'receiver_ids' => $broadcastReceiverIds,
]);

echo json_encode(['success' => true, 'action' => $broadcastAction]);
