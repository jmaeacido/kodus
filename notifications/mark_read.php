<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app_notification_helpers.php';

header('Content-Type: application/json');
app_notification_ensure_schema($conn);

security_require_method(['POST']);
security_require_csrf_token();

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    security_send_json(['success' => false, 'message' => 'Unauthorized.'], 403);
}

$markAll = isset($_POST['mark_all']) && (string) $_POST['mark_all'] === '1';

$markedCount = 0;
$unreadCount = 0;

$countUnread = static function () use ($conn, $userId): int {
    $visibilitySql = app_notification_visibility_sql_for_current_user();
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS unread
         FROM app_notifications n
         LEFT JOIN app_notification_reads r
           ON r.notification_id = n.id AND r.user_id = ?
         WHERE r.notification_id IS NULL' . $visibilitySql
    );

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();

    return (int) ($row['unread'] ?? 0);
};

if ($markAll) {
    $markedCount = $countUnread();
    app_notification_mark_all_read($conn, $userId);
    kodus_socket_broadcast('kodus.notifications', 'notifications.changed', [
        'action' => 'mark_all_read',
        'actor_id' => $userId,
        'marked_count' => $markedCount,
    ]);

    security_send_json([
        'success' => true,
        'marked_count' => $markedCount,
        'unread_count' => 0,
    ]);
}

$ids = $_POST['ids'] ?? [];
if (!is_array($ids)) {
    $ids = [];
}

app_notification_mark_read($conn, $userId, $ids);

$unreadCount = $countUnread();
$normalizedIds = array_values(array_unique(array_filter(array_map('intval', $ids))));

if ($normalizedIds !== []) {
    kodus_socket_broadcast('kodus.notifications', 'notifications.changed', [
        'action' => 'mark_read',
        'actor_id' => $userId,
        'notification_ids' => $normalizedIds,
    ]);
}

security_send_json([
    'success' => true,
    'marked_count' => count($normalizedIds),
    'unread_count' => $unreadCount,
]);
