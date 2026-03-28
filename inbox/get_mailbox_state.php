<?php
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/mailbox_helpers.php';

security_bootstrap_session();
header('Content-Type: application/json');
mailboxEnsureSchema($conn);

if (!isset($_SESSION['user_id'])) {
    security_send_json([
        'state_token' => '',
        'unread_count' => 0,
        'folder' => 'inbox',
    ]);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userType = $_SESSION['user_type'] ?? null;
$userEmail = (string) ($_SESSION['email'] ?? '');
$folder = mailboxGetFolder($_GET['folder'] ?? 'inbox');
$folderPredicate = mailboxTrashPredicate($folder, 'mr');

if ($userType === 'admin') {
    $stateSql = "
        SELECT
            COUNT(*) AS folder_count,
            COALESCE(MAX(COALESCE(reply_summary.latest_reply_touch_at, cm.sent_at)), '') AS latest_touch_at,
            COALESCE(MAX(cm.id), 0) AS max_message_id,
            COALESCE(MAX(reply_summary.latest_reply_id), 0) AS max_reply_id,
            COALESCE(MAX(mr.read_at), '') AS latest_read_at,
            COALESCE(MAX(mr.trashed_at), '') AS latest_trashed_at
        FROM contact_messages cm
        LEFT JOIN (
            SELECT
                message_id,
                MAX(COALESCE(updated_at, deleted_for_everyone_at, sent_at)) AS latest_reply_touch_at,
                MAX(id) AS latest_reply_id
            FROM contact_replies
            GROUP BY message_id
        ) reply_summary ON reply_summary.message_id = cm.id
        LEFT JOIN message_reads mr
            ON cm.id = mr.message_id AND mr.user_id = ?
        WHERE (
            cm.user_email = ?
            OR EXISTS (
                SELECT 1
                FROM users u
                WHERE FIND_IN_SET(u.email, cm.recipient) > 0
                  AND u.userType = 'admin'
            )
        )
          AND {$folderPredicate}
    ";
    $stateStmt = $conn->prepare($stateSql);
    $stateStmt->bind_param('is', $userId, $userEmail);

    $unreadSql = "
        SELECT COUNT(*) AS unread_count
        FROM contact_messages cm
        LEFT JOIN message_reads mr
            ON cm.id = mr.message_id AND mr.user_id = ?
        WHERE (
            cm.user_email = ?
            OR EXISTS (
                SELECT 1
                FROM users u
                WHERE FIND_IN_SET(u.email, cm.recipient) > 0
                  AND u.userType = 'admin'
            )
        )
          AND COALESCE(mr.is_trashed, 0) = 0
          AND (mr.is_read IS NULL OR mr.is_read = 0)
    ";
    $unreadStmt = $conn->prepare($unreadSql);
    $unreadStmt->bind_param('is', $userId, $userEmail);
} else {
    $stateSql = "
        SELECT
            COUNT(*) AS folder_count,
            COALESCE(MAX(COALESCE(reply_summary.latest_reply_touch_at, cm.sent_at)), '') AS latest_touch_at,
            COALESCE(MAX(cm.id), 0) AS max_message_id,
            COALESCE(MAX(reply_summary.latest_reply_id), 0) AS max_reply_id,
            COALESCE(MAX(mr.read_at), '') AS latest_read_at,
            COALESCE(MAX(mr.trashed_at), '') AS latest_trashed_at
        FROM contact_messages cm
        LEFT JOIN (
            SELECT
                message_id,
                MAX(COALESCE(updated_at, deleted_for_everyone_at, sent_at)) AS latest_reply_touch_at,
                MAX(id) AS latest_reply_id
            FROM contact_replies
            GROUP BY message_id
        ) reply_summary ON reply_summary.message_id = cm.id
        LEFT JOIN message_reads mr
            ON cm.id = mr.message_id AND mr.user_id = ?
        WHERE (cm.user_email = ? OR FIND_IN_SET(?, cm.recipient) > 0)
          AND {$folderPredicate}
    ";
    $stateStmt = $conn->prepare($stateSql);
    $stateStmt->bind_param('iss', $userId, $userEmail, $userEmail);

    $unreadSql = "
        SELECT COUNT(*) AS unread_count
        FROM contact_messages cm
        LEFT JOIN message_reads mr
            ON cm.id = mr.message_id AND mr.user_id = ?
        WHERE (cm.user_email = ? OR FIND_IN_SET(?, cm.recipient) > 0)
          AND COALESCE(mr.is_trashed, 0) = 0
          AND (mr.is_read IS NULL OR mr.is_read = 0)
    ";
    $unreadStmt = $conn->prepare($unreadSql);
    $unreadStmt->bind_param('iss', $userId, $userEmail, $userEmail);
}

$stateStmt->execute();
$state = $stateStmt->get_result()->fetch_assoc() ?: [];
$stateStmt->close();

$unreadStmt->execute();
$unreadRow = $unreadStmt->get_result()->fetch_assoc() ?: [];
$unreadStmt->close();

$payload = [
    'folder' => $folder,
    'folder_count' => (int) ($state['folder_count'] ?? 0),
    'latest_touch_at' => (string) ($state['latest_touch_at'] ?? ''),
    'max_message_id' => (int) ($state['max_message_id'] ?? 0),
    'max_reply_id' => (int) ($state['max_reply_id'] ?? 0),
    'latest_read_at' => (string) ($state['latest_read_at'] ?? ''),
    'latest_trashed_at' => (string) ($state['latest_trashed_at'] ?? ''),
    'unread_count' => (int) ($unreadRow['unread_count'] ?? 0),
];

security_send_json([
    'state_token' => hash('sha256', json_encode($payload)),
    'unread_count' => $payload['unread_count'],
    'folder' => $folder,
]);
