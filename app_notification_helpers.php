<?php

require_once __DIR__ . '/socket_helpers.php';

function app_notification_visibility_sql_for_current_user(): string
{
    $userType = (string) ($_SESSION['user_type'] ?? 'user');

    if ($userType === 'admin') {
        return '';
    }

    return " AND n.category NOT IN ('first_login', 'users')";
}

function app_notification_ensure_schema(mysqli $conn): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    $conn->query(
        "CREATE TABLE IF NOT EXISTS app_notifications (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(80) NOT NULL DEFAULT 'system',
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            url VARCHAR(2048) DEFAULT NULL,
            icon_class VARCHAR(80) NOT NULL DEFAULT 'fas fa-bell',
            color_class VARCHAR(40) NOT NULL DEFAULT 'text-warning',
            actor_user_id INT NULL DEFAULT NULL,
            actor_name VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_app_notifications_created_at (created_at),
            INDEX idx_app_notifications_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS app_notification_reads (
            notification_id INT UNSIGNED NOT NULL,
            user_id INT NOT NULL,
            read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (notification_id, user_id),
            INDEX idx_app_notification_reads_user (user_id),
            CONSTRAINT fk_app_notification_reads_notification
                FOREIGN KEY (notification_id) REFERENCES app_notifications(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function app_notification_time_label(?string $createdAt): string
{
    if (!$createdAt) {
        return 'Unknown time';
    }

    $timestamp = strtotime($createdAt);
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

function app_notification_build_url(string $path): string
{
    require_once __DIR__ . '/base_url.php';

    $normalizedPath = ltrim(trim($path), '/');
    return app_url($normalizedPath);
}

function app_notification_actor_name_from_session(): string
{
    $fullName = trim(
        (string) ($_SESSION['first_name'] ?? '')
        . ' '
        . (string) ($_SESSION['last_name'] ?? '')
    );

    if ($fullName !== '') {
        return $fullName;
    }

    return trim((string) ($_SESSION['username'] ?? 'System')) ?: 'System';
}

function app_notification_create(mysqli $conn, array $payload): int
{
    app_notification_ensure_schema($conn);

    $title = trim((string) ($payload['title'] ?? ''));
    $message = trim((string) ($payload['message'] ?? ''));

    if ($title === '' || $message === '') {
        return 0;
    }

    $category = trim((string) ($payload['category'] ?? 'system')) ?: 'system';
    $url = trim((string) ($payload['url'] ?? ''));
    $url = $url !== '' ? $url : null;
    $iconClass = trim((string) ($payload['icon_class'] ?? 'fas fa-bell')) ?: 'fas fa-bell';
    $colorClass = trim((string) ($payload['color_class'] ?? 'text-warning')) ?: 'text-warning';
    $actorUserId = isset($payload['actor_user_id']) ? (int) $payload['actor_user_id'] : null;
    $actorName = trim((string) ($payload['actor_name'] ?? ''));
    $actorName = $actorName !== '' ? $actorName : null;

    $stmt = $conn->prepare(
        'INSERT INTO app_notifications
            (category, title, message, url, icon_class, color_class, actor_user_id, actor_name, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param(
        'ssssssis',
        $category,
        $title,
        $message,
        $url,
        $iconClass,
        $colorClass,
        $actorUserId,
        $actorName
    );

    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }

    $notificationId = (int) $stmt->insert_id;
    $stmt->close();

    kodus_socket_broadcast('kodus.notifications', 'notifications.changed', [
        'notification_id' => $notificationId,
        'category' => $category,
        'actor_id' => (int) ($actorUserId ?? 0),
    ]);

    return $notificationId;
}

function app_notification_get_feed(mysqli $conn, int $userId, int $limit = 5): array
{
    app_notification_ensure_schema($conn);

    $limit = max(1, min(20, $limit));
    $count = 0;
    $items = [];

    $visibilitySql = app_notification_visibility_sql_for_current_user();

    $countStmt = $conn->prepare(
        'SELECT COUNT(*) AS unread
         FROM app_notifications n
         LEFT JOIN app_notification_reads r
           ON r.notification_id = n.id AND r.user_id = ?
         WHERE r.notification_id IS NULL' . $visibilitySql
    );

    if ($countStmt) {
        $countStmt->bind_param('i', $userId);
        $countStmt->execute();
        $countRow = db_stmt_fetch_one_assoc($countStmt);
        $count = (int) ($countRow['unread'] ?? 0);
        $countStmt->close();
    }

    $items = app_notification_list($conn, $userId, $limit);

    return [
        'count' => $count,
        'items' => $items,
    ];
}

function app_notification_list(mysqli $conn, int $userId, int $limit = 50, int $offset = 0): array
{
    app_notification_ensure_schema($conn);

    if ($userId <= 0) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    $items = [];
    $visibilitySql = app_notification_visibility_sql_for_current_user();

    $sql = "
        SELECT n.id, n.category, n.title, n.message, n.url, n.icon_class, n.color_class, n.actor_name, n.created_at,
               CASE WHEN r.notification_id IS NULL THEN 1 ELSE 0 END AS is_unread
        FROM app_notifications n
        LEFT JOIN app_notification_reads r
          ON r.notification_id = n.id AND r.user_id = ?
        WHERE 1 = 1 {$visibilitySql}
        ORDER BY n.created_at DESC, n.id DESC
        LIMIT {$limit} OFFSET {$offset}
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = db_stmt_fetch_all_assoc($stmt);
    $stmt->close();

    foreach ($rows as $row) {
        $items[] = [
            'id' => (int) ($row['id'] ?? 0),
            'category' => (string) ($row['category'] ?? 'system'),
            'title' => (string) ($row['title'] ?? 'Notification'),
            'message' => (string) ($row['message'] ?? ''),
            'url' => (string) ($row['url'] ?? ''),
            'icon_class' => (string) ($row['icon_class'] ?? 'fas fa-bell'),
            'color_class' => (string) ($row['color_class'] ?? 'text-warning'),
            'actor_name' => (string) ($row['actor_name'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'time_label' => app_notification_time_label($row['created_at'] ?? null),
            'is_unread' => !empty($row['is_unread']),
        ];
    }

    return $items;
}

function app_notification_mark_read(mysqli $conn, int $userId, array $notificationIds): void
{
    app_notification_ensure_schema($conn);

    $ids = array_values(array_unique(array_filter(array_map('intval', $notificationIds))));
    if ($userId <= 0 || $ids === []) {
        return;
    }

    $stmt = $conn->prepare(
        'INSERT INTO app_notification_reads (notification_id, user_id, read_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)'
    );

    if (!$stmt) {
        return;
    }

    foreach ($ids as $notificationId) {
        $stmt->bind_param('ii', $notificationId, $userId);
        $stmt->execute();
    }

    $stmt->close();
}

function app_notification_mark_all_read(mysqli $conn, int $userId): void
{
    app_notification_ensure_schema($conn);

    if ($userId <= 0) {
        return;
    }

    $visibilitySql = app_notification_visibility_sql_for_current_user();

    $stmt = $conn->prepare(
        'INSERT INTO app_notification_reads (notification_id, user_id, read_at)
         SELECT n.id, ?, NOW()
         FROM app_notifications n
         LEFT JOIN app_notification_reads r
           ON r.notification_id = n.id AND r.user_id = ?
         WHERE r.notification_id IS NULL' . $visibilitySql . '
         ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)'
    );

    if (!$stmt) {
        return;
    }

    $stmt->bind_param('ii', $userId, $userId);
    $stmt->execute();
    $stmt->close();
}
