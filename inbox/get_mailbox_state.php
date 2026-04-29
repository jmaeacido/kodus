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
$userName = trim((string) ($_SESSION['username'] ?? ''));
$folder = mailboxGetFolder($_GET['folder'] ?? 'inbox');
$folderPredicate = mailboxTrashPredicate($folder, 'mr');

if ($userType === 'admin') {
    $stateSql = "
        SELECT
            COUNT(*) AS folder_count,
            COALESCE(MAX(COALESCE(reply_summary.latest_reply_touch_at, cm.sent_at)), '') AS latest_touch_at,
            COALESCE(MAX(cm.id), 0) AS max_message_id,
            COALESCE(MAX(reply_summary.latest_reply_id), 0) AS max_reply_id,
            COALESCE(MAX(reaction_summary.latest_reaction_id), 0) AS max_reaction_id,
            COALESCE(SUM(reaction_summary.reaction_count), 0) AS reaction_count,
            COALESCE(SUM(member_summary.active_member_count), 0) AS active_member_count,
            COALESCE(SUM(member_summary.left_member_count), 0) AS left_member_count,
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
        LEFT JOIN (
            SELECT message_id, MAX(id) AS latest_reaction_id, COUNT(*) AS reaction_count
            FROM message_reactions
            GROUP BY message_id
        ) reaction_summary ON reaction_summary.message_id = cm.id
        LEFT JOIN (
            SELECT message_id,
                   SUM(CASE WHEN left_at IS NULL AND hidden_at IS NULL THEN 1 ELSE 0 END) AS active_member_count,
                   SUM(CASE WHEN left_at IS NOT NULL AND hidden_at IS NULL THEN 1 ELSE 0 END) AS left_member_count
            FROM contact_message_recipients
            GROUP BY message_id
        ) member_summary ON member_summary.message_id = cm.id
        LEFT JOIN message_reads mr
            ON cm.id = mr.message_id AND mr.user_id = ?
        WHERE (
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
          AND {$folderPredicate}
          AND " . mailboxVisibilityPredicate((int) $userId, 'cm', 'mr') . "
    ";
    $stateStmt = $conn->prepare($stateSql);
    $stateStmt->bind_param('is', $userId, $userEmail);

    $unreadSql = "
        SELECT COUNT(*) AS unread_count
        FROM contact_messages cm
        LEFT JOIN message_reads mr
            ON cm.id = mr.message_id AND mr.user_id = ?
        WHERE (
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
          AND COALESCE(mr.is_trashed, 0) = 0
          AND " . mailboxVisibilityPredicate((int) $userId, 'cm', 'mr') . "
          AND NOT EXISTS (
              SELECT 1 FROM contact_message_recipients muted
              WHERE muted.message_id = cm.id AND muted.user_id = ?
                AND (muted.muted_at IS NOT NULL OR muted.left_at IS NOT NULL)
          )
          AND (mr.is_read IS NULL OR mr.is_read = 0)
    ";
    $unreadStmt = $conn->prepare($unreadSql);
    $unreadStmt->bind_param('isi', $userId, $userEmail, $userId);
} else {
    $stateSql = "
        SELECT
            COUNT(*) AS folder_count,
            COALESCE(MAX(COALESCE(reply_summary.latest_reply_touch_at, cm.sent_at)), '') AS latest_touch_at,
            COALESCE(MAX(cm.id), 0) AS max_message_id,
            COALESCE(MAX(reply_summary.latest_reply_id), 0) AS max_reply_id,
            COALESCE(MAX(reaction_summary.latest_reaction_id), 0) AS max_reaction_id,
            COALESCE(SUM(reaction_summary.reaction_count), 0) AS reaction_count,
            COALESCE(SUM(member_summary.active_member_count), 0) AS active_member_count,
            COALESCE(SUM(member_summary.left_member_count), 0) AS left_member_count,
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
        LEFT JOIN (
            SELECT message_id, MAX(id) AS latest_reaction_id, COUNT(*) AS reaction_count
            FROM message_reactions
            GROUP BY message_id
        ) reaction_summary ON reaction_summary.message_id = cm.id
        LEFT JOIN (
            SELECT message_id,
                   SUM(CASE WHEN left_at IS NULL AND hidden_at IS NULL THEN 1 ELSE 0 END) AS active_member_count,
                   SUM(CASE WHEN left_at IS NOT NULL AND hidden_at IS NULL THEN 1 ELSE 0 END) AS left_member_count
            FROM contact_message_recipients
            GROUP BY message_id
        ) member_summary ON member_summary.message_id = cm.id
        LEFT JOIN message_reads mr
            ON cm.id = mr.message_id AND mr.user_id = ?
        WHERE (cm.user_email = ? OR cm.user_name = ? OR EXISTS (
            SELECT 1
            FROM contact_message_recipients cmr
            WHERE cmr.message_id = cm.id
              AND LOWER(cmr.recipient_email) = LOWER(?)
        ) OR COALESCE(cm.conversation_type, 'direct') = 'group')
          AND {$folderPredicate}
          AND " . mailboxVisibilityPredicate((int) $userId, 'cm', 'mr') . "
    ";
    $stateStmt = $conn->prepare($stateSql);
    $stateStmt->bind_param('isss', $userId, $userEmail, $userName, $userEmail);

    $unreadSql = "
        SELECT COUNT(*) AS unread_count
        FROM contact_messages cm
        LEFT JOIN message_reads mr
            ON cm.id = mr.message_id AND mr.user_id = ?
        WHERE (cm.user_email = ? OR cm.user_name = ? OR EXISTS (
            SELECT 1
            FROM contact_message_recipients cmr
            WHERE cmr.message_id = cm.id
              AND LOWER(cmr.recipient_email) = LOWER(?)
        ) OR COALESCE(cm.conversation_type, 'direct') = 'group')
          AND COALESCE(mr.is_trashed, 0) = 0
          AND " . mailboxVisibilityPredicate((int) $userId, 'cm', 'mr') . "
          AND NOT EXISTS (
              SELECT 1 FROM contact_message_recipients muted
              WHERE muted.message_id = cm.id AND muted.user_id = ?
                AND (muted.muted_at IS NOT NULL OR muted.left_at IS NOT NULL)
          )
          AND (mr.is_read IS NULL OR mr.is_read = 0)
    ";
    $unreadStmt = $conn->prepare($unreadSql);
    $unreadStmt->bind_param('isssi', $userId, $userEmail, $userName, $userEmail, $userId);
}

$stateStmt->execute();
$state = db_stmt_fetch_one_assoc($stateStmt) ?: [];
$stateStmt->close();

$unreadStmt->execute();
$unreadRow = db_stmt_fetch_one_assoc($unreadStmt) ?: [];
$unreadStmt->close();

$payload = [
    'folder' => $folder,
    'folder_count' => (int) ($state['folder_count'] ?? 0),
    'latest_touch_at' => (string) ($state['latest_touch_at'] ?? ''),
    'max_message_id' => (int) ($state['max_message_id'] ?? 0),
    'max_reply_id' => (int) ($state['max_reply_id'] ?? 0),
    'max_reaction_id' => (int) ($state['max_reaction_id'] ?? 0),
    'reaction_count' => (int) ($state['reaction_count'] ?? 0),
    'active_member_count' => (int) ($state['active_member_count'] ?? 0),
    'left_member_count' => (int) ($state['left_member_count'] ?? 0),
    'latest_read_at' => (string) ($state['latest_read_at'] ?? ''),
    'latest_trashed_at' => (string) ($state['latest_trashed_at'] ?? ''),
    'unread_count' => (int) ($unreadRow['unread_count'] ?? 0),
];

security_send_json([
    'state_token' => hash('sha256', json_encode($payload)),
    'unread_count' => $payload['unread_count'],
    'folder' => $folder,
]);
