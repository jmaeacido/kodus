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
    $defaultAvatar = $baseUrl . 'kodus/dist/img/default.webp';
    $picture = (string) ($row['picture'] ?? '');

    if ($picture === '') {
        return $defaultAvatar;
    }

    $filePath = __DIR__ . '/../dist/img/' . $picture;
    if (!file_exists($filePath)) {
        return $defaultAvatar;
    }

    return $baseUrl . 'kodus/dist/img/' . rawurlencode($picture);
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
$items = [];
$unreadCount = 0;

if ($userType === 'admin') {
    $countSql = "
        SELECT COUNT(*) AS unread
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
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param('is', $userId, $userEmail);
    $countStmt->execute();
    $countResult = $countStmt->get_result()->fetch_assoc();
    $unreadCount = (int) ($countResult['unread'] ?? 0);
    $countStmt->close();

    $feedSql = "
        SELECT cm.id, cm.user_name, cm.user_email, cm.subject, cm.message,
               COALESCE(reply_summary.latest_reply_at, cm.sent_at) AS latest_activity_at,
               u.first_name, u.last_name, u.picture
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
            cm.user_email = ?
            OR EXISTS (
                SELECT 1
                FROM users ua
                WHERE FIND_IN_SET(ua.email, cm.recipient) > 0
                  AND ua.userType = 'admin'
            )
        )
          AND COALESCE(mr.is_trashed, 0) = 0
          AND (mr.is_read IS NULL OR mr.is_read = 0)
        ORDER BY latest_activity_at DESC, cm.id DESC
        LIMIT 5
    ";
    $feedStmt = $conn->prepare($feedSql);
    $feedStmt->bind_param('is', $userId, $userEmail);
} else {
    $countSql = "
        SELECT COUNT(*) AS unread
        FROM contact_messages cm
        LEFT JOIN message_reads mr
          ON cm.id = mr.message_id AND mr.user_id = ?
        WHERE (cm.user_email = ? OR FIND_IN_SET(?, cm.recipient) > 0)
          AND COALESCE(mr.is_trashed, 0) = 0
          AND (mr.is_read IS NULL OR mr.is_read = 0)
    ";
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param('iss', $userId, $userEmail, $userEmail);
    $countStmt->execute();
    $countResult = $countStmt->get_result()->fetch_assoc();
    $unreadCount = (int) ($countResult['unread'] ?? 0);
    $countStmt->close();

    $feedSql = "
        SELECT cm.id, cm.user_name, cm.user_email, cm.subject, cm.message,
               COALESCE(reply_summary.latest_reply_at, cm.sent_at) AS latest_activity_at,
               u.first_name, u.last_name, u.picture
        FROM contact_messages cm
        LEFT JOIN (
            SELECT message_id, MAX(sent_at) AS latest_reply_at
            FROM contact_replies
            GROUP BY message_id
        ) reply_summary ON reply_summary.message_id = cm.id
        LEFT JOIN users u ON u.email = cm.user_email
        LEFT JOIN message_reads mr
          ON cm.id = mr.message_id AND mr.user_id = ?
        WHERE (cm.user_email = ? OR FIND_IN_SET(?, cm.recipient) > 0)
          AND COALESCE(mr.is_trashed, 0) = 0
          AND (mr.is_read IS NULL OR mr.is_read = 0)
        ORDER BY latest_activity_at DESC, cm.id DESC
        LIMIT 5
    ";
    $feedStmt = $conn->prepare($feedSql);
    $feedStmt->bind_param('iss', $userId, $userEmail, $userEmail);
}

$feedStmt->execute();
$feedResult = $feedStmt->get_result();

while ($row = $feedResult->fetch_assoc()) {
    $senderName = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
    if ($senderName === '') {
        $senderName = trim((string) ($row['user_name'] ?? 'Unknown'));
    }

    $items[] = [
        'id' => (int) $row['id'],
        'sender' => $senderName !== '' ? $senderName : 'Unknown',
        'subject' => (string) ($row['subject'] ?? '(No Subject)'),
        'snippet' => mb_strimwidth(trim((string) ($row['message'] ?? '')), 0, 50, '...'),
        'sent_label' => notification_time_label($row['latest_activity_at'] ?? null),
        'avatar' => notification_avatar_url($row, $base_url),
        'url' => $base_url . 'kodus/inbox/index.php?msg=' . (int) $row['id'],
    ];
}

$feedStmt->close();

echo json_encode([
    'count' => $unreadCount,
    'items' => $items,
]);
