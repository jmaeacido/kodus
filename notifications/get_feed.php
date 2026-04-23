<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../base_url.php';
require_once __DIR__ . '/../app_notification_helpers.php';

header('Content-Type: application/json');
app_notification_ensure_schema($conn);

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    echo json_encode([
        'count' => 0,
        'items' => [],
    ]);
    exit;
}

$feed = app_notification_get_feed($conn, $userId, 8);

foreach ($feed['items'] as &$item) {
    if (trim((string) ($item['url'] ?? '')) === '') {
        $item['url'] = $app_root . 'home';
    }
}
unset($item);

echo json_encode($feed);
