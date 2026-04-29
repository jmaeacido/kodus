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
$userEmail = (string) ($_SESSION['email'] ?? '');
$userName = trim((string) ($_SESSION['username'] ?? ''));
$groupName = trim((string) ($_POST['group_name'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));
$memberRaw = $_POST['members'] ?? [];

if (!is_array($memberRaw)) {
    $memberRaw = [$memberRaw];
}

$memberIds = array_values(array_unique(array_filter(array_map(static function ($value) {
    $value = trim((string) $value);
    if (strpos($value, 'user_') === 0) {
        $value = substr($value, 5);
    }
    return (int) $value;
}, $memberRaw), static fn(int $id): bool => $id > 0)));

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'You must be logged in.']);
    exit;
}

if ($groupName === '' || count($memberIds) < 2) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Group name and at least 2 selected members are required.']);
    exit;
}

$allMemberIds = array_values(array_unique(array_merge([$userId], $memberIds)));
$placeholders = implode(',', array_fill(0, count($allMemberIds), '?'));
$types = str_repeat('i', count($allMemberIds));
$stmt = $conn->prepare("SELECT id, email, username FROM users WHERE id IN ($placeholders)");
$stmt->bind_param($types, ...$allMemberIds);
$stmt->execute();
$users = db_stmt_fetch_all_assoc($stmt);
$stmt->close();

if (count($users) !== count($allMemberIds)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'One or more selected members could not be found.']);
    exit;
}

$photo = null;
if (!empty($_FILES['group_photo']['name']) && is_uploaded_file($_FILES['group_photo']['tmp_name'])) {
    $tmp = $_FILES['group_photo']['tmp_name'];
    $mime = security_detect_upload_mime($tmp);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime]) || (int) ($_FILES['group_photo']['size'] ?? 0) > 5 * 1024 * 1024) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Upload a JPG, PNG, or WEBP photo up to 5 MB.']);
        exit;
    }
    $uploadDir = __DIR__ . '/uploads/group_photos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $photo = time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmp, $uploadDir . $photo)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Unable to save group photo.']);
        exit;
    }
}

$openingMessage = $message !== '' ? $message : 'Group chat created.';
$subject = $groupName;

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("
        INSERT INTO contact_messages (conversation_type, group_name, group_photo, user_email, user_name, subject, message, sent_at)
        VALUES ('group', ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param('ssssss', $groupName, $photo, $userEmail, $userName, $subject, $openingMessage);
    $stmt->execute();
    $messageId = (int) $stmt->insert_id;
    $stmt->close();

    $recipientStmt = $conn->prepare("
        INSERT INTO contact_message_recipients (message_id, user_id, recipient_email, recipient_name)
        VALUES (?, ?, ?, ?)
    ");
    $readStmt = $conn->prepare("
        INSERT INTO message_reads (message_id, user_id, is_read, is_trashed, read_at, trashed_at)
        VALUES (?, ?, ?, 0, ?, NULL)
        ON DUPLICATE KEY UPDATE is_read = VALUES(is_read), is_trashed = 0, read_at = VALUES(read_at), trashed_at = NULL
    ");

    foreach ($users as $row) {
        $memberId = (int) $row['id'];
        $email = (string) $row['email'];
        $name = (string) $row['username'];
        $recipientStmt->bind_param('iiss', $messageId, $memberId, $email, $name);
        $recipientStmt->execute();

        $isRead = $memberId === $userId ? 1 : 0;
        $readAt = $memberId === $userId ? date('Y-m-d H:i:s') : null;
        $readStmt->bind_param('iiis', $messageId, $memberId, $isRead, $readAt);
        $readStmt->execute();
    }

    $recipientStmt->close();
    $readStmt->close();
    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
    exit;
}

kodus_socket_broadcast('kodus.mailbox', 'mail.changed', [
    'action' => 'group_created',
    'message_id' => $messageId,
    'actor_id' => $userId,
    'receiver_ids' => array_values(array_diff($allMemberIds, [$userId])),
]);

echo json_encode([
    'success' => true,
    'message_id' => $messageId,
]);
