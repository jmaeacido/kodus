<?php
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/mailbox_helpers.php';

security_bootstrap_session();
include('../config.php');
mailboxEnsureSchema($conn);

header('Content-Type: application/json; charset=utf-8');

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userType = $_SESSION['user_type'] ?? null;
$userEmail = $_SESSION['email'] ?? null;
$userName = trim((string) ($_SESSION['username'] ?? ''));
$messageId = (int) ($_GET['message_id'] ?? 0);

if ($userId <= 0 || $messageId <= 0) {
    echo json_encode(['success' => true, 'typing' => []]);
    exit;
}

if (!mailboxCanAccessMessage($conn, $messageId, $userType, $userEmail, $userName)) {
    echo json_encode(['success' => true, 'typing' => []]);
    exit;
}

$cleanupStmt = $conn->prepare('DELETE FROM mailbox_typing_status WHERE last_seen_at < (NOW() - INTERVAL 12 SECOND)');
if ($cleanupStmt) {
    $cleanupStmt->execute();
    $cleanupStmt->close();
}

$stmt = $conn->prepare("
    SELECT mts.user_id, u.username, u.first_name, u.email
    FROM mailbox_typing_status mts
    INNER JOIN users u ON u.id = mts.user_id
    WHERE mts.message_id = ?
      AND mts.user_id <> ?
      AND mts.last_seen_at >= (NOW() - INTERVAL 12 SECOND)
    ORDER BY mts.last_seen_at DESC
");
$stmt->bind_param('ii', $messageId, $userId);
$stmt->execute();
$rows = db_stmt_fetch_all_assoc($stmt);
$stmt->close();

$typing = [];
foreach ($rows as $row) {
    $typing[] = [
        'user_id' => (int) ($row['user_id'] ?? 0),
        'username' => mailboxDisplayName($row, 'Someone'),
    ];
}

echo json_encode(['success' => true, 'typing' => $typing]);
