<?php
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../base_url.php';
require_once __DIR__ . '/../avatar_helpers.php';
require_once __DIR__ . '/mailbox_helpers.php';

security_bootstrap_session();
header('Content-Type: application/json; charset=utf-8');
mailboxEnsureSchema($conn);

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userType = $_SESSION['user_type'] ?? null;
$userEmail = trim((string) ($_SESSION['email'] ?? ''));
$userName = trim((string) ($_SESSION['username'] ?? ''));
$messageId = (int) ($_GET['message_id'] ?? 0);

if ($userId <= 0 || $messageId <= 0) {
    security_send_json(['success' => false, 'error' => 'Missing group conversation.'], 422);
}

if (!mailboxCanAccessMessage($conn, $messageId, $userType, $userEmail, $userName)) {
    security_send_json(['success' => false, 'error' => 'You cannot view this group.'], 403);
}

$threadStmt = $conn->prepare("
    SELECT id, conversation_type, group_name, user_email
    FROM contact_messages
    WHERE id = ?
    LIMIT 1
");
$threadStmt->bind_param('i', $messageId);
$threadStmt->execute();
$thread = db_stmt_fetch_one_assoc($threadStmt);
$threadStmt->close();

if (!$thread || !mailboxIsGroupThread($thread)) {
    security_send_json(['success' => false, 'error' => 'Members are only available for group chats.'], 404);
}

$canManageMembers = mailboxCanParticipateInThread($conn, $messageId, $userId);
$creatorEmail = strtolower(trim((string) ($thread['user_email'] ?? '')));

$stmt = $conn->prepare("
    SELECT cmr.user_id,
           cmr.recipient_email,
           cmr.recipient_name,
           cmr.muted_at,
           cmr.left_at,
           cmr.hidden_at,
           u.username,
           u.userType,
           u.picture,
           u.sso_avatar_url
    FROM contact_message_recipients cmr
    LEFT JOIN users u ON u.id = cmr.user_id
    WHERE cmr.message_id = ?
      AND cmr.hidden_at IS NULL
    ORDER BY
      CASE WHEN cmr.left_at IS NULL THEN 0 ELSE 1 END,
      COALESCE(NULLIF(u.username, ''), NULLIF(cmr.recipient_name, ''), cmr.recipient_email)
");
$stmt->bind_param('i', $messageId);
$stmt->execute();
$rows = db_stmt_fetch_all_assoc($stmt);
$stmt->close();

$members = [];
foreach ($rows as $row) {
    $memberId = (int) ($row['user_id'] ?? 0);
    $email = trim((string) ($row['recipient_email'] ?? ''));
    $name = trim((string) ($row['username'] ?? ''));
    if ($name === '') {
        $name = trim((string) ($row['recipient_name'] ?? ''));
    }
    if ($name === '') {
        $name = $email !== '' ? $email : 'Member';
    }

    $roles = [];
    if ($creatorEmail !== '' && strtolower($email) === $creatorEmail) {
        $roles[] = 'Creator';
    }
    if (strcasecmp((string) ($row['userType'] ?? ''), 'admin') === 0) {
        $roles[] = 'Admin';
    }

    $status = 'Active';
    if (!empty($row['left_at'])) {
        $status = 'Left';
    } elseif (!empty($row['muted_at'])) {
        $status = 'Muted';
    }

    $members[] = [
        'user_id' => $memberId,
        'name' => $name,
        'email' => $email,
        'avatar_url' => avatar_resolve_url($row['picture'] ?? '', $row['sso_avatar_url'] ?? '', $base_url, dirname(__DIR__)),
        'roles' => array_values(array_unique($roles)),
        'status' => $status,
        'is_self' => $memberId > 0 && $memberId === $userId,
        'can_remove' => $canManageMembers && $memberId > 0 && $memberId !== $userId && empty($row['left_at']),
    ];
}

security_send_json([
    'success' => true,
    'message_id' => $messageId,
    'group_name' => trim((string) ($thread['group_name'] ?? '')) ?: 'Group chat',
    'can_add_member' => $canManageMembers,
    'members' => $members,
]);
