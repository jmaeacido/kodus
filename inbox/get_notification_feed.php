<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../base_url.php';
require_once __DIR__ . '/mailbox_helpers.php';

header('Content-Type: application/json');
mailboxEnsureSchema($conn);

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'count' => 0,
        'items' => [],
    ]);
    exit;
}

function notification_avatar_url(array $row, string $baseUrl): string
{
    return avatar_resolve_url($row['picture'] ?? '', $row['sso_avatar_url'] ?? '', $baseUrl, dirname(__DIR__));
}

function notification_time_label(?string $sentAt): string
{
    if (!$sentAt) {
        return 'Unknown time';
    }

    $timestamp = strtotime($sentAt);
    if ($timestamp === false) {
        return 'Unknown time';
    }

    $seconds = time() - $timestamp;
    if ($seconds < 60) {
        return 'Just now';
    }

    $minutes = (int) floor($seconds / 60);
    if ($minutes < 60) {
        return $minutes . ' min' . ($minutes === 1 ? '' : 's') . ' ago';
    }

    $hours = (int) floor($minutes / 60);
    if ($hours < 24) {
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }

    return date('M d, H:i', $timestamp);
}

$userId = (int) $_SESSION['user_id'];
$userType = $_SESSION['user_type'] ?? null;
$userEmail = $_SESSION['email'] ?? '';
$userName = trim((string) ($_SESSION['username'] ?? ''));
$items = [];
$unreadCount = 0;

if ($userType === 'admin') {
    $countSql = "
        SELECT COUNT(*) AS unread
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
              WHERE muted.message_id = cm.id AND muted.user_id = ? AND (muted.muted_at IS NOT NULL OR muted.left_at IS NOT NULL)
          )
          AND (mr.is_read IS NULL OR mr.is_read = 0)
    ";
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param('isi', $userId, $userEmail, $userId);
    $countStmt->execute();
    $countResult = db_stmt_fetch_one_assoc($countStmt);
    $unreadCount = (int) ($countResult['unread'] ?? 0);
    $countStmt->close();

    $feedSql = "
        SELECT cm.id, cm.user_name, cm.user_email, cm.subject, cm.message, cm.conversation_type, cm.group_name, cm.group_photo,
               COALESCE(reply_summary.latest_reply_at, cm.sent_at) AS latest_activity_at,
               u.first_name, u.last_name, u.picture, u.sso_avatar_url
        FROM contact_messages cm
        LEFT JOIN (
            SELECT message_id, MAX(sent_at) AS latest_reply_at
            FROM contact_replies
            GROUP BY message_id
        ) reply_summary ON reply_summary.message_id = cm.id
        LEFT JOIN users u ON u.email = cm.user_email
        LEFT JOIN message_reads mr
          ON cm.id = mr.message_id AND mr.user_id = ?
        WHERE (
            COALESCE(cm.conversation_type, 'direct') = 'group'
            OR
            cm.user_email = ?
            OR EXISTS (
                SELECT 1
                FROM contact_message_recipients cmr
                INNER JOIN users ua ON ua.id = cmr.user_id
                WHERE cmr.message_id = cm.id
                  AND ua.userType = 'admin'
            )
        )
          AND COALESCE(mr.is_trashed, 0) = 0
          AND " . mailboxVisibilityPredicate((int) $userId, 'cm', 'mr') . "
          AND NOT EXISTS (
              SELECT 1 FROM contact_message_recipients muted
              WHERE muted.message_id = cm.id AND muted.user_id = ? AND (muted.muted_at IS NOT NULL OR muted.left_at IS NOT NULL)
          )
          AND (mr.is_read IS NULL OR mr.is_read = 0)
        ORDER BY latest_activity_at DESC, cm.id DESC
        LIMIT 5
    ";
    $feedStmt = $conn->prepare($feedSql);
    $feedStmt->bind_param('isi', $userId, $userEmail, $userId);
} else {
    $countSql = "
        SELECT COUNT(*) AS unread
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
              WHERE muted.message_id = cm.id AND muted.user_id = ? AND (muted.muted_at IS NOT NULL OR muted.left_at IS NOT NULL)
          )
          AND (mr.is_read IS NULL OR mr.is_read = 0)
    ";
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param('isssi', $userId, $userEmail, $userName, $userEmail, $userId);
    $countStmt->execute();
    $countResult = db_stmt_fetch_one_assoc($countStmt);
    $unreadCount = (int) ($countResult['unread'] ?? 0);
    $countStmt->close();

    $feedSql = "
        SELECT cm.id, cm.user_name, cm.user_email, cm.subject, cm.message, cm.conversation_type, cm.group_name, cm.group_photo,
               COALESCE(reply_summary.latest_reply_at, cm.sent_at) AS latest_activity_at,
               u.first_name, u.last_name, u.picture, u.sso_avatar_url
        FROM contact_messages cm
        LEFT JOIN (
            SELECT message_id, MAX(sent_at) AS latest_reply_at
            FROM contact_replies
            GROUP BY message_id
        ) reply_summary ON reply_summary.message_id = cm.id
        LEFT JOIN users u ON u.email = cm.user_email
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
              WHERE muted.message_id = cm.id AND muted.user_id = ? AND (muted.muted_at IS NOT NULL OR muted.left_at IS NOT NULL)
          )
          AND (mr.is_read IS NULL OR mr.is_read = 0)
        ORDER BY latest_activity_at DESC, cm.id DESC
        LIMIT 5
    ";
    $feedStmt = $conn->prepare($feedSql);
    $feedStmt->bind_param('isssi', $userId, $userEmail, $userName, $userEmail, $userId);
}

$feedStmt->execute();
$feedRows = db_stmt_fetch_all_assoc($feedStmt);
$latestPreviews = mailboxLatestThreadPreviews(
    $conn,
    array_column($feedRows, 'id'),
    $userId,
    (string) $userEmail,
    (string) $userName
);

foreach ($feedRows as $row) {
    $isGroupThread = mailboxIsGroupThread($row);
    $senderName = $isGroupThread ? trim((string) ($row['group_name'] ?? 'Group chat')) : trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
    if ($senderName === '') {
        $senderName = trim((string) ($row['user_name'] ?? 'Unknown'));
    }

    $messageId = (int) $row['id'];
    $latestPreview = $latestPreviews[$messageId] ?? [
        'text' => mailboxPreviewText($row['message'] ?? '', ''),
        'is_mine' => false,
    ];

    $items[] = [
        'id' => $messageId,
        'sender' => $senderName !== '' ? $senderName : 'Unknown',
        'subject' => (string) ($row['subject'] ?? '(No Subject)'),
        'snippet' => mailboxFormatThreadPreview($latestPreview, 50),
        'sent_label' => notification_time_label($row['latest_activity_at'] ?? null),
        'activity_key' => $messageId . ':' . (string) ($latestPreview['sent_at'] ?? $row['latest_activity_at'] ?? ''),
        'avatar' => $isGroupThread && trim((string) ($row['group_photo'] ?? '')) !== ''
            ? $base_url . 'inbox/uploads/group_photos/' . rawurlencode((string) $row['group_photo'])
            : notification_avatar_url($row, $base_url),
        'url' => $app_root . 'messenger/index.php?msg=' . $messageId,
    ];
}

$feedStmt->close();

echo json_encode([
    'count' => $unreadCount,
    'items' => $items,
]);
