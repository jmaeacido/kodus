<?php
include('../header.php');
include('../sidenav.php');
include('../config.php');
require_once __DIR__ . '/mailbox_helpers.php';

mailboxEnsureSchema($conn);
$attachmentLimits = mailboxAttachmentLimits();

function mailbox_user_avatar_url(?string $picture, ?string $ssoAvatarUrl, string $baseUrl): string
{
    return avatar_resolve_url($picture, $ssoAvatarUrl, $baseUrl, dirname(__DIR__));
}

$userId = $_SESSION['user_id'] ?? null;
$currentFolder = mailboxGetFolder($_GET['folder'] ?? 'inbox');
$trashPredicate = mailboxTrashPredicate($currentFolder, 'mr');
$folderTitle = $currentFolder === 'trash' ? 'Archived' : 'Chats';
$currentUsername = trim((string) ($_SESSION['username'] ?? ''));

if (!$userId) {
    header("Location: ../");
    exit;
}

$stmt = $conn->prepare("SELECT userType FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($userType);
$stmt->fetch();
$stmt->close();

if ($userType === 'admin') {
    $stmt = $conn->prepare("
        SELECT cm.*,
               COALESCE(reply_summary.latest_reply_at, cm.sent_at) AS latest_activity_at,
               COALESCE(mr.is_read, 0) AS user_read,
               sender_user.picture AS sender_picture,
               sender_user.id AS sender_user_id,
               sender_user.sso_avatar_url AS sender_sso_avatar_url,
               sender_user.last_activity AS sender_last_activity,
               sender_user.is_online AS sender_is_online,
               current_member.muted_at AS current_member_muted_at,
               current_member.left_at AS current_member_left_at,
               (
                   SELECT GROUP_CONCAT(DISTINCT u.username ORDER BY u.username SEPARATOR ', ')
                   FROM contact_message_recipients cmr
                   INNER JOIN users u ON u.id = cmr.user_id
                   WHERE cmr.message_id = cm.id
               ) AS recipient_names,
               (
                   SELECT u.id
                   FROM contact_message_recipients cmr
                   INNER JOIN users u ON u.id = cmr.user_id
                   WHERE cmr.message_id = cm.id
                   ORDER BY u.username
                   LIMIT 1
               ) AS recipient_user_id,
               (
                   SELECT u.picture
                   FROM contact_message_recipients cmr
                   INNER JOIN users u ON u.id = cmr.user_id
                   WHERE cmr.message_id = cm.id
                   ORDER BY u.username
                   LIMIT 1
               ) AS recipient_picture,
               (
                   SELECT u.sso_avatar_url
                   FROM contact_message_recipients cmr
                   INNER JOIN users u ON u.id = cmr.user_id
                   WHERE cmr.message_id = cm.id
                   ORDER BY u.username
                   LIMIT 1
               ) AS recipient_sso_avatar_url,
               (
                   SELECT u.last_activity
                   FROM contact_message_recipients cmr
                   INNER JOIN users u ON u.id = cmr.user_id
                   WHERE cmr.message_id = cm.id
                   ORDER BY u.username
                   LIMIT 1
               ) AS recipient_last_activity,
               (
                   SELECT u.is_online
                   FROM contact_message_recipients cmr
                   INNER JOIN users u ON u.id = cmr.user_id
                   WHERE cmr.message_id = cm.id
                   ORDER BY u.username
                   LIMIT 1
               ) AS recipient_is_online
        FROM contact_messages cm
        LEFT JOIN (
            SELECT message_id, MAX(sent_at) AS latest_reply_at
            FROM contact_replies
            GROUP BY message_id
        ) reply_summary ON reply_summary.message_id = cm.id
        LEFT JOIN users sender_user ON sender_user.email = cm.user_email
        LEFT JOIN message_reads mr
            ON cm.id = mr.message_id AND mr.user_id = ?
        LEFT JOIN contact_message_recipients current_member
            ON current_member.message_id = cm.id AND current_member.user_id = ?
        WHERE (
            COALESCE(cm.conversation_type, 'direct') = 'group'
            OR
            cm.user_email IN (SELECT email FROM users WHERE id = ?)
            OR EXISTS (
                SELECT 1
                FROM contact_message_recipients cmr
                INNER JOIN users u ON u.id = cmr.user_id
                WHERE cmr.message_id = cm.id
                  AND u.userType = 'admin'
            )
        )
          AND {$trashPredicate}
          AND " . mailboxVisibilityPredicate((int) $userId, 'cm', 'mr') . "
        ORDER BY latest_activity_at DESC, cm.id DESC
    ");
    $stmt->bind_param("iii", $userId, $userId, $userId);
} else {
    $userEmail = $_SESSION['email'] ?? '';
    $stmt = $conn->prepare("
        SELECT cm.*,
               COALESCE(reply_summary.latest_reply_at, cm.sent_at) AS latest_activity_at,
               COALESCE(mr.is_read, 0) AS user_read,
               sender_user.picture AS sender_picture,
               sender_user.id AS sender_user_id,
               sender_user.sso_avatar_url AS sender_sso_avatar_url,
               sender_user.last_activity AS sender_last_activity,
               sender_user.is_online AS sender_is_online,
               current_member.muted_at AS current_member_muted_at,
               current_member.left_at AS current_member_left_at,
               (
                   SELECT GROUP_CONCAT(DISTINCT u.username ORDER BY u.username SEPARATOR ', ')
                   FROM contact_message_recipients cmr
                   INNER JOIN users u ON u.id = cmr.user_id
                   WHERE cmr.message_id = cm.id
               ) AS recipient_names,
               (
                   SELECT u.id
                   FROM contact_message_recipients cmr
                   INNER JOIN users u ON u.id = cmr.user_id
                   WHERE cmr.message_id = cm.id
                   ORDER BY u.username
                   LIMIT 1
               ) AS recipient_user_id,
               (
                   SELECT u.picture
                   FROM contact_message_recipients cmr
                   INNER JOIN users u ON u.id = cmr.user_id
                   WHERE cmr.message_id = cm.id
                   ORDER BY u.username
                   LIMIT 1
               ) AS recipient_picture,
               (
                   SELECT u.sso_avatar_url
                   FROM contact_message_recipients cmr
                   INNER JOIN users u ON u.id = cmr.user_id
                   WHERE cmr.message_id = cm.id
                   ORDER BY u.username
                   LIMIT 1
               ) AS recipient_sso_avatar_url,
               (
                   SELECT u.last_activity
                   FROM contact_message_recipients cmr
                   INNER JOIN users u ON u.id = cmr.user_id
                   WHERE cmr.message_id = cm.id
                   ORDER BY u.username
                   LIMIT 1
               ) AS recipient_last_activity,
               (
                   SELECT u.is_online
                   FROM contact_message_recipients cmr
                   INNER JOIN users u ON u.id = cmr.user_id
                   WHERE cmr.message_id = cm.id
                   ORDER BY u.username
                   LIMIT 1
               ) AS recipient_is_online
        FROM contact_messages cm
        LEFT JOIN (
            SELECT message_id, MAX(sent_at) AS latest_reply_at
            FROM contact_replies
            GROUP BY message_id
        ) reply_summary ON reply_summary.message_id = cm.id
        LEFT JOIN users sender_user ON sender_user.email = cm.user_email
        LEFT JOIN message_reads mr
            ON cm.id = mr.message_id AND mr.user_id = ?
        LEFT JOIN contact_message_recipients current_member
            ON current_member.message_id = cm.id AND current_member.user_id = ?
        WHERE (cm.user_email = ?
           OR cm.user_name = ?
           OR EXISTS (
               SELECT 1
               FROM contact_message_recipients cmr
               WHERE cmr.message_id = cm.id
                 AND LOWER(cmr.recipient_email) = LOWER(?)
           )
           OR COALESCE(cm.conversation_type, 'direct') = 'group')
          AND {$trashPredicate}
          AND " . mailboxVisibilityPredicate((int) $userId, 'cm', 'mr') . "
        ORDER BY latest_activity_at DESC, cm.id DESC
    ");
    $stmt->bind_param("iisss", $userId, $userId, $userEmail, $currentUsername, $userEmail);
}

$stmt->execute();
$messages = db_stmt_fetch_all_assoc($stmt);
$stmt->close();
$latestPreviews = mailboxLatestThreadPreviews(
    $conn,
    array_column($messages, 'id'),
    (int) $userId,
    (string) ($_SESSION['email'] ?? ''),
    $currentUsername
);

$messageCount = count($messages);
$unreadCount = 0;
foreach ($messages as $message) {
    $isMutedOrLeftGroup = mailboxIsGroupThread($message)
        && (!empty($message['current_member_muted_at']) || !empty($message['current_member_left_at']));
    if (!$isMutedOrLeftGroup && (int) ($message['user_read'] ?? 0) === 0) {
        $unreadCount++;
    }
}

$trashCount = 0;
if ($userType === 'admin') {
    $trashCountStmt = $conn->prepare("
        SELECT COUNT(*) AS trash_count
        FROM contact_messages cm
        LEFT JOIN message_reads mr
            ON cm.id = mr.message_id AND mr.user_id = ?
        WHERE (
            COALESCE(cm.conversation_type, 'direct') = 'group'
            OR
            cm.user_email IN (SELECT email FROM users WHERE id = ?)
            OR EXISTS (
                SELECT 1
                FROM contact_message_recipients cmr
                INNER JOIN users u ON u.id = cmr.user_id
                WHERE cmr.message_id = cm.id
                  AND u.userType = 'admin'
            )
        )
          AND COALESCE(mr.is_trashed, 0) = 1
          AND " . mailboxVisibilityPredicate((int) $userId, 'cm', 'mr') . "
    ");
    $trashCountStmt->bind_param("ii", $userId, $userId);
} else {
    $userEmail = $_SESSION['email'] ?? '';
    $trashCountStmt = $conn->prepare("
        SELECT COUNT(*) AS trash_count
        FROM contact_messages cm
        LEFT JOIN message_reads mr
            ON cm.id = mr.message_id AND mr.user_id = ?
        WHERE (cm.user_email = ? OR cm.user_name = ? OR EXISTS (
            SELECT 1
            FROM contact_message_recipients cmr
            WHERE cmr.message_id = cm.id
              AND LOWER(cmr.recipient_email) = LOWER(?)
        ) OR COALESCE(cm.conversation_type, 'direct') = 'group')
          AND COALESCE(mr.is_trashed, 0) = 1
          AND " . mailboxVisibilityPredicate((int) $userId, 'cm', 'mr') . "
    ");
    $trashCountStmt->bind_param("isss", $userId, $userEmail, $currentUsername, $userEmail);
}

$trashCountStmt->execute();
$trashCountResult = db_stmt_fetch_one_assoc($trashCountStmt);
$trashCount = (int) ($trashCountResult['trash_count'] ?? 0);
$trashCountStmt->close();

$composeOpen = isset($_GET['compose']) && $_GET['compose'] !== '0';
$composeRecipients = [];
$recipientResult = $conn->query("SELECT id, username, email, picture, sso_avatar_url, last_activity, is_online FROM users ORDER BY username");
if ($recipientResult) {
    while ($recipientRow = $recipientResult->fetch_assoc()) {
        $recipientRow['avatar_url'] = mailbox_user_avatar_url($recipientRow['picture'] ?? '', $recipientRow['sso_avatar_url'] ?? '', $base_url);
        $recipientRow['presence'] = mailboxClassifyPresence($recipientRow['last_activity'] ?? null, (int) ($recipientRow['is_online'] ?? 0));
        $composeRecipients[] = $recipientRow;
    }
}
$composeCsrfToken = security_get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>KODUS | Messenger</title>
  <link rel="stylesheet" href="<?php echo app_url('plugins/select2/css/select2.min.css'); ?>">
  <link rel="stylesheet" href="<?php echo app_url('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css'); ?>">
  <style>
    :root {
      --mailbox-surface: #ffffff;
      --mailbox-surface-muted: #f8fafc;
      --mailbox-surface-elevated: #ffffff;
      --mailbox-border: #d9e2ec;
      --mailbox-border-strong: #c7d2e0;
      --mailbox-text: #1f2937;
      --mailbox-text-muted: #64748b;
      --mailbox-accent-soft: rgba(13, 110, 253, 0.1);
      --mailbox-accent-strong: #2563eb;
      --mailbox-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
      --messenger-bg: #eef2f7;
      --messenger-panel: #ffffff;
      --messenger-panel-strong: #f8fafc;
      --messenger-panel-soft: rgba(15, 23, 42, 0.04);
      --messenger-divider: rgba(15, 23, 42, 0.08);
      --messenger-text: #18212f;
      --messenger-muted: #667085;
      --messenger-accent: #2374e1;
      --messenger-accent-soft: rgba(35, 116, 225, 0.14);
      --messenger-chip: rgba(15, 23, 42, 0.06);
      --messenger-bubble: #f1f5f9;
      --messenger-bubble-mine: linear-gradient(135deg, #2563eb, #7c3aed);
      --messenger-success: #31a24c;
      --messenger-shadow: 0 20px 48px rgba(15, 23, 42, 0.12);
    }

    body.kodus-chat-bubble-mode .main-sidebar,
    body.kodus-chat-bubble-mode .main-header,
    body.kodus-chat-bubble-mode .main-footer,
    body.kodus-chat-bubble-mode footer {
      display: none !important;
    }

    body.kodus-chat-bubble-mode .content-wrapper {
      margin-left: 0 !important;
      padding-top: 0 !important;
      min-height: 100vh !important;
    }

    body.dark-mode .mailbox-app,
    body.dark-mode .compose-modal,
    body.dark-mode #groupMembersModal,
    body.dark-mode.messenger-page .swal2-popup,
    body[data-theme="dark"] .mailbox-app,
    body[data-theme="dark"] .compose-modal,
    body[data-theme="dark"] #groupMembersModal,
    body[data-theme="dark"].messenger-page .swal2-popup {
      --mailbox-surface: #1f2a37;
      --mailbox-surface-muted: #243140;
      --mailbox-surface-elevated: #223041;
      --mailbox-border: rgba(255, 255, 255, 0.09);
      --mailbox-border-strong: rgba(255, 255, 255, 0.18);
      --mailbox-text: #f3f4f6;
      --mailbox-text-muted: #c0cad5;
      --mailbox-accent-soft: rgba(96, 165, 250, 0.18);
      --mailbox-accent-strong: #93c5fd;
      --mailbox-shadow: 0 18px 36px rgba(0, 0, 0, 0.22);
      --messenger-bg: #18191a;
      --messenger-panel: #242526;
      --messenger-panel-strong: #1f2021;
      --messenger-panel-soft: rgba(255, 255, 255, 0.05);
      --messenger-divider: rgba(255, 255, 255, 0.06);
      --messenger-text: #f5f7fb;
      --messenger-muted: #aeb4bd;
      --messenger-accent: #2374e1;
      --messenger-accent-soft: rgba(35, 116, 225, 0.18);
      --messenger-chip: rgba(255, 255, 255, 0.08);
      --messenger-bubble: #2f3031;
      --messenger-bubble-mine: linear-gradient(135deg, #2563eb, #7c3aed);
      --messenger-success: #31a24c;
      --messenger-shadow: 0 24px 56px rgba(0, 0, 0, 0.28);
    }

    body.messenger-page .swal2-popup {
      --mailbox-surface: #ffffff;
      --mailbox-surface-muted: #f8fafc;
      --mailbox-surface-elevated: #ffffff;
      --mailbox-border: #d9e2ec;
      --mailbox-border-strong: #c7d2e0;
      --mailbox-text: #1f2937;
      --mailbox-text-muted: #64748b;
      --mailbox-accent-soft: rgba(13, 110, 253, 0.1);
      --mailbox-accent-strong: #2563eb;
      --mailbox-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
      --messenger-panel: #ffffff;
      --messenger-panel-strong: #f8fafc;
      --messenger-divider: rgba(15, 23, 42, 0.08);
      --messenger-text: #18212f;
      --messenger-muted: #667085;
      --messenger-accent-soft: rgba(35, 116, 225, 0.14);
      --messenger-chip: rgba(15, 23, 42, 0.06);
    }

    body.dark-mode .mailbox-app .mailbox-read-info,
    body[data-theme="dark"] .mailbox-app .mailbox-read-info {
      box-shadow: none;
    }

    body.dark-mode .mailbox-app .attachment-thumb,
    body[data-theme="dark"] .mailbox-app .attachment-thumb {
      background: transparent;
      color: #f3f4f6;
      border-color: transparent;
    }

    body.dark-mode .mailbox-app .attachment-thumb .btn,
    body[data-theme="dark"] .mailbox-app .attachment-thumb .btn {
      color: #f3f4f6;
      border-color: rgba(255, 255, 255, 0.2);
    }

    html,
    body {
      max-width: 100%;
      overflow-x: hidden;
    }

    .mailbox-app {
      max-width: 100%;
      overflow-x: hidden;
    }

    .mailbox-app .content-wrapper {
      background:
        radial-gradient(circle at top right, rgba(13, 110, 253, 0.12), transparent 28%),
        radial-gradient(circle at bottom left, rgba(16, 185, 129, 0.1), transparent 24%),
        linear-gradient(180deg, color-mix(in srgb, var(--mailbox-surface-muted) 88%, #dbeafe 12%) 0%, var(--mailbox-surface-muted) 52%, color-mix(in srgb, var(--mailbox-surface) 92%, #ecfeff 8%) 100%);
    }

    body.dark-mode .mailbox-app .content-wrapper,
    body[data-theme="dark"] .mailbox-app .content-wrapper {
      background:
        radial-gradient(circle at top right, rgba(59, 130, 246, 0.16), transparent 30%),
        radial-gradient(circle at bottom left, rgba(20, 184, 166, 0.14), transparent 24%),
        linear-gradient(180deg, #16202b 0%, #1b2633 52%, #13212c 100%);
    }

    .mailbox-app .content-wrapper,
    .mailbox-app .container-fluid,
    .mailbox-app .row,
    .mailbox-app [class*="col-"] {
      min-width: 0;
    }

    .mailbox-app .card {
      margin-bottom: 1rem;
      border: 1px solid var(--mailbox-border);
      border-radius: 1rem;
      box-shadow: var(--mailbox-shadow);
      overflow: hidden;
    }

    .mailbox-app .mailbox-sidebar-card,
    .mailbox-app .mailbox-pane-card {
      min-height: 72vh;
      background: linear-gradient(180deg, var(--mailbox-surface) 0%, var(--mailbox-surface-muted) 100%);
      overflow-x: hidden;
    }

    .mailbox-app .mailbox-messages-wrap {
      max-height: 48vh;
      overflow-y: auto;
      overflow-x: hidden;
      border-top: 1px solid var(--mailbox-border);
      background: linear-gradient(180deg, var(--mailbox-surface-muted) 0%, var(--mailbox-surface) 100%);
    }

    .mailbox-app .mailbox-empty,
    .mailbox-app .mailbox-empty-detail {
      min-height: 260px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: var(--mailbox-text-muted);
      text-align: center;
      padding: 2rem;
    }

    .mailbox-app .mailbox-empty img {
      max-width: 160px;
      opacity: 0.6;
      margin-bottom: 1rem;
    }

    .mailbox-app .message-item {
      cursor: pointer;
      transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
    }

    .mailbox-app .message-item:hover {
      background: color-mix(in srgb, var(--mailbox-surface) 90%, transparent);
    }

    .mailbox-app .message-item.active {
      background: color-mix(in srgb, var(--mailbox-surface) 96%, transparent);
    }

    .mailbox-app .message-item.unread .mailbox-name,
    .mailbox-app .message-item.unread .mailbox-subject {
      font-weight: 700;
    }

    .mailbox-app .mailbox-chat-badge {
      display: inline-flex;
      align-items: center;
      margin-left: 0.45rem;
      padding: 0.18rem 0.45rem;
      border-radius: 999px;
      background: color-mix(in srgb, var(--messenger-accent) 14%, transparent);
      color: var(--messenger-accent);
      font-size: 0.68rem;
      font-weight: 800;
      letter-spacing: 0.02em;
      vertical-align: middle;
    }

    .mailbox-app .mailbox-chat-badge--muted {
      background: color-mix(in srgb, #f59e0b 16%, var(--mailbox-surface));
      border: 1px solid color-mix(in srgb, #f59e0b 28%, var(--mailbox-border));
      color: color-mix(in srgb, #d97706 78%, var(--mailbox-text));
    }

    .mailbox-app .mailbox-chat-badge--left {
      background: color-mix(in srgb, var(--mailbox-text-muted) 14%, var(--mailbox-surface));
      border: 1px solid color-mix(in srgb, var(--mailbox-text-muted) 28%, var(--mailbox-border));
      color: var(--mailbox-text-muted);
    }

    .mailbox-app .mailbox-name {
      width: 190px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      color: var(--mailbox-text);
    }

    .mailbox-app .mailbox-avatar-cell {
      width: 54px;
    }

    .mailbox-app .mailbox-avatar {
      width: 36px;
      height: 36px;
      border-radius: 999px;
      object-fit: cover;
      border: 2px solid rgba(255, 255, 255, 0.18);
      box-shadow: 0 6px 14px rgba(15, 23, 42, 0.16);
    }

    .mailbox-app .mailbox-subject {
      max-width: 100%;
      width: 100%;
      display: block;
      color: var(--mailbox-text);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .mailbox-app .mailbox-subject .mailbox-subject-line,
    .mailbox-app .mailbox-subject .mailbox-snippet {
      display: inline-block;
      max-width: 100%;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      vertical-align: bottom;
    }

    .mailbox-app .mailbox-subject .mailbox-snippet {
      color: var(--mailbox-text-muted);
      font-weight: 400;
    }

    .mailbox-app .mailbox-date {
      white-space: nowrap;
      width: 110px;
      text-align: right;
      color: var(--mailbox-text-muted);
    }

    .mailbox-app .mailbox-bulk-toolbar {
      gap: 0.6rem 0.85rem;
      padding: 0 0 0.75rem;
      flex-wrap: wrap;
    }

    .mailbox-app .mailbox-bulk-actions {
      gap: 0.5rem;
      flex: 0 0 auto;
    }

    .mailbox-app .mailbox-bulk-summary {
      color: var(--mailbox-text-muted);
      font-size: 0.82rem;
      line-height: 1;
      white-space: nowrap;
    }

    .mailbox-app .mailbox-select-toggle {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      margin: 0;
      color: var(--mailbox-text);
      font-weight: 500;
      cursor: pointer;
      font-size: 0.86rem;
    }

    .mailbox-app .mailbox-select-toggle input,
    .mailbox-app .mailbox-row-select input {
      width: 1rem;
      height: 1rem;
      accent-color: #2563eb;
      cursor: pointer;
    }

    .mailbox-app .mailbox-bulk-select,
    .mailbox-app .mailbox-bulk-apply {
      min-height: 30px;
    }

    .mailbox-app .mailbox-bulk-select {
      width: auto;
      min-width: 132px;
      max-width: 180px;
      flex: 0 0 auto;
      padding-right: 1.85rem;
    }

    .mailbox-app .mailbox-bulk-apply {
      flex: 0 0 auto;
      white-space: nowrap;
    }

    .mailbox-app .mailbox-select-cell {
      width: 38px;
      text-align: center;
    }

    .mailbox-app .mailbox-read-pane {
      min-height: 52vh;
      background: linear-gradient(180deg, var(--mailbox-surface) 0%, var(--mailbox-surface-muted) 100%);
    }

    .mailbox-app .conversation-scroll {
      max-height: 320px;
      overflow-y: auto;
      padding-right: 0.5rem;
    }

    .mailbox-app .mailbox-sidebar-card .box-profile,
    .mailbox-app .mailbox-read-pane,
    .mailbox-app .card-header,
    .mailbox-app .mailbox-controls {
      color: var(--mailbox-text);
    }

    .mailbox-app .content-header {
      padding-top: 0.65rem;
    }

    .mailbox-app .content-header h1 {
      font-size: clamp(1.65rem, 2.2vw, 2.15rem);
      font-weight: 800;
      letter-spacing: -0.03em;
      color: var(--mailbox-text);
    }

    .mailbox-app .breadcrumb {
      padding: 0.45rem 0.8rem;
      border-radius: 999px;
      background: color-mix(in srgb, var(--mailbox-surface) 86%, transparent);
      border: 1px solid var(--mailbox-border);
      backdrop-filter: blur(10px);
    }

    .mailbox-app .card-header {
      padding: 1rem 1.1rem;
      background: linear-gradient(180deg, var(--mailbox-surface) 0%, var(--mailbox-surface-muted) 100%);
      border-bottom: 1px solid var(--mailbox-border);
    }

    .mailbox-app .card-title {
      font-weight: 700;
      color: var(--mailbox-text);
    }

    .mailbox-app .mailbox-sidebar-card .btn.btn-primary.btn-block {
      border-radius: 0.85rem;
      font-weight: 600;
      box-shadow: 0 12px 24px rgba(13, 110, 253, 0.18);
      background: linear-gradient(135deg, #2563eb, #0f766e);
      border: 0;
    }

    .mailbox-app .mailbox-folder-nav {
      gap: 0.5rem;
    }

    .mailbox-app .mailbox-folder-link {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      padding: 0.8rem 0.9rem;
      border-radius: 0.9rem;
      border: 1px solid var(--mailbox-border);
      background: var(--mailbox-surface-muted);
      color: var(--mailbox-text);
      font-weight: 600;
    }

    .mailbox-app .mailbox-folder-link:hover {
      background: var(--mailbox-surface);
      border-color: var(--mailbox-border-strong);
      text-decoration: none;
    }

    .mailbox-app .mailbox-folder-link.active {
      background: linear-gradient(135deg, rgba(37, 99, 235, 0.14), rgba(15, 118, 110, 0.1));
      border-color: rgba(37, 99, 235, 0.24);
      color: color-mix(in srgb, var(--mailbox-text) 30%, #2563eb 70%);
    }

    .mailbox-app .mailbox-folder-link .badge {
      min-width: 2rem;
      border-radius: 999px;
    }

    .mailbox-app .mailbox-sidebar-summary {
      border-radius: 0.9rem;
      border: 1px solid var(--mailbox-border);
      background: linear-gradient(180deg, var(--mailbox-surface) 0%, var(--mailbox-surface-muted) 100%);
      padding: 0.95rem 1rem;
    }

    .mailbox-app .mailbox-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      padding: 0.85rem 1rem;
      border-bottom: 1px solid var(--mailbox-border);
      background: var(--mailbox-surface);
    }

    .mailbox-app .mailbox-toolbar-copy {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.45rem 0.75rem;
      color: var(--mailbox-text-muted);
      font-size: 0.86rem;
    }

    .mailbox-app .mailbox-toolbar-copy strong {
      color: var(--mailbox-text);
      font-size: 0.94rem;
    }

    .mailbox-app .mailbox-live-indicator {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
    }

    .mailbox-app .mailbox-live-indicator::before {
      content: "";
      width: 0.55rem;
      height: 0.55rem;
      border-radius: 999px;
      background: #10b981;
      box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.14);
    }

    .mailbox-app .mailbox-filter-group {
      padding: 0.8rem 1rem 0.95rem;
      background: var(--mailbox-surface);
      border-bottom: 1px solid var(--mailbox-border);
    }

    .mailbox-app .mailbox-filter.btn {
      border-radius: 999px;
      font-weight: 600;
      padding-left: 0.95rem;
      padding-right: 0.95rem;
    }

    .mailbox-app .mailbox-filter.active {
      background: linear-gradient(135deg, #2563eb, #0f766e);
      border-color: transparent;
      color: #fff;
      box-shadow: 0 10px 20px rgba(13, 110, 253, 0.16);
    }

    .mailbox-app .mailbox-search .input-group {
      width: min(100%, 280px) !important;
    }

    .mailbox-app .mailbox-search .form-control,
    .mailbox-app .mailbox-search .input-group-append .btn {
      height: 2.5rem;
      border-color: var(--mailbox-border);
      background: var(--mailbox-surface-muted);
      color: var(--mailbox-text);
    }

    .mailbox-app .mailbox-search .form-control {
      border-radius: 999px 0 0 999px;
      padding-left: 0.95rem;
    }

    .mailbox-app .mailbox-search .input-group-append .btn {
      border-radius: 0 999px 999px 0;
      color: var(--mailbox-text-muted);
    }

    .mailbox-app .mailbox-read-info {
      border: 1px solid var(--mailbox-border);
      border-radius: 1rem;
      background: linear-gradient(180deg, var(--mailbox-surface) 0%, var(--mailbox-surface-muted) 100%);
      padding: 1rem 1.1rem;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
    }

    .mailbox-app .mailbox-read-info--compact .mailbox-read-meta {
      margin-top: 0;
    }

    .mailbox-app .mailbox-read-subject {
      margin: 0;
      font-size: 1.1rem;
      font-weight: 700;
      line-height: 1.35;
      color: var(--mailbox-text);
    }

    .mailbox-app .mailbox-read-meta {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 0.7rem;
      margin-top: 0.9rem;
    }

    .mailbox-app .mailbox-read-meta-item {
      border-radius: 0.85rem;
      background: var(--mailbox-surface-muted);
      border: 1px solid var(--mailbox-border);
      padding: 0.75rem 0.85rem;
      min-width: 0;
    }

    .mailbox-app .mailbox-read-meta-item span {
      display: block;
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: var(--mailbox-text-muted);
      margin-bottom: 0.2rem;
    }

    .mailbox-app .mailbox-read-meta-item strong {
      display: block;
      font-size: 0.9rem;
      color: var(--mailbox-text);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .mailbox-app .mailbox-thread-heading {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      margin-bottom: 0.85rem;
    }

    .mailbox-app .mailbox-thread-heading h6 {
      margin: 0;
      color: var(--mailbox-text-muted);
      letter-spacing: 0.06em;
    }

    .mailbox-app .mailbox-thread-count {
      border-radius: 999px;
      background: linear-gradient(135deg, rgba(37, 99, 235, 0.14), rgba(15, 118, 110, 0.12));
      color: color-mix(in srgb, var(--mailbox-text) 30%, #2563eb 70%);
      font-size: 0.78rem;
      font-weight: 700;
      padding: 0.35rem 0.65rem;
    }

    .mailbox-app .chat-shell {
      display: grid;
      gap: 1rem;
    }

    .mailbox-app .chat-thread-header {
      display: grid;
      gap: 1rem;
    }

    .mailbox-app .chat-thread-hero {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .mailbox-app .chat-thread-options {
      margin-left: auto;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      position: relative;
      z-index: 35;
    }

    .mailbox-app .chat-thread-options-btn {
      width: 38px;
      height: 38px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0;
      border: 1px solid transparent;
      color: var(--messenger-muted);
      background: transparent;
    }

    .mailbox-app .chat-thread-options-btn:hover,
    .mailbox-app .chat-thread-options-btn:focus {
      color: var(--messenger-text);
      background: var(--messenger-chip);
      border-color: var(--messenger-divider);
      box-shadow: none;
    }

    .mailbox-app .chat-thread-options-menu,
    .mailbox-app .messenger-compose-menu {
      background: var(--mailbox-surface-elevated);
      border: 1px solid var(--messenger-divider, #dee2e6);
      border-radius: 0.5rem;
      box-shadow: 0 14px 34px color-mix(in srgb, var(--messenger-text) 16%, transparent);
      color: var(--mailbox-text);
      z-index: 1040;
    }

    .mailbox-app .chat-thread-options-menu .dropdown-item,
    .mailbox-app .messenger-compose-menu .dropdown-item {
      align-items: center;
      background: transparent;
      color: var(--mailbox-text);
      display: flex;
      font-weight: 600;
      gap: 0.25rem;
    }

    .mailbox-app .chat-thread-options-menu .dropdown-item:hover,
    .mailbox-app .chat-thread-options-menu .dropdown-item:focus,
    .mailbox-app .messenger-compose-menu .dropdown-item:hover,
    .mailbox-app .messenger-compose-menu .dropdown-item:focus {
      background: color-mix(in srgb, var(--mailbox-accent-soft) 88%, var(--mailbox-surface-elevated));
      color: var(--mailbox-text);
      outline: none;
    }

    .mailbox-app .chat-thread-options-menu .dropdown-item.text-danger,
    .mailbox-app .messenger-compose-menu .dropdown-item.text-danger {
      color: color-mix(in srgb, #dc3545 78%, var(--mailbox-text)) !important;
    }

    .mailbox-app .chat-thread-options-menu .dropdown-divider,
    .mailbox-app .messenger-compose-menu .dropdown-divider {
      border-top-color: var(--mailbox-border);
    }

    .mailbox-app .chat-thread-kicker {
      margin: 0 0 0.35rem;
      font-size: 0.74rem;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--mailbox-text-muted);
    }

    .mailbox-app .chat-thread-meta-line {
      display: flex;
      flex-wrap: wrap;
      gap: 0.45rem 0.8rem;
      margin-top: 0.55rem;
      color: var(--mailbox-text-muted);
      font-size: 0.84rem;
    }

    .mailbox-app .chat-thread-search {
      flex: 1 1 240px;
      max-width: 320px;
      min-width: min(100%, 220px);
    }

    .mailbox-app .chat-thread-search .form-control {
      border-radius: 999px;
    }

    .mailbox-app .chat-thread-search-status {
      margin-top: 0.4rem;
      color: var(--mailbox-text-muted);
      font-size: 0.78rem;
      text-align: right;
    }

    .mailbox-app .chat-status-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .mailbox-app .chat-typing-indicator {
      display: inline-flex;
      align-items: center;
      gap: 0.65rem;
      min-height: 2rem;
      color: var(--mailbox-text-muted);
      font-size: 0.82rem;
      font-weight: 600;
    }

    .mailbox-app .conversation-scroll > .chat-typing-indicator.reply.theirs {
      display: flex;
      align-items: center;
      gap: 0.55rem;
      width: fit-content;
      min-width: 0;
      max-width: min(48%, 320px);
      margin: 0 0 1rem 0;
      padding: 0.65rem 0.85rem;
      border-radius: 1.1rem;
      border-bottom-left-radius: 0.35rem;
      background: var(--messenger-bubble, color-mix(in srgb, var(--mailbox-surface) 92%, transparent));
      border: 1px solid var(--messenger-divider, var(--mailbox-border));
      color: var(--messenger-text, var(--mailbox-text));
      box-shadow: 0 8px 22px color-mix(in srgb, var(--messenger-text, var(--mailbox-text)) 8%, transparent);
      clear: both;
    }

    .mailbox-app .conversation-scroll > .chat-typing-indicator[hidden] {
      display: none !important;
    }

    .mailbox-app .chat-system-event {
      clear: both;
      display: flex;
      justify-content: center;
      margin: 0.35rem 0 0.85rem;
      color: var(--mailbox-text-muted);
      font-size: 0.76rem;
      line-height: 1.35;
    }

    .mailbox-app .chat-system-event span {
      max-width: min(100%, 26rem);
      padding: 0.18rem 0.55rem;
      border-radius: 999px;
      background: color-mix(in srgb, var(--mailbox-surface) 72%, transparent);
      border: 1px solid color-mix(in srgb, var(--mailbox-border) 65%, transparent);
      text-align: center;
    }

    .mailbox-app .chat-typing-dots {
      display: inline-flex;
      align-items: center;
      gap: 0.2rem;
    }

    .mailbox-app .chat-typing-dots span {
      width: 0.42rem;
      height: 0.42rem;
      border-radius: 999px;
      background: #60a5fa;
      animation: mailboxTypingBounce 1s infinite ease-in-out;
    }

    .mailbox-app .chat-typing-dots span:nth-child(2) {
      animation-delay: 0.15s;
    }

    .mailbox-app .chat-typing-dots span:nth-child(3) {
      animation-delay: 0.3s;
    }

    @keyframes mailboxTypingBounce {
      0%, 80%, 100% { transform: translateY(0); opacity: 0.45; }
      40% { transform: translateY(-3px); opacity: 1; }
    }

    .mailbox-app .mailbox-detail-actions {
      margin: 0.9rem 0 1rem;
    }

    .mailbox-app .mailbox-detail-actions .btn-group {
      flex-wrap: wrap;
      gap: 0.45rem;
    }

    .mailbox-app .mailbox-detail-actions .btn {
      border-radius: 999px;
      font-weight: 600;
    }

    .mailbox-app .reply {
      display: table;
      clear: both;
      max-width: 82%;
      min-width: 120px;
      margin-bottom: 1rem;
      padding: 0.9rem 1rem;
      border-radius: 0.75rem;
      word-break: break-word;
      box-shadow: 0 12px 20px rgba(15, 23, 42, 0.06);
    }

    .mailbox-app .chat-bubble-body {
      white-space: normal;
      line-height: 1.5;
      font-size: 0.95rem;
    }

    .mailbox-app .chat-mention-chip {
      display: inline-flex;
      align-items: center;
      max-width: 100%;
      padding: 0.08rem 0.4rem;
      border-radius: 999px;
      background: #fff3cd;
      color: #5c3b00;
      box-shadow: inset 0 0 0 1px rgba(133, 100, 4, 0.28);
      font-weight: 800;
      white-space: nowrap;
    }

    .mailbox-app .reply.mine .chat-mention-chip {
      background: #fef08a;
      color: #422006;
      box-shadow: inset 0 0 0 1px rgba(113, 63, 18, 0.32);
    }

    body.dark-mode .mailbox-app .chat-mention-chip,
    body[data-theme="dark"] .mailbox-app .chat-mention-chip {
      background: #fde68a;
      color: #451a03;
      box-shadow: inset 0 0 0 1px rgba(251, 191, 36, 0.45);
    }

    .mailbox-app .reply.mine {
      margin-left: auto;
      background: linear-gradient(180deg, rgba(13, 110, 253, 0.22) 0%, rgba(13, 110, 253, 0.12) 100%);
      border: 1px solid rgba(13, 110, 253, 0.16);
      border-bottom-right-radius: 0.2rem;
    }

    .mailbox-app .reply.theirs {
      margin-right: auto;
      background: color-mix(in srgb, var(--mailbox-surface) 92%, transparent);
      border: 1px solid var(--mailbox-border);
      border-bottom-left-radius: 0.2rem;
    }

    .mailbox-app .reply-meta {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
      font-size: 0.8rem;
      color: var(--mailbox-text-muted);
      margin-bottom: 0.5rem;
    }

    .mailbox-app .reply-head {
      display: flex;
      align-items: center;
      gap: 0.7rem;
      margin-bottom: 0.5rem;
      flex-wrap: wrap;
    }

    .mailbox-app .reply.mine .reply-head {
      flex-direction: row-reverse;
    }

    .mailbox-app .reply-tools {
      position: relative;
      display: flex;
      margin-left: auto;
    }

    .mailbox-app .reply-menu-trigger,
    .mailbox-app .reply-quote-trigger {
      width: 28px;
      height: 28px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0;
      line-height: 1;
      border-color: transparent;
      background: transparent;
      color: #ced4da;
      box-shadow: none;
    }

    .mailbox-app .reply-menu-trigger:hover,
    .mailbox-app .reply-menu-trigger:focus,
    .mailbox-app .reply-quote-trigger:hover,
    .mailbox-app .reply-quote-trigger:focus {
      background: rgba(255, 255, 255, 0.08);
      color: #fff;
      border-color: transparent;
      box-shadow: none;
    }

    .mailbox-app .chat-quote-preview {
      max-width: 100%;
      margin-bottom: 0.55rem;
      padding: 0.5rem 0.65rem;
      border-left: 3px solid color-mix(in srgb, var(--messenger-accent, #2374e1) 76%, transparent);
      border-radius: 0.55rem;
      background: color-mix(in srgb, var(--mailbox-surface, #fff) 70%, transparent);
      color: var(--mailbox-text, #212529);
    }

    .mailbox-app .chat-quote-preview-author,
    .mailbox-app .reply-quote-composer-author {
      font-size: 0.76rem;
      font-weight: 800;
      line-height: 1.25;
      color: var(--messenger-accent, #2374e1);
    }

    .mailbox-app .chat-quote-preview-text,
    .mailbox-app .reply-quote-composer-text {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      color: var(--messenger-muted, #6c757d);
      font-size: 0.84rem;
      line-height: 1.35;
    }

    .mailbox-app .reply-menu-dropdown {
      position: absolute;
      top: calc(100% + 0.3rem);
      right: 0;
      min-width: 130px;
      padding: 0.35rem;
      border-radius: 0.65rem;
      background: #1f2937;
      border: 1px solid rgba(255, 255, 255, 0.08);
      box-shadow: 0 12px 28px rgba(15, 23, 42, 0.28);
      z-index: 20;
    }

    .mailbox-app .reply-menu-item {
      width: 100%;
      border: 0;
      background: transparent;
      color: #e5e7eb;
      text-align: left;
      border-radius: 0.45rem;
      padding: 0.45rem 0.6rem;
      font-size: 0.84rem;
    }

    .mailbox-app .reply-menu-item:hover,
    .mailbox-app .reply-menu-item:focus {
      background: rgba(255, 255, 255, 0.08);
      outline: none;
    }

    .mailbox-app .reply-menu-item-danger {
      color: #fca5a5;
    }

    .mailbox-app .reply-deleted {
      opacity: 0.9;
      border: 1px dashed rgba(255, 255, 255, 0.18);
    }

    .mailbox-app .reply-deleted-copy {
      font-style: italic;
      color: #ced4da;
    }

    .mailbox-app .reply-avatar {
      width: 34px;
      height: 34px;
      border-radius: 999px;
      object-fit: cover;
      border: 2px solid rgba(255, 255, 255, 0.18);
      box-shadow: 0 6px 14px rgba(15, 23, 42, 0.16);
      flex-shrink: 0;
    }

    .mailbox-app .attachments {
      margin-top: 0.75rem;
    }

    .mailbox-app .attachments-list {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      margin-top: 0.5rem;
    }

    .mailbox-app .attachment-thumb {
      width: auto;
      max-width: min(280px, 100%);
      padding: 0;
      border-radius: 0.45rem;
      background: transparent;
      color: #343a40;
      border: 0;
      cursor: pointer;
      overflow: hidden;
    }

    .mailbox-app .attachment-thumb img,
    .mailbox-app .attachment-thumb video {
      display: block;
      max-width: min(280px, 100%);
      max-height: 220px;
      width: auto;
      height: auto;
      object-fit: contain;
      border-radius: 0.45rem;
    }

    .mailbox-app .attachment-thumb audio {
      width: 100%;
      height: 42px;
    }

    .mailbox-app .attachment-thumb .filename {
      font-size: 0.75rem;
      margin-top: 0.25rem;
      padding: 0.45rem 0.6rem;
      word-break: break-word;
      border: 1px solid var(--mailbox-border);
      border-radius: 0.45rem;
      background: var(--mailbox-surface-muted);
    }

    .mailbox-app .reply-form-shell {
      border: 1px solid var(--mailbox-border);
      border-radius: 0.5rem;
      padding: 1rem;
      background: linear-gradient(180deg, var(--mailbox-surface) 0%, var(--mailbox-surface-muted) 100%);
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .mailbox-app .chat-composer-shell {
      position: sticky;
      bottom: 0;
      z-index: 2;
      border-radius: 1rem;
    }

    .mailbox-app .reply-quote-composer {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 0.65rem;
      margin-bottom: 0.65rem;
      padding: 0.65rem 0.75rem;
      border-radius: 0.75rem;
      border: 1px solid var(--mailbox-border);
      border-left: 3px solid var(--messenger-accent, #2374e1);
      background: color-mix(in srgb, var(--messenger-chip, rgba(15, 23, 42, 0.06)) 88%, transparent);
    }

    .mailbox-app .reply-quote-composer[hidden] {
      display: none;
    }

    .mailbox-app .reply-quote-composer-copy {
      min-width: 0;
      flex: 1 1 auto;
    }

    .mailbox-app .reply-quote-clear {
      width: 1.65rem;
      height: 1.65rem;
      border: 0;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: var(--messenger-muted, #6c757d);
      background: transparent;
      flex: 0 0 auto;
    }

    .mailbox-app .reply-quote-clear:hover,
    .mailbox-app .reply-quote-clear:focus {
      color: var(--messenger-text, #212529);
      background: var(--messenger-chip, rgba(15, 23, 42, 0.06));
      outline: none;
    }

    .mailbox-app .reply-compose-tools {
      display: flex;
      justify-content: flex-end;
    }

    .mailbox-app .emoji-tools {
      position: relative;
      display: inline-flex;
    }

    .mailbox-app .emoji-menu,
    .compose-modal .emoji-menu {
      position: fixed;
      width: min(328px, calc(100vw - 1.5rem));
      padding: 0.65rem;
      border-radius: 0.95rem;
      background: color-mix(in srgb, var(--messenger-panel-strong) 88%, rgba(15, 23, 42, 0.86) 12%);
      border: 1px solid color-mix(in srgb, var(--messenger-divider) 72%, rgba(255, 255, 255, 0.18) 28%);
      box-shadow: 0 18px 42px rgba(0, 0, 0, 0.28);
      display: block;
      z-index: 1075;
      color: var(--messenger-text, #212529);
      backdrop-filter: blur(12px);
    }

    .compose-modal .emoji-menu.is-modal-bound {
      position: absolute;
    }

    .mailbox-app .emoji-menu[hidden],
    .compose-modal .emoji-menu[hidden] {
      display: none;
    }

    .mailbox-app .emoji-item,
    .compose-modal .emoji-item {
      border: 0;
      background: transparent;
      border-radius: 0.55rem;
      width: 2rem;
      height: 2rem;
      padding: 0;
      font-size: 1.1rem;
      line-height: 1;
      color: inherit;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .mailbox-app .emoji-item:hover,
    .mailbox-app .emoji-item:focus,
    .compose-modal .emoji-item:hover,
    .compose-modal .emoji-item:focus {
      background: color-mix(in srgb, var(--messenger-accent) 16%, transparent);
      outline: none;
    }

    .mailbox-app .emoji-panel-head,
    .compose-modal .emoji-panel-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.5rem;
      margin-bottom: 0.5rem;
      color: var(--messenger-muted, #6c757d);
      font-size: 0.74rem;
      font-weight: 800;
      letter-spacing: 0.02em;
      text-transform: uppercase;
    }

    .mailbox-app .emoji-panel-cats,
    .compose-modal .emoji-panel-cats {
      display: inline-flex;
      align-items: center;
      gap: 0.18rem;
      color: var(--messenger-muted);
    }

    .mailbox-app .emoji-panel-cat,
    .compose-modal .emoji-panel-cat {
      width: 1.55rem;
      height: 1.55rem;
      border: 0;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: color-mix(in srgb, var(--messenger-chip) 82%, transparent);
      font-size: 0.82rem;
      cursor: pointer;
      color: inherit;
      pointer-events: auto;
      padding: 0;
    }

    .mailbox-app .emoji-panel-cat:hover,
    .mailbox-app .emoji-panel-cat:focus,
    .compose-modal .emoji-panel-cat:hover,
    .compose-modal .emoji-panel-cat:focus {
      background: color-mix(in srgb, var(--messenger-accent) 22%, transparent);
      outline: none;
    }

    .mailbox-app .emoji-panel-search,
    .compose-modal .emoji-panel-search {
      width: 100%;
      height: 2rem;
      border-radius: 0.7rem;
      border: 1px solid var(--messenger-divider);
      background: color-mix(in srgb, var(--messenger-chip) 84%, transparent);
      color: var(--messenger-text);
      font-size: 0.82rem;
      padding: 0.35rem 0.65rem;
      margin-bottom: 0.55rem;
      box-shadow: none;
    }

    .mailbox-app .emoji-panel-search::placeholder,
    .compose-modal .emoji-panel-search::placeholder {
      color: var(--messenger-muted);
    }

    .mailbox-app .emoji-grid,
    .compose-modal .emoji-grid {
      display: grid;
      grid-template-columns: repeat(8, minmax(0, 1fr));
      gap: 0.28rem;
      max-height: min(18rem, 42vh);
      overflow-y: auto;
      padding-right: 0.15rem;
    }

    .mailbox-app .emoji-panel-section,
    .compose-modal .emoji-panel-section {
      display: contents;
    }

    .mailbox-app .emoji-panel-label,
    .compose-modal .emoji-panel-label {
      grid-column: 1 / -1;
      margin-top: 0.35rem;
      color: var(--messenger-muted);
      font-size: 0.68rem;
      font-weight: 800;
      text-transform: uppercase;
    }

    .mailbox-app .reply-actions {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-top: 0.75rem;
    }

    .mailbox-app .reply-form-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      margin-bottom: 0.85rem;
    }

    .mailbox-app .reply-form-header h6 {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--mailbox-text);
    }

    .mailbox-app .reply-form-caption {
      margin-top: 0.2rem;
      color: var(--mailbox-text-muted);
      font-size: 0.8rem;
    }

    .mailbox-app .reply-form-hint {
      color: var(--mailbox-text-muted);
      font-size: 0.82rem;
    }

    .mailbox-app .attachment-limit-hint {
      color: var(--mailbox-text-muted);
      font-size: 0.78rem;
      line-height: 1.35;
    }

    .mailbox-app .attachment-validation-message {
      color: #dc3545;
      font-size: 0.82rem;
      line-height: 1.35;
    }

    .mailbox-app .chat-mentions-shell {
      position: relative;
    }

    .mailbox-app .chat-mentions-shell.is-mentioning {
      z-index: 2200;
    }

    .mailbox-app .composer-mention-preview,
    .compose-modal .composer-mention-preview {
      position: absolute;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      overflow: hidden;
      white-space: pre-wrap;
      word-break: break-word;
      color: var(--mailbox-text);
      font: inherit;
      line-height: 1.5;
    }

    .mailbox-app .composer-mention-preview {
      min-height: 104px;
      padding: 0.375rem 4.75rem 0.375rem 0.75rem;
      border: 1px solid transparent;
      border-radius: 1rem;
    }

    .compose-modal .composer-mention-preview {
      min-height: 160px;
      padding: 0.75rem 4.75rem 0.75rem 0.85rem;
      border: 1px solid transparent;
      border-radius: 1rem;
    }

    .mailbox-app .has-mention-preview-text .chat-reply-textarea,
    .compose-modal .has-mention-preview-text #composeMessage {
      color: transparent !important;
      caret-color: var(--mailbox-text, #212529);
      background: transparent !important;
      position: relative;
      z-index: 1;
    }

    .mailbox-app .composer-mention-chip,
    .compose-modal .composer-mention-chip {
      display: inline;
      padding: 0.08rem 0.35rem;
      border-radius: 999px;
      background: #fef08a;
      color: #422006;
      box-shadow: inset 0 0 0 1px rgba(113, 63, 18, 0.32);
      font-weight: 800;
    }

    .mailbox-app .chat-reply-textarea {
      min-height: 104px;
      border-radius: 1rem;
      resize: vertical;
    }

    .mailbox-app .chat-mention-menu {
      position: absolute;
      left: 0;
      right: 0;
      bottom: calc(100% + 0.35rem);
      top: auto;
      max-height: min(14rem, 38vh);
      overflow-y: auto;
      border: 1px solid var(--mailbox-border);
      border-radius: 0.85rem;
      background: var(--mailbox-surface-elevated);
      box-shadow: var(--mailbox-shadow);
      padding: 0.35rem;
      display: grid;
      gap: 0.25rem;
      z-index: 1095;
    }

    .mailbox-app .chat-mention-menu[hidden] {
      display: none;
    }

    .mailbox-app .chat-mention-item {
      border: 0;
      background: transparent;
      color: var(--mailbox-text);
      text-align: left;
      border-radius: 0.65rem;
      padding: 0.55rem 0.75rem;
      font-size: 0.9rem;
      font-weight: 600;
    }

    .mailbox-app .chat-mention-item:hover,
    .mailbox-app .chat-mention-item:focus,
    .mailbox-app .chat-mention-item.is-active {
      background: color-mix(in srgb, var(--mailbox-accent-soft) 84%, transparent);
      outline: none;
    }

    .mailbox-app .chat-reactions {
      margin-top: 0.75rem;
    }

    .mailbox-app .chat-reaction-summary {
      display: flex;
      flex-wrap: wrap;
      gap: 0.35rem;
      align-items: center;
    }

    .mailbox-app .chat-reaction-chip,
    .mailbox-app .chat-reaction-add,
    .mailbox-app .chat-reaction-trigger {
      border: 1px solid var(--mailbox-border);
      border-radius: 999px;
      background: color-mix(in srgb, var(--mailbox-surface) 94%, transparent);
      color: var(--mailbox-text);
      min-height: 30px;
      padding: 0.2rem 0.55rem;
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      font-size: 0.8rem;
      font-weight: 700;
      line-height: 1;
    }

    .mailbox-app .chat-reaction-chip,
    .mailbox-app .chat-reaction-add,
    .mailbox-app .chat-reaction-trigger,
    .compose-modal .compose-tool-btn {
      transition: background-color 0.16s ease, border-color 0.16s ease, color 0.16s ease, box-shadow 0.16s ease;
    }

    .mailbox-app .chat-reaction-chip:hover,
    .mailbox-app .chat-reaction-chip:focus,
    .mailbox-app .chat-reaction-add:hover,
    .mailbox-app .chat-reaction-add:focus,
    .mailbox-app .chat-reaction-trigger:hover,
    .mailbox-app .chat-reaction-trigger:focus {
      background: color-mix(in srgb, var(--mailbox-accent-soft) 78%, var(--mailbox-surface));
      border-color: color-mix(in srgb, var(--mailbox-accent-strong) 42%, var(--mailbox-border));
      color: var(--mailbox-text);
      outline: none;
    }

    .mailbox-app .chat-reaction-chip.is-active {
      border-color: rgba(37, 99, 235, 0.36);
      background: linear-gradient(135deg, rgba(37, 99, 235, 0.16), rgba(15, 118, 110, 0.14));
      color: color-mix(in srgb, var(--mailbox-text) 30%, #2563eb 70%);
    }

    .mailbox-app .chat-reaction-chip[data-reaction-details] {
      position: relative;
    }

    .mailbox-app .chat-reaction-chip[data-reaction-details]:hover::after,
    .mailbox-app .chat-reaction-chip[data-reaction-details]:focus::after {
      content: attr(data-reaction-details);
      position: absolute;
      left: 50%;
      bottom: calc(100% + 0.45rem);
      transform: translateX(-50%);
      max-width: min(260px, 80vw);
      width: max-content;
      padding: 0.4rem 0.55rem;
      border-radius: 0.45rem;
      border: 1px solid var(--mailbox-border);
      background: var(--mailbox-surface-elevated);
      color: var(--mailbox-text);
      box-shadow: var(--mailbox-shadow);
      font-size: 0.78rem;
      font-weight: 600;
      line-height: 1.25;
      pointer-events: none;
      white-space: normal;
      z-index: 30;
    }

    .mailbox-app .chat-reaction-add,
    .mailbox-app .chat-reaction-trigger {
      font-size: 0.95rem;
      padding-inline: 0.45rem;
    }

    .mailbox-app .chat-reaction-picker-wrap {
      position: relative;
      display: inline-flex;
      align-items: center;
    }

    .mailbox-app .chat-reaction-trigger {
      justify-content: center;
      min-width: 30px;
      cursor: pointer;
      position: relative;
      z-index: 1;
    }

    .chat-reaction-picker {
      position: fixed;
      left: 0;
      top: 0;
      right: auto;
      width: min(328px, calc(100vw - 1rem));
      padding: 0.6rem;
      border-radius: 0.85rem;
      background: var(--mailbox-surface-elevated);
      border: 1px solid var(--mailbox-border);
      box-shadow: 0 12px 28px color-mix(in srgb, var(--messenger-text) 14%, transparent);
      display: block;
      z-index: 2140;
      color: var(--mailbox-text);
      pointer-events: auto;
    }

    .chat-reaction-picker .emoji-panel-head,
    .chat-reaction-picker .emoji-panel-cats {
      display: flex;
      align-items: center;
      gap: 0.25rem;
      justify-content: space-between;
    }

    .chat-reaction-picker .emoji-panel-cat {
      width: 1.55rem;
      height: 1.55rem;
      border: 0;
      border-radius: 999px;
      background: color-mix(in srgb, var(--messenger-chip, rgba(15, 23, 42, 0.06)) 82%, transparent);
      color: inherit;
      cursor: pointer;
      padding: 0;
    }

    .chat-reaction-picker .emoji-panel-search {
      width: 100%;
      height: 2rem;
      border-radius: 0.7rem;
      border: 1px solid var(--mailbox-border);
      background: color-mix(in srgb, var(--messenger-chip, rgba(15, 23, 42, 0.06)) 84%, transparent);
      color: var(--mailbox-text);
      font-size: 0.82rem;
      padding: 0.35rem 0.65rem;
      margin: 0.55rem 0;
    }

    .chat-reaction-picker .emoji-grid {
      display: grid;
      grid-template-columns: repeat(8, minmax(0, 1fr));
      gap: 0.28rem;
      max-height: min(18rem, 42vh);
      overflow-y: auto;
    }

    .chat-reaction-picker .emoji-panel-label {
      grid-column: 1 / -1;
      margin-top: 0.35rem;
      color: var(--mailbox-text);
      opacity: 0.72;
      font-size: 0.68rem;
      font-weight: 800;
      text-transform: uppercase;
    }

    .chat-reaction-picker[hidden] {
      display: none;
    }

    .chat-reaction-picker .chat-reaction-add {
      border: 1px solid var(--mailbox-border, #d9e2ec);
      border-radius: 999px;
      background: color-mix(in srgb, var(--mailbox-surface, #ffffff) 94%, transparent);
      color: var(--mailbox-text, #1f2937);
      min-height: 30px;
      padding: 0.2rem 0.45rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.95rem;
      font-weight: 700;
      line-height: 1;
      position: relative;
      z-index: 1;
    }

    .chat-reaction-picker .chat-reaction-add:hover,
    .chat-reaction-picker .chat-reaction-add:focus {
      background: color-mix(in srgb, var(--mailbox-accent-soft, rgba(13, 110, 253, 0.1)) 78%, var(--mailbox-surface, #ffffff));
      border-color: color-mix(in srgb, var(--mailbox-accent-strong, #2563eb) 42%, var(--mailbox-border, #d9e2ec));
      color: var(--mailbox-text, #1f2937);
      outline: none;
    }

    body.dark-mode .chat-reaction-picker,
    body[data-theme="dark"] .chat-reaction-picker {
      --mailbox-surface: #1f2a37;
      --mailbox-surface-elevated: #223041;
      --mailbox-border: rgba(255, 255, 255, 0.09);
      --mailbox-text: #f3f4f6;
      --mailbox-accent-soft: rgba(96, 165, 250, 0.18);
      --mailbox-accent-strong: #93c5fd;
      --messenger-text: #f5f7fb;
    }

    .group-members-list {
      display: grid;
      gap: 0.65rem;
      max-height: min(62vh, 520px);
      overflow-y: auto;
      padding-right: 0.2rem;
    }

    #groupMembersModal .modal-content {
      background: linear-gradient(180deg, var(--mailbox-surface) 0%, var(--mailbox-surface-muted) 100%);
      border: 1px solid var(--mailbox-border);
      border-radius: 1rem;
      box-shadow: var(--mailbox-shadow);
      color: var(--mailbox-text);
      overflow: hidden;
      max-height: 80vh;
    }

    #groupComposeModal .modal-content {
      max-height: 80vh;
      overflow: hidden;
    }

    #groupComposeModal .modal-header,
    #groupComposeModal .modal-footer {
      flex: 0 0 auto;
    }

    #groupComposeModal .modal-body {
      overflow-y: auto;
      min-height: 0;
    }

    #groupMembersModal .modal-header,
    #groupMembersModal .modal-footer {
      background: color-mix(in srgb, var(--mailbox-surface) 92%, transparent);
      border-color: var(--mailbox-border);
      flex: 0 0 auto;
    }

    #groupMembersModal .modal-body {
      overflow-y: auto;
      min-height: 0;
    }

    #groupMembersModal .modal-title {
      color: var(--mailbox-text);
      font-weight: 800;
    }

    #groupMembersModal .text-muted {
      color: var(--mailbox-text-muted) !important;
    }

    #groupMembersModal .close {
      color: var(--mailbox-text);
      opacity: 0.78;
      text-shadow: none;
    }

    #groupMembersModal .close:hover,
    #groupMembersModal .close:focus {
      color: var(--mailbox-text);
      opacity: 1;
      outline: none;
    }

    .group-member-row {
      align-items: center;
      background: color-mix(in srgb, var(--mailbox-surface) 94%, transparent);
      border: 1px solid var(--messenger-divider);
      border-radius: 0.65rem;
      display: flex;
      gap: 0.75rem;
      padding: 0.65rem 0.75rem;
    }

    .group-member-row:hover {
      background: color-mix(in srgb, var(--mailbox-accent-soft) 42%, var(--mailbox-surface));
      border-color: color-mix(in srgb, var(--mailbox-accent-strong) 24%, var(--mailbox-border));
    }

    .group-member-avatar {
      border-radius: 50%;
      flex: 0 0 42px;
      height: 42px;
      object-fit: cover;
      width: 42px;
    }

    .group-member-main {
      min-width: 0;
      flex: 1 1 auto;
    }

    .group-member-name,
    .group-member-email {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .group-member-name {
      color: var(--messenger-text);
      font-weight: 700;
    }

    .group-member-email {
      color: var(--messenger-muted);
      font-size: 0.82rem;
    }

    .group-member-badges {
      display: flex;
      flex-wrap: wrap;
      gap: 0.3rem;
      margin-top: 0.35rem;
    }

    .group-member-badges .badge {
      border: 1px solid transparent;
      font-weight: 700;
    }

    .group-member-badges .badge-info {
      background: color-mix(in srgb, var(--mailbox-accent-strong) 16%, var(--mailbox-surface));
      border-color: color-mix(in srgb, var(--mailbox-accent-strong) 28%, var(--mailbox-border));
      color: color-mix(in srgb, var(--mailbox-accent-strong) 82%, var(--mailbox-text));
    }

    .group-member-badges .badge-success {
      background: color-mix(in srgb, var(--messenger-success) 16%, var(--mailbox-surface));
      border-color: color-mix(in srgb, var(--messenger-success) 30%, var(--mailbox-border));
      color: color-mix(in srgb, var(--messenger-success) 72%, var(--mailbox-text));
    }

    .group-member-badges .badge-warning {
      background: color-mix(in srgb, #f59e0b 18%, var(--mailbox-surface));
      border-color: color-mix(in srgb, #f59e0b 32%, var(--mailbox-border));
      color: color-mix(in srgb, #d97706 78%, var(--mailbox-text));
    }

    .group-member-badges .badge-secondary {
      background: color-mix(in srgb, var(--mailbox-text-muted) 14%, var(--mailbox-surface));
      border-color: color-mix(in srgb, var(--mailbox-text-muted) 28%, var(--mailbox-border));
      color: var(--mailbox-text-muted);
    }

    #groupMembersModal .btn-default,
    #groupMembersModal .btn-outline-danger,
    body.messenger-page .swal2-popup .btn-default {
      background: color-mix(in srgb, var(--mailbox-surface) 94%, transparent);
      border-color: var(--mailbox-border);
      color: var(--mailbox-text);
    }

    #groupMembersModal .btn-default:hover,
    #groupMembersModal .btn-default:focus,
    #groupMembersModal .btn-outline-danger:hover,
    #groupMembersModal .btn-outline-danger:focus {
      background: color-mix(in srgb, var(--mailbox-accent-soft) 68%, var(--mailbox-surface));
      border-color: color-mix(in srgb, var(--mailbox-accent-strong) 34%, var(--mailbox-border));
      color: var(--mailbox-text);
      box-shadow: none;
    }

    #groupMembersModal .btn-outline-danger {
      color: color-mix(in srgb, #dc3545 78%, var(--mailbox-text));
    }

    .mailbox-app .chat-search-hit {
      background: rgba(250, 204, 21, 0.3);
      color: inherit;
      border-radius: 0.25rem;
      padding: 0 0.1rem;
      box-shadow: 0 0 0 1px rgba(250, 204, 21, 0.22);
    }

    .compose-modal .modal-dialog {
      display: flex;
      align-items: stretch;
      min-height: calc(100vh - 1rem);
      max-height: calc(100vh - 1rem);
      margin-top: 0.5rem;
      margin-bottom: 0.5rem;
    }

    .compose-modal .modal-content {
      display: flex;
      flex-direction: column;
      flex: 1 1 auto;
      min-height: 0;
      max-height: 100%;
      border: 1px solid var(--mailbox-border);
      border-radius: 1.35rem;
      background: linear-gradient(180deg, var(--mailbox-surface) 0%, var(--mailbox-surface-muted) 100%);
      box-shadow: var(--mailbox-shadow);
      overflow: hidden;
    }

    .compose-modal .modal-content,
    .compose-modal .modal-body,
    .compose-modal .modal-footer {
      color: var(--mailbox-text);
    }

    .compose-modal .text-muted {
      color: var(--mailbox-text-muted) !important;
    }

    .compose-modal #composeForm {
      display: flex;
      flex: 1 1 auto;
      flex-direction: column;
      min-height: 0;
      overflow: hidden;
    }

    .compose-modal .modal-body {
      flex: 1 1 auto;
      min-height: 0;
      overflow-y: auto;
      overflow-x: hidden;
      -webkit-overflow-scrolling: touch;
    }

    .compose-modal .modal-footer {
      flex-shrink: 0;
    }

    .compose-modal .modal-header,
    .compose-modal .modal-footer {
      border-color: var(--mailbox-border);
      background: color-mix(in srgb, var(--mailbox-surface) 92%, transparent);
    }

    .compose-modal .modal-title {
      color: var(--mailbox-text);
      font-weight: 800;
      letter-spacing: -0.02em;
    }

    .compose-modal .close {
      color: var(--mailbox-text);
      opacity: 0.78;
      text-shadow: none;
    }

    .compose-modal .close:hover,
    .compose-modal .close:focus {
      color: var(--mailbox-text);
      opacity: 1;
    }

    .compose-modal .compose-panel {
      border: 1px solid var(--mailbox-border);
      border-radius: 1.1rem;
      background: color-mix(in srgb, var(--mailbox-surface) 94%, transparent);
      padding: 1rem;
    }

    .compose-modal .compose-panel-head {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 1rem;
      margin-bottom: 0.85rem;
    }

    .compose-modal .compose-panel-head h4 {
      margin: 0 0 0.25rem;
      color: var(--mailbox-text);
      font-size: 1rem;
      font-weight: 800;
    }

    .compose-modal .compose-panel-head p,
    .compose-modal .compose-meta {
      margin: 0;
      color: var(--mailbox-text-muted);
      font-size: 0.88rem;
    }

    .compose-modal .compose-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      border-radius: 999px;
      padding: 0.45rem 0.8rem;
      background: linear-gradient(135deg, rgba(37, 99, 235, 0.14), rgba(15, 118, 110, 0.1));
      color: color-mix(in srgb, var(--mailbox-text) 30%, #2563eb 70%);
      font-size: 0.82rem;
      font-weight: 700;
    }

    .compose-modal .compose-field label,
    .compose-modal .compose-body label {
      display: block;
      color: var(--mailbox-text);
      font-size: 0.84rem;
      font-weight: 700;
      margin-bottom: 0.45rem;
    }

    .compose-modal .compose-field + .compose-field,
    .compose-modal .compose-body {
      margin-top: 0.95rem;
    }

    .compose-modal .compose-field .form-control,
    .compose-modal .compose-body .form-control,
    .compose-modal .compose-attachment-label {
      border-radius: 0.95rem;
      border: 1px solid var(--mailbox-border);
      background: color-mix(in srgb, var(--mailbox-surface) 94%, transparent);
      color: var(--mailbox-text);
      box-shadow: none;
    }

    .compose-modal .compose-field .form-control::placeholder,
    .compose-modal .compose-body .form-control::placeholder,
    .mailbox-app .chat-reply-textarea::placeholder {
      color: color-mix(in srgb, var(--mailbox-text-muted) 76%, transparent);
      opacity: 1;
    }

    .compose-modal .form-control:disabled,
    .compose-modal .form-control[readonly],
    #groupMembersModal .btn:disabled,
    .mailbox-app .chat-reaction-chip:disabled,
    .mailbox-app .chat-reaction-add:disabled,
    .mailbox-app .chat-reaction-trigger:disabled {
      cursor: not-allowed;
      opacity: 0.62;
    }

    .compose-modal .compose-field .form-control:focus,
    .compose-modal .compose-body .form-control:focus {
      border-color: color-mix(in srgb, var(--mailbox-accent-strong) 55%, var(--mailbox-border));
      box-shadow: 0 0 0 0.22rem color-mix(in srgb, var(--mailbox-accent-soft) 88%, transparent);
    }

    .compose-modal .compose-message-shell {
      position: relative;
    }

    .compose-modal .compose-message-shell .form-control {
      min-height: 154px;
      padding-right: 3.4rem;
    }

    .compose-modal .compose-message-shell.has-file-preview .form-control {
      min-height: 224px;
      padding-bottom: 5rem;
    }

    .compose-modal .compose-tool-stack {
      position: absolute;
      right: 0.7rem;
      bottom: 0.55rem;
      display: inline-flex;
      flex-direction: column;
      gap: 0.4rem;
      z-index: 4;
    }

    .compose-modal .compose-tool-btn {
      width: 2.2rem;
      height: 2.2rem;
      border: 1px solid var(--mailbox-border);
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: color-mix(in srgb, var(--mailbox-surface) 94%, transparent);
      color: var(--mailbox-text-muted);
      transition: background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease;
    }

    .compose-modal .compose-tool-btn:hover,
    .compose-modal .compose-tool-btn:focus {
      outline: none;
      color: var(--mailbox-accent-strong);
      border-color: color-mix(in srgb, var(--mailbox-accent-strong) 45%, var(--mailbox-border));
      background: color-mix(in srgb, var(--mailbox-accent-soft) 78%, transparent);
    }

    .compose-modal .compose-send-btn {
      background: var(--mailbox-accent-strong);
      border-color: var(--mailbox-accent-strong);
      color: #fff;
    }

    .compose-modal .compose-send-btn:hover,
    .compose-modal .compose-send-btn:focus {
      background: color-mix(in srgb, var(--mailbox-accent-strong) 84%, #000 16%);
      border-color: color-mix(in srgb, var(--mailbox-accent-strong) 84%, #000 16%);
      color: #fff;
    }

    .compose-modal .compose-message-shell .emoji-menu {
      z-index: 1080;
    }

    .compose-modal .compose-file-summary {
      margin-top: 0.65rem;
      color: var(--mailbox-text-muted);
      font-size: 0.85rem;
    }

    .compose-modal .compose-attachment-meta {
      display: grid;
      gap: 0.25rem;
      margin-top: 0.65rem;
    }

    .compose-modal .compose-file-preview {
      position: absolute;
      left: 0.65rem;
      bottom: 0.65rem;
      z-index: 3;
      display: flex;
      flex-direction: row;
      align-items: flex-start;
      flex-wrap: nowrap;
      gap: 0.45rem;
      margin: 0;
      max-width: min(360px, calc(100% - 4.7rem));
      max-height: 62px;
      overflow: visible;
      padding: 0.2rem 0.35rem 0.2rem 0.2rem;
    }

    .compose-modal .compose-file-preview:empty {
      display: none;
    }

    .compose-modal .compose-file-card {
      position: relative;
      width: 58px;
      height: 58px;
      min-height: 58px;
      border-radius: 0.85rem;
      border: 1px solid var(--mailbox-border);
      background: color-mix(in srgb, var(--mailbox-surface) 94%, transparent);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0;
      overflow: visible;
      box-shadow: 0 10px 18px rgba(15, 23, 42, 0.08);
      color: var(--mailbox-text-muted);
      flex: 0 0 58px;
      transition: transform 0.16s ease;
    }

    .compose-modal .compose-file-card img,
    .compose-modal .compose-file-card video,
    .compose-modal .compose-file-card .compose-file-icon {
      width: 100%;
      height: 100%;
      border-radius: 0.75rem;
      flex: 0 0 100%;
      object-fit: cover;
    }

    .compose-modal .compose-file-card audio {
      width: 100%;
      align-self: center;
    }

    .compose-modal .compose-file-card .compose-file-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: color-mix(in srgb, var(--mailbox-surface-muted) 90%, transparent);
      color: var(--mailbox-text-muted);
    }

    .compose-modal .compose-file-name {
      position: absolute;
      left: 0.25rem;
      right: 0.25rem;
      bottom: 0.18rem;
      padding: 0.08rem 0.22rem;
      border-radius: 0.35rem;
      background: rgba(15, 23, 42, 0.72);
      color: #fff;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      font-size: 0.62rem;
      line-height: 1.1;
      font-weight: 700;
      text-align: center;
      pointer-events: none;
    }

    .compose-modal .compose-file-preview.is-stacked {
      width: 154px;
      max-width: 154px;
    }

    .compose-modal .compose-file-preview.is-stacked .compose-file-card {
      margin-left: -34px;
    }

    .compose-modal .compose-file-preview.is-stacked .compose-file-card:first-child {
      margin-left: 0;
    }

    .compose-modal .compose-file-preview.is-stacked .compose-file-card:hover,
    .compose-modal .compose-file-preview.is-stacked .compose-file-card:focus-within {
      transform: translateY(-4px);
      z-index: 12 !important;
    }

    .compose-modal .compose-file-count {
      width: 58px;
      height: 58px;
      border-radius: 0.85rem;
      margin-left: -34px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: color-mix(in srgb, var(--mailbox-accent-strong) 82%, #111827 18%);
      color: #fff;
      font-size: 0.85rem;
      font-weight: 800;
      box-shadow: 0 10px 18px rgba(15, 23, 42, 0.16);
      z-index: 1;
    }

    .compose-modal .compose-file-remove {
      position: absolute;
      top: -0.35rem;
      right: -0.35rem;
      width: 1.25rem;
      height: 1.25rem;
      border: 0;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0;
      background: #dc3545;
      color: #fff;
      font-size: 0.95rem;
      line-height: 1;
      box-shadow: 0 4px 10px rgba(15, 23, 42, 0.2);
    }

    .compose-modal .compose-file-remove:hover,
    .compose-modal .compose-file-remove:focus {
      background: #bd2130;
      color: #fff;
      outline: none;
    }

    .compose-modal .compose-stats {
      display: flex;
      flex-wrap: wrap;
      gap: 0.45rem 0.75rem;
      margin-top: 0.7rem;
      color: var(--mailbox-text-muted);
      font-size: 0.82rem;
    }

    .compose-modal .compose-stat-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.38rem 0.7rem;
      border-radius: 999px;
      background: color-mix(in srgb, var(--mailbox-surface-muted) 88%, transparent);
      border: 1px solid var(--mailbox-border);
    }

    .mailbox-app .select2-container--bootstrap4 .select2-selection,
    .compose-modal .select2-container--bootstrap4 .select2-selection,
    body.messenger-page .swal2-popup .select2-container--bootstrap4 .select2-selection {
      min-height: 48px;
      border-radius: 0.95rem;
      border-color: var(--mailbox-border);
      background: color-mix(in srgb, var(--mailbox-surface) 94%, transparent);
      color: var(--mailbox-text);
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
    }

    .mailbox-app .select2-container--bootstrap4.select2-container--focus .select2-selection,
    .compose-modal .select2-container--bootstrap4.select2-container--focus .select2-selection,
    body.messenger-page .swal2-popup .select2-container--bootstrap4.select2-container--focus .select2-selection {
      border-color: color-mix(in srgb, var(--mailbox-accent-strong) 55%, var(--mailbox-border));
      box-shadow: 0 0 0 0.22rem color-mix(in srgb, var(--mailbox-accent-soft) 88%, transparent);
    }

    .mailbox-app .select2-container--bootstrap4 .select2-selection__rendered,
    .mailbox-app .select2-container--bootstrap4 .select2-selection__placeholder,
    .compose-modal .select2-container--bootstrap4 .select2-selection__rendered,
    .compose-modal .select2-container--bootstrap4 .select2-selection__placeholder,
    body.messenger-page .swal2-popup .select2-container--bootstrap4 .select2-selection__rendered,
    body.messenger-page .swal2-popup .select2-container--bootstrap4 .select2-selection__placeholder {
      color: var(--mailbox-text);
    }

    .mailbox-app .select2-container--bootstrap4 .select2-selection__placeholder,
    .compose-modal .select2-container--bootstrap4 .select2-selection__placeholder,
    body.messenger-page .swal2-popup .select2-container--bootstrap4 .select2-selection__placeholder {
      opacity: 0.78;
    }

    .mailbox-app .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__rendered,
    .compose-modal .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__rendered,
    body.messenger-page .swal2-popup .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__rendered {
      display: flex;
      flex-wrap: wrap;
      gap: 0.45rem;
      padding: 0.45rem 0.55rem;
    }

    .mailbox-app .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice,
    .compose-modal .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice,
    body.messenger-page .swal2-popup .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      float: none;
      margin: 0;
      padding: 0.22rem 0.55rem 0.22rem 0.28rem;
      border-radius: 999px;
      border: 1px solid var(--mailbox-border);
      background: color-mix(in srgb, var(--mailbox-surface-muted) 92%, transparent);
      color: var(--mailbox-text);
      font-size: 0.78rem;
      font-weight: 600;
      line-height: 1.1;
      max-width: 100%;
    }

    .mailbox-app .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__remove,
    .compose-modal .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__remove,
    body.messenger-page .swal2-popup .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__remove {
      color: var(--mailbox-text-muted);
      margin: 0 0.15rem 0 0;
      font-size: 0.8rem;
      line-height: 1;
    }

    .mailbox-app .select2-container--bootstrap4 .select2-selection--multiple .select2-search__field,
    .compose-modal .select2-container--bootstrap4 .select2-selection--multiple .select2-search__field,
    body.messenger-page .swal2-popup .select2-container--bootstrap4 .select2-selection--multiple .select2-search__field {
      color: var(--mailbox-text);
      background: transparent;
      caret-color: var(--mailbox-accent-strong);
      margin-top: 0 !important;
    }

    .mailbox-app .select2-container--bootstrap4 .select2-dropdown,
    .compose-modal .select2-container--bootstrap4 .select2-dropdown,
    body.messenger-page .swal2-popup .select2-container--bootstrap4 .select2-dropdown {
      background: var(--mailbox-surface-elevated);
      border-color: var(--mailbox-border);
      color: var(--mailbox-text);
      box-shadow: var(--mailbox-shadow);
    }

    .mailbox-app .select2-container--bootstrap4 .select2-search--dropdown,
    .compose-modal .select2-container--bootstrap4 .select2-search--dropdown,
    body.messenger-page .swal2-popup .select2-container--bootstrap4 .select2-search--dropdown {
      padding: 0.55rem;
      background: var(--mailbox-surface-elevated);
      border-bottom: 1px solid var(--mailbox-border);
    }

    .mailbox-app .select2-container--bootstrap4 .select2-search--dropdown .select2-search__field,
    .compose-modal .select2-container--bootstrap4 .select2-search--dropdown .select2-search__field,
    body.messenger-page .swal2-popup .select2-container--bootstrap4 .select2-search--dropdown .select2-search__field {
      border-radius: 0.8rem;
      border: 1px solid var(--mailbox-border);
      background: color-mix(in srgb, var(--mailbox-surface) 92%, transparent);
      color: var(--mailbox-text);
      padding: 0.55rem 0.75rem;
      outline: none;
    }

    .mailbox-app .select2-container--bootstrap4 .select2-search--dropdown .select2-search__field:focus,
    .compose-modal .select2-container--bootstrap4 .select2-search--dropdown .select2-search__field:focus,
    body.messenger-page .swal2-popup .select2-container--bootstrap4 .select2-search--dropdown .select2-search__field:focus {
      border-color: color-mix(in srgb, var(--mailbox-accent-strong) 55%, var(--mailbox-border));
      box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--mailbox-accent-soft) 88%, transparent);
    }

    .mailbox-app .select2-container--bootstrap4 .select2-results__option,
    .compose-modal .select2-container--bootstrap4 .select2-results__option,
    body.messenger-page .swal2-popup .select2-container--bootstrap4 .select2-results__option {
      color: var(--mailbox-text);
      background: transparent;
      padding: 0.6rem 0.8rem;
    }

    .mailbox-app .select2-container--bootstrap4 .select2-results__option[aria-selected="true"],
    .compose-modal .select2-container--bootstrap4 .select2-results__option[aria-selected="true"],
    body.messenger-page .swal2-popup .select2-container--bootstrap4 .select2-results__option[aria-selected="true"] {
      background: color-mix(in srgb, var(--mailbox-accent-soft) 78%, var(--mailbox-surface-elevated));
      color: var(--mailbox-text);
    }

    .mailbox-app .select2-container--bootstrap4 .select2-results__option--highlighted[aria-selected],
    .compose-modal .select2-container--bootstrap4 .select2-results__option--highlighted[aria-selected],
    body.messenger-page .swal2-popup .select2-container--bootstrap4 .select2-results__option--highlighted[aria-selected] {
      background: color-mix(in srgb, var(--mailbox-accent-soft) 92%, var(--mailbox-surface-elevated));
      color: var(--mailbox-text);
    }

    .mailbox-app .select2-container--bootstrap4 .select2-results__option--disabled,
    .compose-modal .select2-container--bootstrap4 .select2-results__option--disabled,
    body.messenger-page .swal2-popup .select2-container--bootstrap4 .select2-results__option--disabled {
      color: var(--mailbox-text-muted);
    }

    .mailbox-app .compose-recipient-option,
    .mailbox-app .compose-recipient-chip,
    .compose-modal .compose-recipient-option,
    .compose-modal .compose-recipient-chip {
      display: flex;
      align-items: center;
      gap: 0.55rem;
      min-width: 0;
    }

    .mailbox-app .compose-recipient-avatar,
    .compose-modal .compose-recipient-avatar {
      width: 16px;
      height: 16px;
      min-width: 16px;
      max-width: 16px;
      min-height: 16px;
      max-height: 16px;
      border-radius: 50%;
      object-fit: cover;
      flex-shrink: 0;
      border: 1px solid rgba(255, 255, 255, 0.3);
      box-shadow: 0 2px 5px rgba(15, 23, 42, 0.12);
      background: color-mix(in srgb, var(--mailbox-surface-muted) 88%, transparent);
    }

    .mailbox-app .compose-recipient-copy,
    .compose-modal .compose-recipient-copy {
      min-width: 0;
    }

    .mailbox-app .compose-recipient-name,
    .compose-modal .compose-recipient-name {
      display: block;
      color: var(--mailbox-text);
      font-size: 0.82rem;
      font-weight: 600;
      line-height: 1.2;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .mailbox-app .compose-recipient-meta,
    .compose-modal .compose-recipient-meta {
      display: block;
      color: var(--mailbox-text-muted);
      font-size: 0.72rem;
      line-height: 1.2;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .mailbox-app .compose-recipient-chip .compose-recipient-meta,
    .compose-modal .compose-recipient-chip .compose-recipient-meta {
      display: none;
    }

    .mailbox-app .compose-recipient-chip .compose-recipient-name,
    .compose-modal .compose-recipient-chip .compose-recipient-name {
      font-size: 0.76rem;
      font-weight: 600;
      line-height: 1.1;
    }

    .mailbox-app .select2-container--bootstrap4 .select2-selection__choice .compose-recipient-chip,
    .compose-modal .select2-container--bootstrap4 .select2-selection__choice .compose-recipient-chip {
      gap: 0.3rem;
    }

    .mailbox-app .select2-container--bootstrap4 .select2-selection__choice .compose-recipient-avatar,
    .compose-modal .select2-container--bootstrap4 .select2-selection__choice .compose-recipient-avatar {
      width: 14px !important;
      height: 14px !important;
      min-width: 14px !important;
      max-width: 14px !important;
      min-height: 14px !important;
      max-height: 14px !important;
      display: inline-block !important;
      vertical-align: middle;
    }

    .mailbox-app .select2-container--bootstrap4 .select2-results__option .compose-recipient-avatar,
    .compose-modal .select2-container--bootstrap4 .select2-results__option .compose-recipient-avatar {
      width: 18px !important;
      height: 18px !important;
      min-width: 18px !important;
      max-width: 18px !important;
      min-height: 18px !important;
      max-height: 18px !important;
      display: inline-block !important;
      vertical-align: middle;
    }

    .mailbox-app .select2-selection__choice__display,
    .mailbox-app .select2-results__option .compose-recipient-option,
    .mailbox-app .select2-selection__choice .compose-recipient-chip,
    .compose-modal .select2-selection__choice__display,
    .compose-modal .select2-results__option .compose-recipient-option,
    .compose-modal .select2-selection__choice .compose-recipient-chip {
      max-width: 100%;
    }

    .mailbox-app .select2-selection__choice__display .compose-recipient-name,
    .compose-modal .select2-selection__choice__display .compose-recipient-name {
      display: inline-block;
      max-width: 140px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    body.messenger-page .swal2-container {
      z-index: 2000;
    }

    body.messenger-page .swal2-popup {
      background: linear-gradient(180deg, var(--mailbox-surface) 0%, var(--mailbox-surface-muted) 100%);
      border: 1px solid var(--mailbox-border);
      border-radius: 1rem;
      box-shadow: var(--mailbox-shadow);
      color: var(--mailbox-text);
    }

    body.messenger-page .swal2-title,
    body.messenger-page .swal2-html-container,
    body.messenger-page .swal2-content {
      color: var(--mailbox-text);
    }

    body.messenger-page .swal2-html-container {
      color: var(--mailbox-text-muted);
    }

    body.messenger-page .swal2-input,
    body.messenger-page .swal2-file,
    body.messenger-page .swal2-select,
    body.messenger-page .swal2-textarea {
      background: color-mix(in srgb, var(--mailbox-surface) 94%, transparent);
      border: 1px solid var(--mailbox-border);
      border-radius: 0.8rem;
      box-shadow: none;
      color: var(--mailbox-text);
    }

    body.messenger-page .swal2-input:focus,
    body.messenger-page .swal2-file:focus,
    body.messenger-page .swal2-select:focus,
    body.messenger-page .swal2-textarea:focus {
      border-color: color-mix(in srgb, var(--mailbox-accent-strong) 55%, var(--mailbox-border));
      box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--mailbox-accent-soft) 88%, transparent);
    }

    body.messenger-page .swal2-validation-message {
      background: color-mix(in srgb, #dc3545 10%, var(--mailbox-surface));
      color: color-mix(in srgb, #dc3545 78%, var(--mailbox-text));
      border: 1px solid color-mix(in srgb, #dc3545 24%, var(--mailbox-border));
    }

    body.messenger-page .swal2-styled:focus {
      box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--mailbox-accent-soft) 88%, transparent);
    }

    body.messenger-page .swal2-confirm.swal2-styled {
      background: var(--mailbox-accent-strong);
      color: #fff;
    }

    body.messenger-page .swal2-deny.swal2-styled {
      background: #dc3545;
      color: #fff;
    }

    body.messenger-page .swal2-cancel.swal2-styled {
      background: color-mix(in srgb, var(--mailbox-surface) 92%, transparent);
      border: 1px solid var(--mailbox-border);
      color: var(--mailbox-text);
    }

    body.messenger-page .swal2-popup .attachment-preview-stage {
      position: relative;
      width: min(90vw, 1100px);
      height: min(75vh, 700px);
      max-width: calc(100vw - 1.5rem);
      max-height: calc(100vh - 5rem);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    body.messenger-page .swal2-popup.attachment-preview-popup {
      background: transparent;
      border: 0;
      box-shadow: none;
      padding: 0;
    }

    body.messenger-page .swal2-popup.attachment-preview-popup .swal2-html-container {
      margin: 0;
      padding: 0;
      overflow: visible;
    }

    body.messenger-page .swal2-popup.attachment-preview-popup .swal2-close {
      color: #fff;
      text-shadow: 0 1px 8px rgba(0, 0, 0, 0.5);
    }

    body.messenger-page .swal2-popup .attachment-preview-media {
      width: 100%;
      height: 100%;
      max-width: 100%;
      max-height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: transparent;
      border: 0;
      box-shadow: none;
    }

    body.messenger-page .swal2-popup .attachment-preview-media > img,
    body.messenger-page .swal2-popup .attachment-preview-media > video,
    body.messenger-page .swal2-popup .attachment-preview-media > iframe {
      width: auto;
      height: auto;
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
      background: transparent;
      border: 0;
      box-shadow: none;
    }

    body.messenger-page .swal2-popup .attachment-preview-media > iframe {
      width: 100%;
      height: 100%;
    }

    body.messenger-page .swal2-popup .attachment-preview-nav {
      position: absolute;
      top: 50%;
      width: 2.35rem;
      height: 2.35rem;
      transform: translateY(-50%);
      border: 1px solid var(--mailbox-border);
      border-radius: 999px;
      background: color-mix(in srgb, var(--mailbox-surface-elevated) 86%, transparent);
      color: var(--mailbox-text);
      box-shadow: 0 8px 24px color-mix(in srgb, var(--mailbox-text) 16%, transparent);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      line-height: 1;
      padding: 0;
      z-index: 2;
    }

    body.messenger-page .swal2-popup .attachment-preview-nav--prev {
      left: 0.35rem;
    }

    body.messenger-page .swal2-popup .attachment-preview-nav--next {
      right: 0.35rem;
    }

    body.messenger-page .swal2-popup .attachment-preview-nav[hidden] {
      display: none;
    }

    .mailbox-app {
      min-height: 100vh;
      background: var(--messenger-bg);
    }

    .mailbox-app .content-wrapper {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      background:
        radial-gradient(circle at top left, color-mix(in srgb, var(--messenger-accent) 12%, transparent), transparent 25%),
        radial-gradient(circle at bottom right, rgba(168, 85, 247, 0.08), transparent 28%),
        linear-gradient(180deg, color-mix(in srgb, var(--messenger-bg) 92%, #ffffff 8%) 0%, color-mix(in srgb, var(--messenger-bg) 98%, #000000 2%) 100%);
    }

    .mailbox-app .content-header {
      display: none;
    }

    .mailbox-app .container-fluid {
      max-width: 1480px;
    }

    .mailbox-app .mailbox-sidebar-card,
    .mailbox-app .mailbox-pane-card {
      border-radius: 1.25rem;
      border-color: var(--messenger-divider);
      background: linear-gradient(180deg, var(--messenger-panel) 0%, var(--messenger-panel-strong) 100%);
      box-shadow: var(--messenger-shadow);
    }

    .mailbox-app .mailbox-pane-card {
      min-height: calc(100vh - 2rem);
    }

    .mailbox-app .messenger-sidebar-shell,
    .mailbox-app .mailbox-read-pane {
      background: transparent;
    }

    .mailbox-app .messenger-sidebar-top,
    .mailbox-app .messenger-detail-frame {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.9rem;
    }

    .mailbox-app .messenger-sidebar-title {
      margin: 0;
      font-size: 1.9rem;
      line-height: 1.05;
      font-weight: 800;
      letter-spacing: -0.03em;
      color: var(--messenger-text);
    }

    .mailbox-app .messenger-sidebar-subtitle {
      margin: 0.25rem 0 0;
      color: var(--messenger-muted);
      font-size: 0.84rem;
    }

    .mailbox-app .messenger-sidebar-actions,
    .mailbox-app .messenger-detail-actions {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .mailbox-app .messenger-icon-btn {
      width: 2.5rem;
      height: 2.5rem;
      border: 0;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: var(--messenger-chip);
      color: var(--messenger-muted);
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
    }

    .mailbox-app .messenger-icon-btn:hover,
    .mailbox-app .messenger-icon-btn:focus {
      background: color-mix(in srgb, var(--messenger-accent) 18%, transparent);
      color: var(--messenger-text);
      outline: none;
    }

    .mailbox-app .messenger-sidebar-search {
      margin: 1rem 0 0.95rem;
    }

    .mailbox-app .messenger-search-shell {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      min-height: 2.9rem;
      padding: 0 1rem;
      border-radius: 1rem;
      background: color-mix(in srgb, var(--messenger-chip) 92%, transparent);
      border: 1px solid transparent;
      transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
    }

    .mailbox-app .messenger-search-shell:focus-within {
      border-color: color-mix(in srgb, var(--messenger-accent) 28%, transparent);
      background: color-mix(in srgb, var(--messenger-panel) 90%, transparent);
      box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--messenger-accent) 16%, transparent);
    }

    .mailbox-app .messenger-search-shell > i {
      color: var(--messenger-muted);
      font-size: 0.9rem;
      flex: 0 0 auto;
    }

    .mailbox-app .messenger-sidebar-search .form-control {
      width: 100% !important;
      height: 2.8rem;
      padding: 0;
      border: 0;
      background: transparent;
      color: var(--messenger-text);
      box-shadow: none;
      border-radius: 0;
    }

    .mailbox-app .messenger-sidebar-search .form-control:focus {
      box-shadow: none;
    }

    .mailbox-app .messenger-sidebar-search .form-control::placeholder {
      color: var(--messenger-muted);
    }

    .mailbox-app .messenger-chip-row {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 1rem;
      overflow-x: auto;
      padding-bottom: 0.2rem;
    }

    .mailbox-app .messenger-filter-chip {
      border: 0;
      border-radius: 999px;
      min-height: 2.2rem;
      padding: 0.45rem 0.9rem;
      background: transparent;
      color: var(--messenger-muted);
      font-size: 0.88rem;
      font-weight: 700;
      white-space: nowrap;
    }

    .mailbox-app .messenger-filter-chip.active {
      background: var(--messenger-accent);
      color: #fff;
      box-shadow: 0 10px 20px color-mix(in srgb, var(--messenger-accent) 28%, transparent);
    }

    .mailbox-app .mailbox-folder-nav {
      display: none;
    }

    .mailbox-app .messenger-sidebar-summary {
      margin-top: 0.5rem;
      border-radius: 1rem;
      background: var(--messenger-panel-soft);
      border-color: var(--messenger-divider);
      color: var(--messenger-muted);
    }

    .mailbox-app .messenger-list-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.9rem;
      border-bottom-color: var(--messenger-divider);
    }

    .mailbox-app .messenger-list-header .card-title,
    .mailbox-app .messenger-detail-frame .card-title {
      color: var(--messenger-text);
      font-size: 1.08rem;
    }

    .mailbox-app .messenger-list-header-copy {
      display: inline-flex;
      align-items: center;
      gap: 0.55rem;
      color: var(--messenger-muted);
      font-size: 0.82rem;
      font-weight: 600;
    }

    .mailbox-app .messenger-toolbar,
    .mailbox-app .messenger-bulk-row {
      display: none;
    }

    .mailbox-app .messenger-toolbar {
      background: transparent;
      border-bottom-color: var(--messenger-divider);
      padding-top: 0.7rem;
      padding-bottom: 0.7rem;
    }

    .mailbox-app .messenger-bulk-row {
      background: transparent;
      border-bottom-color: var(--messenger-divider);
      padding-top: 0.4rem;
    }

    .mailbox-app .mailbox-toolbar-copy,
    .mailbox-app .mailbox-bulk-summary,
    .mailbox-app .mailbox-select-toggle,
    .mailbox-app .mailbox-read-pane,
    .mailbox-app .reply-placeholder,
    .mailbox-app .mailbox-empty-detail {
      color: var(--messenger-muted);
    }

    .mailbox-app .mailbox-messages-wrap {
      max-height: calc(100vh - 13.8rem);
      background: transparent;
      border-top-color: var(--messenger-divider);
    }

    .mailbox-app .mailbox-messages-wrap tbody {
      gap: 0.5rem;
      padding: 0.65rem;
    }

    .mailbox-app .mailbox-messages-wrap tr.message-item {
      background: transparent;
      border: 0;
      border-radius: 1rem;
      box-shadow: none;
      min-height: 90px;
      height: 90px;
      padding: 0.85rem 0.9rem;
      position: relative;
    }

    .mailbox-app .mailbox-messages-wrap tr.message-item .mailbox-name {
      margin-bottom: 0.22rem;
    }

    .mailbox-app .mailbox-messages-wrap tr.message-item:hover {
      background: var(--messenger-panel-soft);
      transform: none;
    }

    .mailbox-app .mailbox-messages-wrap tr.message-item.active {
      background: var(--messenger-accent-soft);
      border: 1px solid color-mix(in srgb, var(--messenger-accent) 22%, transparent);
      box-shadow: none;
    }

    .mailbox-app .mailbox-messages-wrap tr.message-item::after {
      content: "";
      position: absolute;
      left: 3.55rem;
      right: 0.8rem;
      bottom: 0;
      height: 1px;
      background: var(--messenger-divider);
    }

    .mailbox-app .mailbox-messages-wrap tr.message-item.active::after {
      display: none;
    }

    .mailbox-app .mailbox-name {
      color: var(--messenger-text);
      font-size: 0.98rem;
      font-weight: 700;
    }

    .mailbox-app .mailbox-subject,
    .mailbox-app .mailbox-subject .mailbox-subject-line,
    .mailbox-app .mailbox-subject .mailbox-snippet,
    .mailbox-app .mailbox-date {
      color: var(--messenger-muted);
    }

    .mailbox-app .mailbox-subject .mailbox-subject-line {
      display: none !important;
    }

    .mailbox-app .mailbox-subject .mailbox-snippet {
      display: inline-block;
      max-width: 100%;
      font-size: 0.88rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .mailbox-app .message-item.unread .mailbox-name,
    .mailbox-app .message-item.unread .mailbox-subject,
    .mailbox-app .message-item.unread .mailbox-date {
      color: var(--messenger-text);
    }

    .mailbox-app .mailbox-avatar {
      width: 52px;
      height: 52px;
      border: 0;
      box-shadow: 0 8px 22px rgba(0, 0, 0, 0.3);
    }

    .mailbox-app .mailbox-avatar-cell {
      position: relative;
      width: 60px;
    }

    .mailbox-app .mailbox-avatar-wrap {
      position: relative;
      display: inline-flex;
    }

    .mailbox-app .mailbox-presence-dot,
    .mailbox-app .chat-thread-status-dot {
      content: "";
      position: absolute;
      right: 0.2rem;
      bottom: 0.1rem;
      width: 0.9rem;
      height: 0.9rem;
      border-radius: 999px;
      background: var(--messenger-success);
      border: 2px solid var(--messenger-panel);
    }

    .mailbox-app .mailbox-presence-idle,
    .mailbox-app .chat-thread-status-dot.is-idle {
      background: #f59e0b;
    }

    .mailbox-app .mailbox-presence-offline,
    .mailbox-app .chat-thread-status-dot.is-offline {
      background: #94a3b8;
    }

    .mailbox-app .mailbox-star {
      display: none !important;
    }

    .mailbox-app .mailbox-select-cell {
      display: none !important;
    }

    .mailbox-app .mailbox-empty,
    .mailbox-app .mailbox-empty-detail {
      color: #b6bcc5;
    }

    .mailbox-app .mailbox-read-pane {
      background: linear-gradient(180deg, var(--messenger-panel) 0%, var(--messenger-panel-strong) 100%);
    }

    .mailbox-app .mailbox-detail-header {
      padding: 0.95rem 1.15rem;
    }

    .mailbox-app .messenger-detail-frame {
      border-bottom-color: var(--messenger-divider);
    }

    .mailbox-app .messenger-detail-actions {
      margin-left: auto;
    }

    .mailbox-app .chat-shell {
      gap: 0.75rem;
    }

    .mailbox-app .mailbox-detail-actions {
      display: none;
    }

    .mailbox-app .chat-thread-header {
      padding: 0;
      border: 0;
      background: transparent;
      box-shadow: none;
    }

    .mailbox-app .chat-thread-kicker,
    .mailbox-app .chat-thread-participants {
      display: none;
    }

    .mailbox-app .chat-thread-hero {
      align-items: center;
      padding: 0 0 0.4rem;
    }

    .mailbox-app .chat-thread-primary {
      display: flex;
      align-items: center;
      gap: 0.9rem;
      min-width: 0;
    }

    .mailbox-app .chat-thread-avatar-wrap {
      position: relative;
      flex: 0 0 auto;
    }

    .mailbox-app .chat-thread-avatar {
      width: 52px;
      height: 52px;
      border-radius: 999px;
      object-fit: cover;
      box-shadow: 0 10px 26px rgba(15, 23, 42, 0.18);
      border: 2px solid color-mix(in srgb, var(--messenger-panel) 86%, transparent);
    }

    .mailbox-app .chat-thread-status-dot {
      position: absolute;
      right: 0.1rem;
      bottom: 0.1rem;
      width: 0.9rem;
      height: 0.9rem;
      border-radius: 999px;
      background: var(--messenger-success);
      border: 2px solid var(--messenger-panel);
    }

    .mailbox-app .chat-thread-status-dot.is-idle {
      background: #f59e0b;
    }

    .mailbox-app .chat-thread-status-dot.is-offline {
      background: #94a3b8;
    }

    .mailbox-app .mailbox-read-subject {
      font-size: 1.28rem;
      color: var(--messenger-text);
    }

    .mailbox-app .chat-thread-presence {
      margin-top: 0.1rem;
      font-size: 0.84rem;
      font-weight: 600;
      color: var(--messenger-muted);
    }

    .mailbox-app .chat-thread-meta-line {
      margin-top: 0.15rem;
      display: flex;
      flex-wrap: wrap;
      gap: 0.35rem 0.55rem;
      font-size: 0.82rem;
      color: var(--messenger-muted);
    }

    .mailbox-app .chat-thread-meta-line span {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.2rem 0.55rem;
      border-radius: 999px;
      background: var(--messenger-chip);
    }

    .mailbox-app .chat-thread-search {
      max-width: 240px;
    }

    .mailbox-app .chat-thread-search .form-control {
      background: var(--messenger-chip);
      border: 0;
      color: var(--messenger-text);
    }

    .mailbox-app .chat-thread-search-status {
      color: var(--messenger-muted);
      font-size: 0.74rem;
    }

    .mailbox-app .chat-status-row {
      display: none;
    }

    .mailbox-app #conversationWrapper {
      margin-top: 0 !important;
      border-radius: 1.1rem;
      background: color-mix(in srgb, var(--messenger-panel-strong) 88%, transparent);
      border: 1px solid var(--messenger-divider);
      padding: 1rem 1rem 0.25rem;
    }

    .mailbox-app .conversation-scroll {
      max-height: calc(100vh - 21rem);
      padding-right: 0.35rem;
    }

    .mailbox-app .reply {
      max-width: min(78%, 720px);
      margin-bottom: 0.85rem;
      border-radius: 1.1rem;
      box-shadow: none;
    }

    .mailbox-app .reply.theirs {
      background: var(--messenger-bubble);
      border: 0;
      color: var(--messenger-text);
    }

    .mailbox-app .reply.mine {
      background: var(--messenger-bubble-mine);
      border: 0;
      color: #fff;
    }

    .mailbox-app .reply.mine .reply-meta,
    .mailbox-app .reply.mine .chat-reaction-chip,
    .mailbox-app .reply.mine .chat-reaction-add {
      color: rgba(255, 255, 255, 0.88);
    }

    .mailbox-app .reply-meta {
      font-size: 0.74rem;
    }

    .mailbox-app .reply-meta strong {
      font-size: 0.82rem;
    }

    .mailbox-app .reply.mine .reply-avatar,
    .mailbox-app .reply.mine .reply-meta strong {
      display: none;
    }

    .mailbox-app .reply.mine .reply-head {
      justify-content: flex-end;
      margin-bottom: 0.25rem;
    }

    .mailbox-app .reply.mine .reply-meta {
      justify-content: flex-end;
      text-align: right;
    }

    .mailbox-app .reply.theirs .reply-head {
      margin-bottom: 0.45rem;
    }

    .mailbox-app .chat-bubble-body {
      font-size: 0.97rem;
      line-height: 1.55;
    }

    .mailbox-app .attachments .font-weight-bold.small.mb-2 {
      display: none;
    }

    .mailbox-app .attachment-thumb {
      background: transparent;
      border-color: transparent;
      color: var(--messenger-text);
      border-radius: 0.45rem;
    }

    .mailbox-app .chat-composer-shell {
      margin-top: 0.8rem !important;
      border-radius: 1rem;
      background: color-mix(in srgb, var(--messenger-panel-strong) 92%, transparent);
      border-color: var(--messenger-divider);
      box-shadow: none;
    }

    .mailbox-app .reply-form-header {
      display: none !important;
      margin-bottom: 0;
    }

    .mailbox-app .reply-form-header h6,
    .mailbox-app .reply-form-caption {
      display: none;
    }

    .mailbox-app .chat-reply-textarea {
      min-height: 46px;
      max-height: 120px;
      border: 0;
      background: var(--messenger-chip);
      color: var(--messenger-text);
      padding: 0.78rem 0.95rem;
      box-shadow: none;
    }

    .mailbox-app .chat-reply-textarea::placeholder {
      color: var(--messenger-muted);
    }

    .mailbox-app .reply-actions {
      margin-top: 0.65rem;
      gap: 0.65rem;
    }

    .mailbox-app .reply-actions .btn {
      border-radius: 999px;
    }

    .mailbox-app .select2-results__option .compose-recipient-name,
    .compose-modal .select2-results__option .compose-recipient-name {
      max-width: 240px;
    }

    .mailbox-app .select2-results__option .compose-recipient-meta,
    .compose-modal .select2-results__option .compose-recipient-meta {
      max-width: 240px;
    }

    @media (max-width: 991.98px) {
      .mailbox-app .compose-modal .modal-dialog {
        max-width: calc(100vw - 1.2rem);
      }
    }

    @media (max-height: 820px) {
      .compose-modal .modal-dialog {
        margin-top: 0.65rem;
        margin-bottom: 0.65rem;
        min-height: calc(100vh - 1.3rem);
        max-height: calc(100vh - 1.3rem);
      }
    }

    .mailbox-app .file-preview {
      display: flex;
      flex-direction: row;
      flex-wrap: nowrap;
      gap: 0.5rem;
      margin-top: 0.75rem;
    }

    .mailbox-app .chat-mentions-shell .file-preview {
      position: absolute;
      left: 0.55rem;
      bottom: 0.45rem;
      z-index: 3;
      flex-direction: row;
      align-items: flex-start;
      flex-wrap: nowrap;
      max-height: 50px;
      max-width: min(260px, calc(100% - 4.2rem));
      margin: 0;
      overflow: visible;
      padding: 0.1rem;
    }

    .mailbox-app .chat-mentions-shell .file-preview:empty {
      display: none;
    }

    .mailbox-app .file-card {
      position: relative;
      width: 42px;
      height: 42px;
      border-radius: 0.6rem;
      border: 1px solid rgba(255, 255, 255, 0.12);
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
      background: color-mix(in srgb, var(--mailbox-surface-muted) 92%, transparent);
    }

    .mailbox-app .chat-mentions-shell .file-card {
      width: 46px;
      height: 46px;
      justify-content: center;
      padding: 0;
      overflow: visible;
      background: color-mix(in srgb, var(--mailbox-surface) 96%, transparent);
      border-color: var(--mailbox-border);
      box-shadow: 0 8px 18px rgba(15, 23, 42, 0.12);
      color: var(--mailbox-text);
      flex: 0 0 46px;
      transition: transform 0.16s ease;
    }

    .mailbox-app .file-card img,
    .mailbox-app .file-card video {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .mailbox-app .file-card audio {
      width: 100%;
      align-self: center;
    }

    .mailbox-app .chat-mentions-shell .file-card img,
    .mailbox-app .chat-mentions-shell .file-card video,
    .mailbox-app .chat-mentions-shell .file-card .file-card-icon {
      width: 100%;
      height: 100%;
      border-radius: 0.45rem;
      flex: 0 0 100%;
      object-fit: cover;
    }

    .mailbox-app .chat-mentions-shell .file-card .file-card-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: color-mix(in srgb, var(--mailbox-surface-muted) 90%, transparent);
      color: var(--mailbox-text-muted);
    }

    .mailbox-app .file-card-name {
      position: absolute;
      left: 0.2rem;
      right: 0.2rem;
      bottom: 0.16rem;
      padding: 0.06rem 0.18rem;
      border-radius: 0.3rem;
      background: rgba(15, 23, 42, 0.72);
      color: #fff;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      font-size: 0.56rem;
      line-height: 1.1;
      font-weight: 700;
      text-align: center;
      pointer-events: none;
    }

    .mailbox-app .chat-mentions-shell .file-preview.is-stacked {
      width: 126px;
      max-width: 126px;
    }

    .mailbox-app .chat-mentions-shell .file-preview.is-stacked .file-card {
      margin-left: -27px;
    }

    .mailbox-app .chat-mentions-shell .file-preview.is-stacked .file-card:first-child {
      margin-left: 0;
    }

    .mailbox-app .chat-mentions-shell .file-preview.is-stacked .file-card:hover,
    .mailbox-app .chat-mentions-shell .file-preview.is-stacked .file-card:focus-within {
      transform: translateY(-4px);
      z-index: 12 !important;
    }

    .mailbox-app .file-card-count {
      width: 46px;
      height: 46px;
      border-radius: 0.6rem;
      margin-left: -27px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: color-mix(in srgb, var(--messenger-accent, #007bff) 82%, #111827 18%);
      color: #fff;
      font-size: 0.72rem;
      font-weight: 800;
      box-shadow: 0 8px 18px rgba(15, 23, 42, 0.16);
      z-index: 1;
    }

    .mailbox-app .file-card-remove {
      position: absolute;
      top: -0.35rem;
      right: -0.35rem;
      width: 1.25rem;
      height: 1.25rem;
      border: 0;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0;
      background: #dc3545;
      color: #fff;
      font-size: 0.95rem;
      line-height: 1;
      box-shadow: 0 4px 10px rgba(15, 23, 42, 0.2);
    }

    .mailbox-app .file-card-remove:hover,
    .mailbox-app .file-card-remove:focus {
      background: #bd2130;
      color: #fff;
      outline: none;
    }

    .mailbox-app .file-card.more {
      font-size: 0.85rem;
      font-weight: 700;
      background: linear-gradient(135deg, #2563eb, #0f766e);
      color: #fff;
    }

    .mailbox-app .reply-placeholder {
      min-height: 52vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      color: var(--mailbox-text-muted);
    }

    .mailbox-app .mobile-only {
      display: none;
    }

    .mailbox-app .mailbox-search .form-control {
      border-right: 0;
    }

    .mailbox-app .mailbox-search .input-group-append .btn {
      border-left: 0;
    }

    @media (max-width: 991.98px) {
      .mailbox-app .container-fluid {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
      }

      .mailbox-app .mailbox-sidebar-card,
      .mailbox-app .mailbox-pane-card {
        min-height: auto;
      }

      .mailbox-app .mailbox-messages-wrap {
        max-height: none;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
      }

      .mailbox-app .mailbox-name,
      .mailbox-app .mailbox-date {
        width: auto;
      }

      .mailbox-app .reply {
        max-width: 100%;
      }

      .mailbox-app .chat-thread-hero {
        flex-direction: column;
      }

      .mailbox-app .chat-thread-search {
        width: 100%;
        max-width: none;
      }

      .mailbox-app .card-header .card-tools {
        width: 100%;
        margin-top: 0.75rem;
      }

      .mailbox-app .mailbox-search .input-group {
        width: 100% !important;
      }

      .mailbox-app .mailbox-controls .btn-group {
        flex-wrap: wrap;
        row-gap: 0.4rem;
      }
    }

    @media (max-width: 1199.98px) {
      .mailbox-app .mailbox-messages-wrap {
        overflow-x: hidden;
      }

      .mailbox-app .mailbox-messages-wrap .table-responsive {
        min-width: 0 !important;
        max-width: 100%;
        overflow-x: hidden;
      }

      .mailbox-app .mailbox-messages-wrap table {
        min-width: 0 !important;
        width: 100%;
        max-width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        table-layout: fixed;
      }

      .mailbox-app .mailbox-messages-wrap tbody {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        padding: 0.75rem;
      }

      .mailbox-app .mailbox-messages-wrap tr.message-item {
        display: grid;
        grid-template-columns: 24px 18px 40px minmax(0, 1fr) auto;
        grid-template-areas:
          "select star avatar name date"
          "select star avatar subject subject";
        gap: 0.16rem 0.7rem;
        align-items: center;
        padding: 0.82rem 0.9rem;
        min-height: 80px;
        height: 80px;
        border: 1px solid var(--mailbox-border);
        border-radius: 0.9rem;
        background: color-mix(in srgb, var(--mailbox-surface) 88%, transparent);
        overflow: hidden;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
      }

      .mailbox-app .mailbox-messages-wrap tr.message-item td {
        display: block;
        padding: 0 !important;
        border-top: 0 !important;
        background: transparent !important;
        min-width: 0;
      }

      .mailbox-app .mailbox-messages-wrap tr.message-item .mailbox-select-cell {
        grid-area: select;
        width: auto !important;
        text-align: center !important;
      }

      .mailbox-app .mailbox-messages-wrap tr.message-item .mailbox-star {
        grid-area: star;
        width: auto !important;
        text-align: center !important;
      }

      .mailbox-app .mailbox-messages-wrap tr.message-item .mailbox-avatar-cell {
        grid-area: avatar;
        width: auto;
      }

      .mailbox-app .mailbox-messages-wrap tr.message-item .mailbox-name {
        grid-area: name;
        width: auto;
        max-width: none;
        min-width: 0;
        font-size: 0.94rem;
        font-weight: 600;
      }

      .mailbox-app .mailbox-messages-wrap tr.message-item .mailbox-subject {
        grid-area: subject;
        width: auto;
        max-width: none;
        min-width: 0;
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 0.84rem;
      }

      .mailbox-app .mailbox-messages-wrap tr.message-item .mailbox-subject .mailbox-subject-line,
      .mailbox-app .mailbox-messages-wrap tr.message-item .mailbox-subject .mailbox-snippet {
        display: inline;
        white-space: nowrap;
      }

      .mailbox-app .mailbox-messages-wrap tr.message-item .mailbox-date {
        grid-area: date;
        width: auto;
        font-size: 0.74rem;
        text-align: right;
        color: var(--mailbox-text-muted);
        padding-left: 0.5rem;
      }

      .mailbox-app .mailbox-messages-wrap tr.message-item .mailbox-attachment {
        display: none;
      }

      .mailbox-app .mailbox-messages-wrap tr.message-item.active {
        background: var(--mailbox-surface);
        border-color: rgba(13, 110, 253, 0.32);
        box-shadow: 0 14px 28px rgba(13, 110, 253, 0.1);
        transform: translateY(-1px);
      }
    }

    @media (max-width: 767.98px) {
      .mailbox-app.mobile-thread-list #mailboxDetailColumn,
      .mailbox-app.mobile-thread-detail #mailboxSidebarColumn,
      .mailbox-app.mobile-thread-detail #mailboxListColumn {
        display: none;
      }

      .mailbox-app.mobile-thread-detail #mailboxDetailColumn {
        display: block;
      }

      .mailbox-app .content-header {
        padding-top: 0.35rem;
      }

      .mailbox-app .content-header .row,
      .mailbox-app .mailbox-controls .d-flex {
        row-gap: 0.5rem;
      }

      .mailbox-app .content-header h1,
      .mailbox-app .card-title {
        font-size: 1.1rem;
      }

      .mailbox-app .card-header {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
      }

      .mailbox-app .mailbox-detail-header {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
      }

      .mailbox-app .mobile-only {
        display: inline-flex;
      }

      .mailbox-app .mailbox-avatar {
        width: 30px;
        height: 30px;
      }

      .mailbox-app .mailbox-avatar-cell {
        width: 42px;
      }

      .mailbox-app .mailbox-messages-wrap tbody {
        padding: 0.6rem;
      }

      .mailbox-app .mailbox-messages-wrap tr.message-item {
        grid-template-columns: 24px 18px 34px minmax(0, 1fr) auto;
        padding: 0.75rem 0.8rem;
        min-height: 82px;
        height: 82px;
        gap: 0.18rem 0.55rem;
      }

      .mailbox-app .mailbox-messages-wrap tr.message-item .mailbox-name {
        font-size: 0.9rem;
      }

      .mailbox-app .mailbox-messages-wrap tr.message-item .mailbox-subject {
        font-size: 0.82rem;
      }

      .mailbox-app .mailbox-messages-wrap tr.message-item .mailbox-date {
        font-size: 0.71rem;
      }

      .mailbox-app .mailbox-read-pane {
        padding: 1rem;
      }

      .mailbox-app .mailbox-sidebar-card .box-profile {
        padding: 1rem;
      }

      .mailbox-app .reply {
        padding: 0.8rem 0.85rem;
      }

      .mailbox-app .chat-bubble-body {
        font-size: 0.92rem;
      }

      .mailbox-app .reply-head,
      .mailbox-app .reply-meta {
        gap: 0.4rem;
      }

      .mailbox-app .reply-head {
        align-items: flex-start;
      }

      .mailbox-app .reply-meta {
        flex: 1 1 0;
        min-width: 0;
      }

      .mailbox-app .reply-tools {
        display: flex;
      }

      .mailbox-app .reply-menu-trigger,
      .mailbox-app .reply-quote-trigger {
        width: 32px;
        height: 32px;
      }

      .mailbox-app .reply-menu-dropdown {
        top: calc(100% + 0.35rem);
        right: 0;
        min-width: 150px;
        max-width: min(190px, calc(100vw - 3rem));
        padding: 0.4rem;
      }

      .mailbox-app .reply-menu-item {
        padding: 0.55rem 0.7rem;
        font-size: 0.9rem;
      }

      .mailbox-app .reply[data-can-edit="1"],
      .mailbox-app .reply[data-can-delete="1"] {
        -webkit-touch-callout: none;
      }

      .mailbox-app .reply-actions {
        flex-direction: column;
        align-items: stretch;
      }

      .mailbox-app .chat-reaction-picker {
        overflow-x: auto;
        padding-bottom: 0.1rem;
      }

      .mailbox-app .mailbox-read-meta {
        grid-template-columns: 1fr;
      }

      .mailbox-app .reply-actions > div,
      .mailbox-app .reply-actions > button,
      .mailbox-app .reply-actions .btn {
        width: 100%;
      }

      .mailbox-app .attachments-list {
        gap: 0.5rem;
      }

      .mailbox-app .attachment-thumb {
        width: auto;
        max-width: min(280px, 100%);
      }
    }

    @media (max-width: 575.98px) {
      .mailbox-app .content-wrapper > .content {
        padding-top: 0;
      }

      .mailbox-app .container-fluid {
        padding-left: 0.65rem;
        padding-right: 0.65rem;
      }

      .mailbox-app .breadcrumb {
        float: none !important;
        justify-content: flex-start;
        margin-top: 0.5rem;
      }

      .mailbox-app .mailbox-controls {
        padding-left: 0.75rem !important;
        padding-right: 0.75rem !important;
      }

      .mailbox-app .mailbox-messages-wrap tr.message-item {
        grid-template-columns: 16px 30px minmax(0, 1fr) auto;
        padding: 0.72rem;
        min-height: 78px;
        height: 78px;
      }

      .mailbox-app .mailbox-messages-wrap tr.message-item .mailbox-subject .mailbox-snippet {
        display: none;
      }

      .mailbox-app .reply-form-shell {
        padding: 0.85rem;
      }

      .mailbox-app .reply-head {
        display: grid;
        grid-template-columns: 30px minmax(0, 1fr) auto;
        align-items: start;
        column-gap: 0.55rem;
      }

      .mailbox-app .reply.mine .reply-head {
        grid-template-columns: auto minmax(0, 1fr) 30px;
      }

      .mailbox-app .reply.mine .reply-avatar {
        order: 3;
      }

      .mailbox-app .reply.mine .reply-meta {
        text-align: right;
      }

      .mailbox-app .reply-menu-dropdown {
        width: min(180px, calc(100vw - 2.5rem));
      }

      .mailbox-app .conversation-scroll {
        max-height: 260px;
      }

      .mailbox-app .chat-thread-search-status {
        text-align: left;
      }

      .mailbox-app .attachment-thumb {
        width: auto;
        max-width: 100%;
      }
    }

    html.messenger-page-root,
    body.messenger-page {
      height: 100%;
      overflow: hidden;
      background: var(--messenger-bg);
    }

    .messenger-page .wrapper.mailbox-app {
      --messenger-shell-height: 100vh;
      --messenger-top-offset: 0px;
      --messenger-footer-offset: 0px;
      margin-top: var(--messenger-top-offset);
      height: var(--messenger-shell-height);
      min-height: var(--messenger-shell-height);
      overflow: hidden;
    }

    .messenger-page .wrapper.mailbox-app > .content-wrapper {
      height: 100%;
      min-height: 100%;
      overflow: hidden;
    }

    .mailbox-app .messenger-content,
    .mailbox-app .messenger-container,
    .mailbox-app .messenger-layout,
    .mailbox-app .messenger-conversations-column,
    .mailbox-app .messenger-thread-column,
    .mailbox-app .messenger-conversations-card,
    .mailbox-app .messenger-thread-card {
      min-height: 0;
    }

    .mailbox-app .messenger-conversations-column,
    .mailbox-app .messenger-thread-column {
      height: 100%;
      overflow: hidden;
    }

    .mailbox-app .messenger-content,
    .mailbox-app .messenger-container {
      height: 100%;
    }

    .mailbox-app .messenger-content {
      flex: 1 1 auto;
      min-height: 0;
      padding-top: 0.15rem;
      padding-bottom: 0;
    }

    .mailbox-app .messenger-container {
      padding-top: 1rem;
      padding-bottom: 1rem;
    }

    .mailbox-app .messenger-layout {
      display: grid;
      grid-template-columns: minmax(320px, 348px) minmax(0, 1fr);
      gap: 1rem;
      height: 100%;
      overflow: hidden;
    }

    .mailbox-app .messenger-conversations-card,
    .mailbox-app .messenger-thread-card {
      height: 100%;
      margin-bottom: 0;
      overflow: hidden;
    }

    .mailbox-app .messenger-conversations-card > .card-body,
    .mailbox-app .messenger-thread-card > .card-body {
      height: 100%;
      min-height: 0;
    }

    .mailbox-app .messenger-conversations-card > .card-body {
      display: flex;
      flex-direction: column;
    }

    .mailbox-app .messenger-conversations-head {
      flex: 0 0 auto;
      padding: 1.15rem 1rem 0.9rem;
      border-bottom: 1px solid var(--messenger-divider);
      background: linear-gradient(180deg, color-mix(in srgb, var(--messenger-panel) 94%, transparent) 0%, color-mix(in srgb, var(--messenger-panel-strong) 98%, transparent) 100%);
    }

    .mailbox-app .messenger-folder-row {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 0.55rem;
      margin-bottom: 0.9rem;
      overflow: visible;
      padding-bottom: 0.15rem;
    }

    .mailbox-app .messenger-compose-dropdown {
      margin-left: auto;
      position: relative;
      z-index: 1040;
    }

    .mailbox-app .messenger-folder-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      min-height: 2.35rem;
      padding: 0.5rem 0.85rem;
      border-radius: 999px;
      border: 1px solid var(--messenger-divider);
      background: var(--messenger-panel-soft);
      color: var(--messenger-text);
      font-size: 0.84rem;
      font-weight: 700;
      white-space: nowrap;
    }

    .mailbox-app .messenger-folder-pill:hover,
    .mailbox-app .messenger-folder-pill:focus {
      color: var(--messenger-text);
      text-decoration: none;
      background: color-mix(in srgb, var(--messenger-accent) 10%, transparent);
      outline: none;
    }

    .mailbox-app .messenger-folder-pill.active {
      background: color-mix(in srgb, var(--messenger-accent) 16%, transparent);
      border-color: color-mix(in srgb, var(--messenger-accent) 28%, transparent);
      color: var(--messenger-text);
    }

    .mailbox-app .messenger-folder-pill--action {
      border: 0;
      background: linear-gradient(135deg, color-mix(in srgb, var(--messenger-accent) 86%, #0f766e 14%), color-mix(in srgb, var(--messenger-accent) 72%, #1d4ed8 28%));
      color: #fff;
      box-shadow: 0 10px 24px color-mix(in srgb, var(--messenger-accent) 28%, transparent);
    }

    .mailbox-app .messenger-folder-pill--action:hover,
    .mailbox-app .messenger-folder-pill--action:focus {
      color: #fff;
      background: linear-gradient(135deg, color-mix(in srgb, var(--messenger-accent) 90%, #0f766e 10%), color-mix(in srgb, var(--messenger-accent) 78%, #1d4ed8 22%));
    }

    .mailbox-app .messenger-compose-icon {
      width: 2.35rem;
      min-width: 2.35rem;
      padding-inline: 0;
      justify-content: center;
    }

    .mailbox-app .messenger-compose-menu {
      min-width: 12rem;
      max-height: none;
      overflow: visible;
      padding: 0.35rem;
      right: 0;
      left: auto;
      top: calc(100% + 0.4rem) !important;
    }

    .mailbox-app .messenger-compose-menu .dropdown-item {
      border-radius: 0.42rem;
      min-height: 2.15rem;
      padding: 0.5rem 0.65rem;
      white-space: nowrap;
    }

    .mailbox-app .messenger-sidebar-summary {
      padding: 0.9rem 1rem;
      margin-bottom: 0.9rem;
    }

    .mailbox-app .messenger-list-header {
      padding: 0;
      border: 0;
      margin-bottom: 0.15rem;
    }

    .mailbox-app .messenger-conversation-list {
      flex: 1 1 auto;
      min-height: 0;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    .mailbox-app .mailbox-messages-wrap {
      flex: 1 1 auto;
      min-height: 0;
      max-height: none;
      overflow-y: auto;
      overflow-x: hidden;
      scrollbar-gutter: stable;
      padding: 0.5rem 0.45rem 0.55rem;
    }

    .mailbox-app .mailbox-messages-wrap .table-responsive {
      overflow: visible;
    }

    .mailbox-app .mailbox-messages-wrap table {
      margin: 0;
      table-layout: fixed;
      width: 100%;
    }

    .mailbox-app .mailbox-messages-wrap tbody {
      display: flex;
      flex-direction: column;
      gap: 0.4rem;
      padding: 0;
    }

    .mailbox-app .mailbox-messages-wrap tr.message-item {
      display: grid;
      grid-template-columns: 56px minmax(0, 1fr) auto;
      grid-template-areas:
        "avatar name date"
        "avatar subject subject";
      gap: 0.12rem 0.8rem;
      align-items: center;
      min-height: 78px;
      height: auto;
      padding: 0.85rem 0.9rem;
      border-radius: 1rem;
    }

    .mailbox-app .mailbox-messages-wrap tr.message-item td {
      display: block;
      padding: 0 !important;
      border-top: 0 !important;
      background: transparent !important;
      min-width: 0;
    }

    .mailbox-app .mailbox-messages-wrap tr.message-item .mailbox-avatar-cell {
      grid-area: avatar;
      align-self: start;
    }

    .mailbox-app .mailbox-messages-wrap tr.message-item .mailbox-name {
      grid-area: name;
      width: auto;
      min-width: 0;
      margin: 0;
    }

    .mailbox-app .mailbox-messages-wrap tr.message-item .mailbox-subject {
      grid-area: subject;
      min-width: 0;
      width: auto;
    }

    .mailbox-app .mailbox-messages-wrap tr.message-item .mailbox-date {
      grid-area: date;
      width: auto;
      text-align: right;
      align-self: start;
      padding-left: 0.5rem;
      font-size: 0.76rem;
    }

    .mailbox-app .mailbox-messages-wrap tr.message-item .mailbox-attachment {
      display: none;
    }

    .mailbox-app .mailbox-messages-wrap tr.message-item::after {
      display: none;
    }

    .mailbox-app .messenger-thread-card {
      display: flex;
      flex-direction: column;
    }

    .mailbox-app .messenger-thread-card .mailbox-detail-header {
      display: none;
      flex: 0 0 auto;
      padding: 1rem 1.15rem;
      border-bottom: 1px solid var(--messenger-divider);
      background: linear-gradient(180deg, color-mix(in srgb, var(--messenger-panel) 96%, transparent) 0%, color-mix(in srgb, var(--messenger-panel-strong) 98%, transparent) 100%);
    }

    .mailbox-app .messenger-thread-card #messageDetail {
      flex: 1 1 auto;
      height: 100%;
      min-height: 0;
      padding: 0;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    .mailbox-app .reply-placeholder,
    .mailbox-app .mailbox-empty-detail {
      flex: 1 1 auto;
      min-height: 0;
      margin: 0;
      padding: 2rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
    }

    .mailbox-app #messageDetail > .chat-shell {
      flex: 1 1 auto;
      height: 100%;
      min-height: 0;
      display: flex;
      flex-direction: column;
      gap: 1rem;
      padding: 1rem 1.15rem 1.1rem;
    }

    .mailbox-app .chat-thread-header {
      flex: 0 0 auto;
      padding-bottom: 0;
      margin-bottom: 0;
    }

    body.kodus-chat-bubble-mode .mailbox-app #messageDetail > .chat-shell {
      gap: 0.45rem;
      padding: 0.55rem 0.75rem 0.75rem;
    }

    body.kodus-chat-bubble-mode .mailbox-app .chat-thread-header {
      line-height: 1.2;
    }

    body.kodus-chat-bubble-mode .mailbox-app .messenger-thread-card {
      position: relative;
    }

    body.kodus-chat-bubble-mode .mailbox-app .messenger-thread-card .mailbox-detail-header {
      position: absolute;
      left: 0.65rem;
      top: 0.48rem;
      z-index: 40;
      display: flex;
      align-items: center;
      padding: 0;
      min-height: 0;
      border: 0;
      background: transparent;
    }

    body.kodus-chat-bubble-mode .mailbox-app #mobileBackToList {
      display: inline-flex !important;
      align-items: center;
      flex: 0 0 auto;
      padding: 0.22rem 0.45rem;
      font-size: 0.78rem;
      line-height: 1.15;
    }

    body.kodus-chat-bubble-mode .mailbox-app .chat-thread-hero {
      align-items: center;
      gap: 0.5rem;
      padding: 0 0 0 3.45rem;
      flex-wrap: nowrap;
      flex-direction: row;
    }

    body.kodus-chat-bubble-mode .mailbox-app .chat-thread-primary {
      gap: 0.55rem;
      flex: 1 1 auto;
      min-width: 0;
    }

    body.kodus-chat-bubble-mode .mailbox-app .chat-thread-primary > div:last-child {
      min-width: 0;
    }

    body.kodus-chat-bubble-mode .mailbox-app .chat-thread-avatar {
      width: 34px;
      height: 34px;
    }

    body.kodus-chat-bubble-mode .mailbox-app .chat-thread-status-dot {
      width: 0.65rem;
      height: 0.65rem;
      border-width: 1px;
    }

    body.kodus-chat-bubble-mode .mailbox-app .mailbox-read-subject {
      margin: 0;
      font-size: 0.98rem;
      line-height: 1.15;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    body.kodus-chat-bubble-mode .mailbox-app .chat-thread-presence {
      margin-top: 0;
      font-size: 0.75rem;
      line-height: 1.15;
    }

    body.kodus-chat-bubble-mode .mailbox-app .chat-thread-meta-line {
      display: none;
    }

    body.kodus-chat-bubble-mode .mailbox-app .chat-thread-options {
      flex: 0 0 auto;
    }

    body.kodus-chat-bubble-mode .mailbox-app .chat-thread-options-btn {
      width: 30px;
      height: 30px;
    }

    .mailbox-app .chat-thread-hero {
      align-items: flex-start;
      gap: 1rem;
    }

    .mailbox-app .chat-thread-search {
      display: none !important;
    }

    .mailbox-app #conversationWrapper {
      flex: 1 1 auto;
      height: 100%;
      min-height: 0;
      margin: 0 !important;
      padding: 0;
      border: 0;
      background: transparent;
    }

    .mailbox-app .conversation-scroll {
      height: 100%;
      max-height: none;
      min-height: 0;
      overflow-y: auto;
      overflow-x: hidden;
      scrollbar-gutter: stable;
      padding: 0 3.5rem 0.85rem 3.5rem;
      scroll-behavior: auto;
    }

    body.kodus-chat-bubble-mode .mailbox-app .conversation-scroll {
      padding: 0 0.55rem 0.35rem;
    }

    .mailbox-app .reply {
      width: fit-content;
      max-width: min(68%, 680px);
      padding: 0.8rem 0.95rem;
      margin-bottom: 1.35rem;
      border-radius: 1.25rem;
      box-shadow: 0 8px 22px color-mix(in srgb, var(--messenger-text) 8%, transparent);
      position: relative;
      overflow: visible;
    }

    .mailbox-app .reply.mine {
      margin-left: auto;
      border-bottom-right-radius: 0.4rem;
    }

    .mailbox-app .reply.theirs {
      margin-right: auto;
      border-bottom-left-radius: 0.4rem;
    }

    .mailbox-app .reply-meta {
      margin-bottom: 0.3rem;
    }

    .mailbox-app .chat-bubble-body {
      word-break: break-word;
    }

    .mailbox-app .reply .chat-reactions {
      position: absolute;
      top: 50%;
      bottom: auto;
      margin-top: 0;
      z-index: 5;
      width: max-content;
      max-width: 12rem;
    }

    .mailbox-app .reply.mine .chat-reactions {
      left: -0.55rem;
      right: auto;
      transform: translate(-100%, -50%);
    }

    .mailbox-app .reply.theirs .chat-reactions {
      right: -0.55rem;
      left: auto;
      transform: translate(100%, -50%);
    }

    .mailbox-app .reply .chat-reaction-summary {
      justify-content: flex-start;
      flex-wrap: nowrap;
    }

    .mailbox-app .reply.theirs .chat-reaction-summary {
      justify-content: flex-end;
    }

    .mailbox-app .chat-composer-shell {
      flex: 0 0 auto;
      margin: 0 !important;
      padding: 0.65rem 0.8rem;
      border-radius: 1.1rem;
      position: relative;
      z-index: 2;
    }

    body.kodus-chat-bubble-mode .mailbox-app .reply-quote-composer {
      margin-bottom: 0.45rem;
      padding: 0.5rem 0.6rem;
      border-radius: 0.65rem;
    }

    .mailbox-app .chat-mentions-shell {
      position: relative;
    }

    .mailbox-app .chat-reply-textarea {
      min-height: 46px;
      max-height: 120px;
      resize: none;
      border-radius: 1rem;
      padding-right: 7.65rem;
    }

    .mailbox-app .chat-mentions-shell.has-file-preview .chat-reply-textarea {
      min-height: 104px;
      padding-bottom: 4.35rem;
    }

    .mailbox-app .chat-composer-tool-stack {
      position: absolute;
      right: 0.6rem;
      bottom: 0.52rem;
      z-index: 4;
      display: inline-flex;
      align-items: center;
      flex-direction: row;
      gap: 0.18rem;
      padding: 0.12rem;
      border-radius: 999px;
      background: color-mix(in srgb, var(--mailbox-surface) 88%, transparent);
      box-shadow: 0 4px 14px color-mix(in srgb, var(--messenger-text) 8%, transparent);
    }

    body.kodus-chat-bubble-mode .mailbox-app .chat-composer-shell {
      padding: 0.45rem 0.55rem;
      border-radius: 0.8rem;
    }

    body.kodus-chat-bubble-mode .mailbox-app .chat-reply-textarea {
      min-height: 42px;
      max-height: 92px;
      padding: 0.62rem 5.1rem 0.62rem 0.75rem;
    }

    body.kodus-chat-bubble-mode .mailbox-app .chat-mentions-shell.has-file-preview .chat-reply-textarea {
      min-height: 98px;
      max-height: 148px;
      padding-bottom: 4.25rem;
    }

    .mailbox-app .chat-reply-textarea,
    .compose-modal #composeMessage,
    .mailbox-app .composer-mention-preview,
    .compose-modal .composer-mention-preview {
      box-sizing: border-box;
      font-family: inherit;
      font-size: 0.95rem;
      line-height: 1.5;
      letter-spacing: 0;
      transform: none;
      zoom: 1;
    }

    .mailbox-app .composer-mention-preview {
      min-height: 46px;
      max-height: 120px;
      padding: 0.78rem 7.65rem 0.78rem 0.95rem;
    }

    .mailbox-app .chat-mentions-shell.has-file-preview .composer-mention-preview {
      min-height: 104px;
      padding-bottom: 4.35rem;
    }

    body.kodus-chat-bubble-mode .mailbox-app .composer-mention-preview {
      min-height: 42px;
      max-height: 92px;
      padding: 0.62rem 5.1rem 0.62rem 0.75rem;
    }

    body.kodus-chat-bubble-mode .mailbox-app .chat-mentions-shell.has-file-preview .composer-mention-preview {
      min-height: 98px;
      max-height: 148px;
      padding-bottom: 4.25rem;
    }

    .compose-modal #composeMessage,
    .compose-modal .composer-mention-preview {
      min-height: 154px;
      padding: 0.75rem 3.4rem 0.75rem 0.85rem;
    }

    .compose-modal .compose-message-shell.has-file-preview #composeMessage,
    .compose-modal .compose-message-shell.has-file-preview .composer-mention-preview {
      min-height: 224px;
      padding-bottom: 5rem;
    }

    .mailbox-app .composer-mention-chip,
    .compose-modal .composer-mention-chip {
      padding: 0;
      font-weight: inherit;
      box-shadow: none;
    }

    body.kodus-chat-bubble-mode .mailbox-app .chat-composer-tool-stack {
      right: 0.65rem;
      bottom: 0.72rem;
      flex-direction: row;
      gap: 0.15rem;
      padding: 0;
      background: transparent;
      box-shadow: none;
    }

    body.kodus-chat-bubble-mode .mailbox-app .chat-composer-tool-btn {
      width: 1.75rem;
      height: 1.75rem;
    }

    .mailbox-app .chat-composer-tool-btn {
      width: 2rem;
      height: 2rem;
      border: 0;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: transparent;
      color: var(--messenger-muted);
      transition: background-color 0.18s ease, color 0.18s ease;
    }

    .mailbox-app #attachBtn {
      order: 1;
    }

    .mailbox-app #replyEmojiTrigger {
      order: 2;
    }

    .mailbox-app .chat-composer-tool-btn:hover,
    .mailbox-app .chat-composer-tool-btn:focus {
      background: color-mix(in srgb, var(--messenger-accent) 16%, transparent);
      color: var(--messenger-accent);
      outline: none;
    }

    .mailbox-app .chat-composer-send-btn {
      order: 3;
      width: 2.15rem;
      height: 2.15rem;
      background: var(--messenger-accent, #007bff);
      color: #fff;
    }

    .mailbox-app .chat-composer-send-btn:hover,
    .mailbox-app .chat-composer-send-btn:focus {
      background: color-mix(in srgb, var(--messenger-accent, #007bff) 84%, #000 16%);
      color: #fff;
    }

    .mailbox-app .chat-composer-tool-stack .emoji-menu {
      z-index: 1075;
    }

    .mailbox-app .reply-actions {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      margin-top: 0.55rem;
      flex-wrap: wrap;
    }

    .mailbox-app .reply-actions > div {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .mailbox-app .file-preview {
      margin-top: 0.65rem;
    }

    @media (max-width: 1199.98px) {
      .mailbox-app .messenger-layout {
        grid-template-columns: minmax(300px, 332px) minmax(0, 1fr);
      }

      .mailbox-app .reply {
        max-width: min(72%, 620px);
      }
    }

    @media (max-width: 991.98px) {
      .mailbox-app .messenger-container {
        padding-inline: 0.85rem;
      }

      .mailbox-app .messenger-layout {
        grid-template-columns: 320px minmax(0, 1fr);
      }

      .mailbox-app .reply {
        max-width: 76%;
      }
    }

    @media (max-width: 767.98px) {
      html.messenger-page-root,
      body.messenger-page {
        overflow: hidden;
      }

      .messenger-page .wrapper.mailbox-app,
      .messenger-page .wrapper.mailbox-app > .content-wrapper {
        height: var(--messenger-shell-height);
        min-height: var(--messenger-shell-height);
      }

      .mailbox-app .messenger-container {
        padding: 0.65rem;
      }

      .mailbox-app .messenger-layout {
        grid-template-columns: 1fr;
      }

      .mailbox-app.mobile-thread-list #mailboxDetailColumn,
      .mailbox-app.mobile-thread-detail #mailboxSidebarColumn {
        display: none;
      }

      .mailbox-app .messenger-conversations-head {
        padding: 1rem 0.85rem 0.8rem;
      }

      .mailbox-app .messenger-thread-card .mailbox-detail-header {
        display: flex;
        padding-inline: 0.85rem;
      }

      .mailbox-app #messageDetail > .chat-shell {
        padding: 0.85rem;
      }

      .mailbox-app .chat-thread-hero {
        flex-direction: column;
      }

      .mailbox-app .reply {
        max-width: 74%;
      }

      .mailbox-app .chat-mentions-shell .file-preview {
        left: 0.45rem;
        max-width: min(210px, calc(100% - 4rem));
        max-height: 50px;
      }

      .mailbox-app .chat-mentions-shell .file-card {
        width: 46px;
        height: 46px;
      }

      .mailbox-app .mobile-only {
        display: inline-flex;
        align-items: center;
      }
    }
  </style>
</head>
<script>
  if (document.documentElement) {
    document.documentElement.classList.add('messenger-page-root');
  }
  if (document.body) {
    document.body.classList.add('messenger-page');
  }
</script>
<div class="wrapper mailbox-app">

  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Mailbox</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="<?php echo app_url('home'); ?>">Home</a></li>
              <li class="breadcrumb-item active">Mailbox</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content messenger-content">
      <div class="container-fluid messenger-container">
        <div class="messenger-layout">
          <aside class="messenger-conversations-column" id="mailboxSidebarColumn">
            <div class="card card-primary card-outline mailbox-pane-card messenger-conversations-card">
              <div class="card-body p-0">
                <div class="messenger-conversations-head">
                  <div class="messenger-sidebar-top">
                    <div>
                      <h2 class="messenger-sidebar-title">Chats</h2>
                      <p class="messenger-sidebar-subtitle">KODUS communication hub</p>
                    </div>
                  </div>

                  <div class="mailbox-search messenger-sidebar-search">
                    <label for="mailboxSearch" class="sr-only">Search chats</label>
                    <div class="messenger-search-shell">
                      <i class="fas fa-search" aria-hidden="true"></i>
                      <input type="text" class="form-control" id="mailboxSearch" placeholder="Search chats">
                    </div>
                  </div>

                  <div class="messenger-folder-row">
                    <a href="?folder=inbox" class="messenger-folder-pill <?= $currentFolder === 'inbox' ? 'active' : '' ?>">
                      <span>Chats</span>
                      <span class="badge bg-primary" id="sidebarUnreadBadge"><?= $unreadCount ?></span>
                    </a>
                    <a href="?folder=trash" class="messenger-folder-pill <?= $currentFolder === 'trash' ? 'active' : '' ?>">
                      <span>Archive</span>
                      <span class="badge bg-secondary" id="sidebarTrashBadge"><?= $trashCount ?></span>
                    </a>
                    <div class="dropdown messenger-compose-dropdown">
                      <button type="button" class="messenger-folder-pill messenger-folder-pill--action messenger-compose-icon" id="messengerComposeMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Compose" title="Compose">
                        <i class="fas fa-edit" aria-hidden="true"></i>
                        <span class="sr-only">Compose</span>
                      </button>
                      <div class="dropdown-menu dropdown-menu-right messenger-compose-menu" aria-labelledby="messengerComposeMenu">
                        <button type="button" class="dropdown-item mailbox-compose-trigger"><i class="fas fa-comment mr-2"></i>New Chat</button>
                        <button type="button" class="dropdown-item group-compose-trigger"><i class="fas fa-users mr-2"></i>New Group Chat</button>
                      </div>
                    </div>
                  </div>

                  <div class="messenger-chip-row" role="tablist" aria-label="Conversation filters">
                    <button type="button" class="btn messenger-filter-chip mailbox-filter active" data-filter="all">All</button>
                    <button type="button" class="btn messenger-filter-chip mailbox-filter" data-filter="unread">Unread</button>
                  </div>

                  <div class="text-muted small messenger-sidebar-summary">
                    <div class="d-flex justify-content-between mb-2">
                      <span><?= $currentFolder === 'trash' ? 'Archived chats' : 'Active chats' ?></span>
                      <strong id="messageCountLabel"><?= $messageCount ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                      <span>Unseen</span>
                      <strong id="unreadCountLabel"><?= $unreadCount ?></strong>
                    </div>
                  </div>

                  <div class="messenger-list-header">
                    <h3 class="card-title mb-0" id="mailboxListTitle"><?= htmlspecialchars($folderTitle) ?></h3>
                    <div class="messenger-list-header-copy">
                      <span><?= $messageCount ?> chat<?= $messageCount === 1 ? '' : 's' ?></span>
                      <span id="mailboxRefreshLabel">Synced</span>
                    </div>
                  </div>

                  <div class="mailbox-filter-group messenger-bulk-row">
                    <?php if (in_array($currentFolder, ['inbox', 'trash'], true)): ?>
                    <div class="mailbox-bulk-toolbar d-flex align-items-center justify-content-between">
                      <div class="mailbox-bulk-actions d-flex align-items-center">
                        <label class="mailbox-select-toggle" for="mailboxSelectAll">
                          <input type="checkbox" id="mailboxSelectAll">
                          <span>Select chats</span>
                        </label>
                        <select class="form-control form-control-sm mailbox-bulk-select" id="mailboxBulkAction">
                          <option value="">Chat actions</option>
                          <?php if ($currentFolder === 'trash'): ?>
                          <option value="restore">Restore</option>
                          <option value="delete_permanent">Delete forever</option>
                          <?php else: ?>
                          <option value="delete">Archive</option>
                          <?php endif; ?>
                          <option value="mark_read">Mark as seen</option>
                        </select>
                        <button type="button" class="btn btn-default btn-sm mailbox-bulk-apply" id="mailboxBulkApply" disabled>
                          <i class="fas fa-check mr-1"></i> Go
                        </button>
                      </div>
                      <div class="mailbox-bulk-summary" id="mailboxBulkSummary">0 selected</div>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="messenger-conversation-list" id="mailboxListColumn">
                  <div class="mailbox-messages-wrap" id="messageList">
                    <?php if ($messageCount > 0): ?>
                      <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                          <tbody>
                          <?php foreach ($messages as $row): ?>
                            <?php
                              $messageId = (int) $row['id'];
                              $isGroupThread = mailboxIsGroupThread($row);
                              $recipientLabel = trim((string) ($row['recipient_names'] ?? ''));
                              $senderLabel = trim((string) ($row['user_name'] ?? ''));
                              if ($senderLabel === '') {
                                  $senderLabel = trim((string) ($row['user_email'] ?? 'Unknown'));
                              }
                              $isSenderView = !empty($_SESSION['email']) && strcasecmp((string) ($row['user_email'] ?? ''), (string) $_SESSION['email']) === 0;
                              $displayLabel = $isGroupThread
                                  ? (trim((string) ($row['group_name'] ?? '')) !== '' ? (string) $row['group_name'] : 'Group chat')
                                  : ($isSenderView
                                  ? ($recipientLabel !== '' ? $recipientLabel : 'Admin')
                                  : $senderLabel);
                              $name = htmlspecialchars($displayLabel);
                              $displayPicture = $isGroupThread ? '' : ($isSenderView ? ($row['recipient_picture'] ?? '') : ($row['sender_picture'] ?? ''));
                              $displaySsoAvatar = $isSenderView ? ($row['recipient_sso_avatar_url'] ?? '') : ($row['sender_sso_avatar_url'] ?? '');
                              $avatarUrl = $isGroupThread && trim((string) ($row['group_photo'] ?? '')) !== ''
                                  ? $base_url . 'inbox/uploads/group_photos/' . rawurlencode((string) $row['group_photo'])
                                  : avatar_resolve_url($displayPicture, $displaySsoAvatar, $base_url, dirname(__DIR__));
                              $presenceUserId = $isGroupThread ? 0 : (int) ($isSenderView ? ($row['recipient_user_id'] ?? 0) : ($row['sender_user_id'] ?? 0));
                              $presence = $isGroupThread
                                  ? ['detail' => 'Group chat', 'class' => 'offline']
                                  : mailboxClassifyPresence(
                                      $isSenderView ? ($row['recipient_last_activity'] ?? null) : ($row['sender_last_activity'] ?? null),
                                      (int) ($isSenderView ? ($row['recipient_is_online'] ?? 0) : ($row['sender_is_online'] ?? 0))
                                  );
                              $subjectRaw = trim((string) ($row['subject'] ?? ''));
                              $subject = htmlspecialchars($subjectRaw !== '' ? $subjectRaw : 'Quick chat');
                              $latestPreview = $latestPreviews[$messageId] ?? [
                                  'text' => mailboxPreviewText($row['message'] ?? '', $row['attachment'] ?? ''),
                                  'is_mine' => $isSenderView,
                              ];
                              $snippet = htmlspecialchars(mailboxFormatThreadPreview($latestPreview, 54));
                              $email = htmlspecialchars($row['user_email'] ?? '');
                              $activityAt = (string) ($row['latest_activity_at'] ?? $row['sent_at'] ?? '');
                              $sentAt = htmlspecialchars($activityAt);
                              $timestamp = $activityAt !== '' ? strtotime($activityAt) : false;
                              $dateLabel = $timestamp ? date('g:i A', $timestamp) : '';
                              $hasAttachment = !empty($row['attachment']);
                              $groupMuted = $isGroupThread && !empty($row['current_member_muted_at']);
                              $groupLeft = $isGroupThread && !empty($row['current_member_left_at']);
                              $rowClass = (!$groupMuted && !$groupLeft && (int) ($row['user_read'] ?? 0) === 0) ? 'unread' : '';
                              $groupStateBadges = [];
                              if ($groupMuted) {
                                  $groupStateBadges[] = ['label' => 'Muted', 'class' => 'mailbox-chat-badge--muted'];
                              }
                              if ($groupLeft) {
                                  $groupStateBadges[] = ['label' => 'Left', 'class' => 'mailbox-chat-badge--left'];
                              }
                            ?>
                            <tr class="message-item <?= $rowClass ?>"
                                data-id="<?= $messageId ?>"
                                data-email="<?= $email ?>"
                                data-name="<?= $name ?>"
                                data-subject="<?= $subject ?>"
                                data-message="<?= htmlspecialchars($row['message'] ?? '') ?>"
                                data-sent="<?= $sentAt ?>"
                                data-unread="<?= $rowClass ? '1' : '0' ?>"
                                data-presence-user-id="<?= (int) $presenceUserId ?>"
                                data-has-attachment="<?= $hasAttachment ? '1' : '0' ?>">
                              <?php if (in_array($currentFolder, ['inbox', 'trash'], true)): ?>
                              <td class="mailbox-select-cell">
                                <label class="mailbox-row-select mb-0">
                                  <input type="checkbox" class="mailbox-message-select" value="<?= $messageId ?>" aria-label="Select conversation <?= $messageId ?>">
                                </label>
                              </td>
                              <?php endif; ?>
                              <td class="mailbox-star text-center" style="width:40px;">
                                <i class="fas fa-envelope <?= $rowClass ? 'text-warning' : 'text-muted' ?>"></i>
                              </td>
                              <td class="mailbox-avatar-cell text-center">
                                <span class="mailbox-avatar-wrap" title="<?= htmlspecialchars($presence['detail']) ?>">
                                  <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= $name ?>" class="mailbox-avatar">
                                  <span class="mailbox-presence-dot mailbox-presence-<?= htmlspecialchars($presence['class']) ?>" aria-label="<?= htmlspecialchars($presence['detail']) ?>"></span>
                                </span>
                              </td>
                              <td class="mailbox-name"><?= $name ?></td>
                              <td class="mailbox-subject">
                                <?php if ($snippet !== ''): ?><span class="mailbox-snippet"><?= $snippet ?></span><?php endif; ?>
                                <?php if ($hasAttachment): ?><span class="mailbox-chat-badge">Attachment</span><?php endif; ?>
                                <?php foreach ($groupStateBadges as $badge): ?>
                                  <span class="mailbox-chat-badge <?= htmlspecialchars($badge['class']) ?>"><?= htmlspecialchars($badge['label']) ?></span>
                                <?php endforeach; ?>
                              </td>
                              <td class="mailbox-attachment text-center" style="width:40px;">
                                <?php if ($hasAttachment): ?>
                                  <i class="fas fa-paperclip text-muted"></i>
                                <?php endif; ?>
                              </td>
                              <td class="mailbox-date" title="<?= htmlspecialchars($activityAt) ?>"><?= htmlspecialchars($dateLabel) ?></td>
                            </tr>
                          <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                      <div class="mailbox-empty" id="mailboxNoResults" style="display:none;">
                        <i class="far fa-comment-dots fa-3x mb-3"></i>
                        <p class="mb-1"><strong>No matching chats</strong></p>
                        <p class="mb-0">Try another search or filter.</p>
                      </div>
                    <?php else: ?>
                      <div class="mailbox-empty">
                        <img src="<?php echo app_url('dist/img/empty-inbox.png'); ?>" alt="No chats yet">
                        <p class="mb-1"><strong><?= $currentFolder === 'trash' ? 'Archive is empty' : 'No chats yet' ?></strong></p>
                        <p class="mb-0"><?= $currentFolder === 'trash' ? 'Chats you remove from your main list will stay here.' : 'New conversations will show up here.' ?></p>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </aside>

          <section class="messenger-thread-column" id="mailboxDetailColumn">
            <div class="card card-primary card-outline mailbox-pane-card messenger-thread-card">
              <div class="card-header mailbox-detail-header messenger-detail-frame">
                <button type="button" class="btn btn-outline-secondary btn-sm mobile-only" id="mobileBackToList">
                  <i class="fas fa-arrow-left mr-1"></i> Back
                </button>
              </div>
              <div class="card-body mailbox-read-pane" id="messageDetail">
                <?php if ($messageCount > 0): ?>
                  <div class="reply-placeholder">
                    <i class="far fa-comment-dots fa-3x mb-3"></i>
                    <p class="mb-1"><?= $currentFolder === 'trash' ? 'Open an archived chat to review it.' : 'Open a chat to see the conversation.' ?></p>
                    <p class="mb-0 small"><?= $currentFolder === 'trash' ? 'You can restore it or remove it forever.' : 'Messages, reactions, and attachments will appear here.' ?></p>
                  </div>
                <?php else: ?>
                  <div class="mailbox-empty-detail">
                    <i class="far fa-comment-dots fa-3x mb-3"></i>
                    <p class="mb-1"><strong>No chat selected</strong></p>
                    <p class="mb-0"><?= $currentFolder === 'trash' ? 'Your archive is currently empty.' : 'Your chat list is currently empty.' ?></p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade compose-modal" id="groupComposeModal" tabindex="-1" aria-labelledby="groupComposeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form id="groupComposeForm" action="create_group_chat.php" method="POST" enctype="multipart/form-data">
        <div class="modal-header">
          <div>
            <h5 class="modal-title" id="groupComposeModalLabel">Create group chat</h5>
            <p class="compose-meta">Name the group and choose at least 2 members.</p>
          </div>
          <button type="button" class="close text-reset" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="compose-field">
            <label for="groupComposeName">Group name</label>
            <input type="text" name="group_name" id="groupComposeName" class="form-control" maxlength="255" required>
          </div>
          <div class="compose-field">
            <label for="groupComposeMembers">Members</label>
            <select name="members[]" id="groupComposeMembers" class="form-control" multiple required>
              <?php foreach ($composeRecipients as $composeRecipient): ?>
                <?php if ((int) $composeRecipient['id'] === (int) $userId) { continue; } ?>
                <option
                  value="user_<?php echo (int) $composeRecipient['id']; ?>"
                  data-avatar="<?php echo htmlspecialchars($composeRecipient['avatar_url']); ?>"
                  data-kind="user"
                  data-name="<?php echo htmlspecialchars($composeRecipient['username']); ?>"
                  data-email="<?php echo htmlspecialchars($composeRecipient['email']); ?>"
                  data-presence="<?php echo htmlspecialchars($composeRecipient['presence']['detail'] ?? 'Offline'); ?>"
                >
                  <?php echo htmlspecialchars($composeRecipient['username']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="compose-field">
            <label for="groupComposePhoto">Group photo</label>
            <input type="file" name="group_photo" id="groupComposePhoto" class="form-control" accept="image/jpeg,image/png,image/webp">
          </div>
          <div class="compose-body">
            <label for="groupComposeMessage" class="mb-2">Opening message</label>
            <textarea name="message" id="groupComposeMessage" class="form-control" rows="3" maxlength="5000"></textarea>
          </div>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($composeCsrfToken, ENT_QUOTES) ?>">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-users mr-1"></i> Create group</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade compose-modal" id="composeModal" tabindex="-1" aria-labelledby="composeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form id="composeForm" action="../send_contact.php" method="POST" enctype="multipart/form-data">
        <div class="modal-header">
          <div>
            <h5 class="modal-title" id="composeModalLabel">New chat</h5>
            <p class="compose-meta">Start a conversation without leaving Messenger.</p>
          </div>
          <button type="button" class="close text-reset" id="composeModalClose" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="compose-panel">
            <div class="compose-panel-head">
              <div>
                <h4>Start chat</h4>
                <p>Pick people, type your first message, and send.</p>
              </div>
              <span class="compose-pill"><i class="fas fa-bolt"></i> Instant send</span>
            </div>
            <div class="compose-field">
              <label for="composeRecipient">People</label>
              <select name="recipient[]" id="composeRecipient" class="form-control" multiple required>
                <?php if ($userType === 'admin'): ?>
                  <option value="all" data-avatar="<?php echo htmlspecialchars(app_url('dist/img/default.webp')); ?>" data-kind="group">All Users & Admins</option>
                  <option value="users" data-avatar="<?php echo htmlspecialchars(app_url('dist/img/default.webp')); ?>" data-kind="group">All Users Only</option>
                <?php endif; ?>
                <option value="admins" data-avatar="<?php echo htmlspecialchars(app_url('dist/img/default.webp')); ?>" data-kind="group">Admin</option>
                    <?php foreach ($composeRecipients as $composeRecipient): ?>
                      <option
                        value="user_<?php echo (int) $composeRecipient['id']; ?>"
                        data-avatar="<?php echo htmlspecialchars($composeRecipient['avatar_url']); ?>"
                        data-kind="user"
                        data-name="<?php echo htmlspecialchars($composeRecipient['username']); ?>"
                        data-email="<?php echo htmlspecialchars($composeRecipient['email']); ?>"
                        data-presence="<?php echo htmlspecialchars($composeRecipient['presence']['detail'] ?? 'Offline'); ?>"
                      >
                        <?php echo htmlspecialchars($composeRecipient['username']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
              <div class="compose-stats">
                <span class="compose-stat-pill" id="composeRecipientSummary">Choose one or more people</span>
              </div>
            </div>
            <input type="hidden" name="subject" id="composeSubject" value="">
            <div class="compose-body">
              <label for="composeMessage" class="mb-2">Opening message</label>
              <div class="compose-message-shell">
                <div class="composer-mention-preview" id="composeMentionPreview" aria-hidden="true"></div>
                <textarea name="message" id="composeMessage" class="form-control" rows="6" maxlength="5000" placeholder="Write your first message..."></textarea>
                <div class="compose-tool-stack">
                  <button type="submit" class="compose-tool-btn compose-send-btn" id="composeInlineSendBtn" aria-label="Send chat" title="Send">
                    <i class="fas fa-paper-plane"></i>
                  </button>
                  <button type="button" class="compose-tool-btn" id="composeAttachTrigger" aria-label="Attach files" title="Attach files">
                    <i class="fas fa-paperclip"></i>
                  </button>
                  <button type="button" class="compose-tool-btn" id="composeEmojiTrigger" aria-label="Insert emoji" aria-expanded="false" aria-controls="composeEmojiMenu" title="Emoji">
                    <i class="far fa-smile"></i>
                  </button>
                  <div class="emoji-menu" id="composeEmojiMenu" hidden></div>
                </div>
                <div class="compose-file-preview" id="composeFilePreview" aria-live="polite"></div>
              </div>
              <div class="compose-stats">
                <span class="compose-stat-pill"><strong id="composeMessageCount">0</strong>/5000 text</span>
              </div>
              <input type="file" name="attachments[]" id="composeAttachments" multiple hidden>
              <div class="compose-attachment-meta">
                <div class="compose-file-summary" id="composeFileSummary">No files selected</div>
                <div class="attachment-validation-message" id="composeAttachmentError" role="alert" aria-live="polite" hidden></div>
              </div>
            </div>
          </div>

          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($composeCsrfToken, ENT_QUOTES) ?>">
          <input type="hidden" name="return_to" value="messenger/">
          <input type="hidden" name="defer_mail" value="1">
        </div>
        <div class="modal-footer justify-content-between">
          <div class="small text-muted">Your new chat will appear instantly in Messenger.</div>
          <div class="d-flex align-items-center" style="gap:0.65rem;">
            <button type="button" class="btn btn-outline-secondary" id="composeResetBtn">Clear</button>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-paper-plane mr-1"></i> Start chat
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="groupMembersModal" tabindex="-1" aria-labelledby="groupMembersModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="groupMembersModalLabel">Group members</h5>
          <p class="mb-0 text-muted small" id="groupMembersModalMeta">Members in this group chat</p>
        </div>
        <button type="button" class="close text-reset" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div id="groupMembersList" class="group-members-list">
          <div class="text-center text-muted py-4">
            <i class="fas fa-spinner fa-spin mr-1"></i> Loading members...
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-default" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="groupMembersAddBtn" hidden>
          <i class="fas fa-user-plus mr-1"></i> Add Member
        </button>
      </div>
    </div>
  </div>
</div>

<script src="<?php echo app_url('plugins/jquery/jquery.min.js'); ?>"></script>
<script src="<?php echo app_url('plugins/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
<script src="<?php echo app_url('plugins/select2/js/select2.full.min.js'); ?>"></script>
<script src="../plugins/sweetalert2/sweetalert2.min.js"></script>
<script>
window.KODUSMessengerAttachmentLimits = <?= json_encode($attachmentLimits, JSON_UNESCAPED_SLASHES) ?>;
if (window.frameElement && !document.querySelector('[data-widget="iframe"]')) {
    try {
        localStorage.setItem('AdminLTE:IFrame:Options', JSON.stringify({ autoIframeMode: false }));
    } catch (error) {}
}
</script>
<script src="<?php echo app_url('dist/js/adminlte.min.js'); ?>"></script>

<script>
let lastOpenedId = null;
window.KODUSActiveMessengerThreadId = null;
let firstLoadDone = false;
let typingHeartbeatTimer = null;
let typingStopTimer = null;
let lastTypingMessageId = null;
let typingRefreshTimeout = null;
let liveTypingUsers = {};
let messengerPresenceOnlineUsers = {};
const currentMessengerUserId = <?= json_encode(is_numeric($userId) ? (int) $userId : 0) ?>;
const messengerBaseUrl = <?= json_encode(app_url('messenger/index.php'), JSON_UNESCAPED_SLASHES) ?>;
window.currentUserId = String(currentMessengerUserId || '');
window.activeThreadId = '';
const currentMailboxFolder = <?= json_encode($currentFolder, JSON_UNESCAPED_SLASHES) ?>;

function getMessengerAttachmentLimits() {
    const limits = window.KODUSMessengerAttachmentLimits || {};
    return {
        maxFileSize: Number(limits.max_file_size || 67108864),
        maxTotalSize: Number(limits.max_total_size || 83886080),
        maxFileCount: Number(limits.max_file_count || 50),
        maxFileSizeLabel: String(limits.max_file_size_label || '64 MB'),
        maxTotalSizeLabel: String(limits.max_total_size_label || '80 MB')
    };
}

function formatAttachmentBytes(bytes) {
    const size = Number(bytes || 0);
    if (size >= 1048576) {
        return (size / 1048576).toFixed(size % 1048576 === 0 ? 0 : 1) + ' MB';
    }
    return Math.max(1, Math.ceil(size / 1024)) + ' KB';
}

function validateMessengerAttachments(files) {
    const list = Array.from(files || []);
    const limits = getMessengerAttachmentLimits();
    const totalSize = list.reduce((sum, file) => sum + Number(file.size || 0), 0);
    const oversized = list.find(file => Number(file.size || 0) > limits.maxFileSize);

    if (list.length > limits.maxFileCount) {
        return {
            valid: false,
            message: `You can attach up to ${limits.maxFileCount} files at a time.`
        };
    }

    if (oversized) {
        return {
            valid: false,
            message: `${oversized.name} is ${formatAttachmentBytes(oversized.size)}. Each file must be ${limits.maxFileSizeLabel} or smaller.`
        };
    }

    if (totalSize > limits.maxTotalSize) {
        return {
            valid: false,
            message: `These attachments total ${formatAttachmentBytes(totalSize)}. Keep each send to ${limits.maxTotalSizeLabel} or less.`
        };
    }

    return { valid: true, message: '' };
}

function setAttachmentValidationMessage(element, message) {
    if (!element) {
        return;
    }
    element.textContent = message || '';
    element.hidden = !message;
}

function messengerUploadErrorMessage(error, xhrOrResponse) {
    const status = Number(xhrOrResponse?.status || error?.status || 0);
    if (status === 413) {
        const limits = getMessengerAttachmentLimits();
        return `Those attachments are too large. Please keep each file to ${limits.maxFileSizeLabel} and the total to ${limits.maxTotalSizeLabel}.`;
    }

    const message = String(error?.message || xhrOrResponse?.responseJSON?.message || xhrOrResponse?.responseJSON?.error || '').trim();
    if (!message || /<\s*!doctype|<\s*html|request entity too large|413/i.test(message)) {
        return 'Unable to send those attachments. Please check the file size and try again.';
    }
    return message;
}
const canReplyInCurrentFolder = currentMailboxFolder !== 'trash';
const shouldOpenCompose = <?= $composeOpen ? 'true' : 'false' ?>;
const isKodusChatBubbleMode = new URLSearchParams(window.location.search).get('bubble') === '1';

if (isKodusChatBubbleMode) {
    document.body.classList.add('kodus-chat-bubble-mode');
}

function formatMessengerPresenceText(isOnline, lastActiveAt) {
    return classifyMessengerPresence(lastActiveAt, isOnline).detail;
}

function applyMessengerPresence(userId, isOnline, lastActiveAt) {
    const normalizedUserId = String(Number(userId || 0));
    if (normalizedUserId === '0') {
        return;
    }

    const effectiveLastActiveAt = lastActiveAt || (isOnline ? new Date().toISOString() : '');
    messengerPresenceOnlineUsers[normalizedUserId] = {
        online: !!isOnline,
        lastActiveAt: effectiveLastActiveAt
    };
    const presence = classifyMessengerPresence(effectiveLastActiveAt, isOnline);
    const className = presence.className;
    const detail = presence.detail;
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({
            type: 'kodus.presence.changed',
            user_id: Number(normalizedUserId),
            online: !!isOnline,
            last_active_at: effectiveLastActiveAt,
            presence_class: className
        }, window.location.origin);
    }

    document.querySelectorAll(`[data-presence-user-id="${normalizedUserId}"]`).forEach(function(node) {
        const dot = node.querySelector('.mailbox-presence-dot, .chat-thread-status-dot');
        if (dot) {
            dot.classList.remove('mailbox-presence-online', 'mailbox-presence-idle', 'mailbox-presence-offline', 'is-online', 'is-idle', 'is-offline');
            if (dot.classList.contains('chat-thread-status-dot')) {
                dot.classList.add('is-' + className);
            } else {
                dot.classList.add('mailbox-presence-' + className);
            }
            dot.setAttribute('aria-label', detail);
        }

        const wrap = node.querySelector('.mailbox-avatar-wrap');
        if (wrap) {
            wrap.setAttribute('title', detail);
        }

        const copy = node.querySelector('.chat-thread-presence');
        if (copy) {
            copy.textContent = detail;
        }
    });
}

function formatMessengerRelativeActivity(timestamp) {
    const seconds = Math.max(0, Math.floor((Date.now() - timestamp) / 1000));
    if (seconds < 60) {
        return 'just now';
    }

    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) {
        return `${minutes} min${minutes === 1 ? '' : 's'} ago`;
    }

    const hours = Math.floor(minutes / 60);
    if (hours < 24) {
        return `${hours} hour${hours === 1 ? '' : 's'} ago`;
    }

    const days = Math.floor(hours / 24);
    return `${days} day${days === 1 ? '' : 's'} ago`;
}

function classifyMessengerPresence(lastActiveAt, isOnline) {
    const lastActiveTime = Date.parse(String(lastActiveAt || ''));
    if (!Number.isFinite(lastActiveTime)) {
        return { className: 'offline', detail: lastActiveAt ? 'Activity unavailable' : 'No activity recorded' };
    }

    const seconds = Math.max(0, Math.floor((Date.now() - lastActiveTime) / 1000));
    if (isOnline && seconds <= 300) {
        return { className: 'online', detail: 'Active just now' };
    }

    const detail = 'Last active ' + formatMessengerRelativeActivity(lastActiveTime);
    return { className: seconds <= 1800 ? 'idle' : 'offline', detail };
}

function notifyParentKodusBubbleThreadState() {
    if (!isKodusChatBubbleMode || !window.parent || window.parent === window) {
        return;
    }

    const shell = document.querySelector('.chat-shell');
    if (!shell) {
        return;
    }

    const dot = document.querySelector('.chat-thread-status-dot');
    window.parent.postMessage({
        type: 'kodus.chatBubble.threadState',
        message_id: Number(shell.dataset.messageId || 0),
        title: document.querySelector('.mailbox-read-subject')?.textContent?.trim() || '',
        avatar: shell.dataset.threadAvatar || document.querySelector('.chat-thread-avatar')?.getAttribute('src') || '',
        presence_user_id: Number(shell.dataset.presenceUserId || 0),
        presence_class: dot?.classList.contains('is-online') ? 'online' : (dot?.classList.contains('is-idle') ? 'idle' : 'offline')
    }, window.location.origin);
}

function syncVisibleMessengerPresence() {
    Object.keys(messengerPresenceOnlineUsers).forEach(function(userId) {
        const presence = messengerPresenceOnlineUsers[userId] || {};
        applyMessengerPresence(userId, presence.online, presence.lastActiveAt);
    });
}

function normalizeBubbleThreadMenu() {
    if (!isKodusChatBubbleMode && !window.frameElement) {
        return;
    }

    document.querySelectorAll('.conversation-open-bubble-trigger').forEach(function(button) {
        button.classList.remove('conversation-open-bubble-trigger');
        button.classList.add('conversation-open-messenger-trigger');
        button.innerHTML = '<i class="fas fa-external-link-alt mr-2"></i>Open in Messenger';
    });
}

const KODUS_EMOJI_CATEGORIES = [
    { name: 'Smileys', icon: '😀', items: [['😀','grinning smile happy'],['😃','smile happy'],['😄','laugh happy'],['😁','grin'],['😆','laugh'],['😅','sweat smile'],['😂','joy tears'],['🤣','rofl laugh'],['😊','blush smile'],['😇','angel'],['🙂','slight smile'],['🙃','upside down'],['😉','wink'],['😍','heart eyes love'],['😘','kiss'],['😗','kiss'],['😙','kiss smile'],['😚','kiss closed'],['😋','yum'],['😛','tongue'],['😜','wink tongue'],['🤪','zany'],['😝','tongue'],['🤑','money'],['🤗','hug'],['🤭','oops'],['🤫','shush'],['🤔','thinking'],['🤐','zipper'],['🤨','raised eyebrow'],['😐','neutral'],['😑','expressionless'],['😶','no mouth'],['😏','smirk'],['😒','unamused'],['🙄','eyes'],['😬','grimace'],['🤥','lying'],['😌','relieved'],['😔','sad'],['😪','sleepy'],['🤤','drool'],['😴','sleep'],['😷','mask'],['🤒','sick'],['🤕','hurt'],['🤢','nausea'],['🤮','vomit'],['🤧','sneeze'],['🥵','hot'],['🥶','cold'],['🥴','woozy'],['😵','dizzy'],['🤯','mind blown'],['🥳','party'],['😎','cool'],['🤓','nerd'],['🧐','monocle'],['😕','confused'],['😟','worried'],['🙁','frown'],['☹️','frown'],['😮','wow'],['😯','hushed'],['😲','astonished'],['😳','flushed'],['🥺','pleading'],['😦','frown open'],['😧','anguish'],['😨','fear'],['😰','cold sweat'],['😥','sad sweat'],['😢','cry'],['😭','sob'],['😱','scream'],['😖','confounded'],['😣','persevere'],['😞','disappointed'],['😓','sweat'],['😩','weary'],['😫','tired'],['😤','triumph'],['😡','angry'],['😠','mad'],['🤬','curse'],['😈','devil'],['💀','skull'],['💩','poop'],['🤡','clown'],['👻','ghost'],['👽','alien'],['🤖','robot'],['😺','cat smile'],['😹','cat joy'],['😻','cat love'],['🙈','see no evil'],['🙉','hear no evil'],['🙊','speak no evil'],['💋','kiss mark'],['💌','love letter'],['💘','cupid'],['💝','gift heart'],['💖','sparkling heart'],['💗','growing heart'],['💓','beating heart'],['💞','revolving hearts'],['💕','two hearts'],['💟','heart decoration'],['❣️','heart exclamation'],['💔','broken heart'],['❤️','heart love'],['🧡','orange heart'],['💛','yellow heart'],['💚','green heart'],['💙','blue heart'],['💜','purple heart'],['🤎','brown heart'],['🖤','black heart'],['🤍','white heart']] },
    { name: 'People', icon: '👋', items: [['👋','wave hello'],['🤚','raised hand'],['🖐️','hand'],['✋','hand stop'],['🖖','vulcan'],['👌','ok hand'],['🤌','pinched fingers'],['🤏','pinch'],['✌️','peace'],['🤞','fingers crossed'],['🤟','love you'],['🤘','rock'],['🤙','call me'],['👈','left'],['👉','right'],['👆','up'],['👇','down'],['☝️','point up'],['👍','thumbs up like'],['👎','thumbs down'],['✊','fist'],['👊','punch'],['🤛','left fist'],['🤜','right fist'],['👏','clap'],['🙌','raised hands'],['👐','open hands'],['🤲','palms'],['🤝','handshake'],['🙏','pray thanks'],['✍️','writing'],['💅','nails'],['💪','muscle strong'],['🦾','robot arm'],['🦿','leg'],['🦵','leg'],['👂','ear'],['👃','nose'],['🧠','brain'],['🫀','heart organ'],['🫁','lungs'],['🦷','tooth'],['👀','eyes'],['👁️','eye'],['👅','tongue'],['👄','mouth'],['👶','baby'],['🧒','child'],['👦','boy'],['👧','girl'],['🧑','person'],['👨','man'],['👩','woman'],['🧓','older person'],['👴','old man'],['👵','old woman'],['🙍','frown person'],['🙎','pout person'],['🙅','no gesture'],['🙆','ok gesture'],['💁','info person'],['🙋','raise hand'],['🧏','deaf person'],['🙇','bow'],['🤦','facepalm'],['🤷','shrug'],['👨‍💻','man technologist'],['👩‍💻','woman technologist'],['👮','police'],['👷','worker'],['💂','guard'],['🕵️','detective'],['👩‍⚕️','health worker'],['👨‍🏫','teacher'],['👩‍🍳','cook'],['👨‍🎨','artist'],['👩‍🚀','astronaut'],['🧑‍⚖️','judge'],['🎅','santa'],['🧙','mage'],['🧚','fairy'],['🧛','vampire'],['🧜','merperson'],['🧝','elf'],['🧟','zombie'],['💆','massage'],['💇','haircut'],['🚶','walk'],['🏃','run'],['💃','dance'],['🕺','dance man'],['🧘','yoga'],['🛀','bath'],['🛌','sleep bed'],['👭','women holding hands'],['👫','people holding hands'],['👬','men holding hands'],['💏','kiss couple'],['💑','couple heart'],['👪','family']] },
    { name: 'Animals', icon: '🐶', items: [['🐶','dog'],['🐱','cat'],['🐭','mouse'],['🐹','hamster'],['🐰','rabbit'],['🦊','fox'],['🐻','bear'],['🐼','panda'],['🐨','koala'],['🐯','tiger'],['🦁','lion'],['🐮','cow'],['🐷','pig'],['🐸','frog'],['🐵','monkey'],['🐔','chicken'],['🐧','penguin'],['🐦','bird'],['🐤','chick'],['🦆','duck'],['🦅','eagle'],['🦉','owl'],['🦇','bat'],['🐺','wolf'],['🐗','boar'],['🐴','horse'],['🦄','unicorn'],['🐝','bee'],['🪱','worm'],['🐛','bug'],['🦋','butterfly'],['🐌','snail'],['🐞','lady beetle'],['🐜','ant'],['🪰','fly'],['🪲','beetle'],['🪳','cockroach'],['🦟','mosquito'],['🦗','cricket'],['🕷️','spider'],['🦂','scorpion'],['🐢','turtle'],['🐍','snake'],['🦎','lizard'],['🦖','t rex'],['🦕','dinosaur'],['🐙','octopus'],['🦑','squid'],['🦐','shrimp'],['🦞','lobster'],['🦀','crab'],['🐡','blowfish'],['🐠','fish'],['🐟','fish'],['🐬','dolphin'],['🐳','whale'],['🦈','shark'],['🐊','crocodile'],['🐅','tiger'],['🐆','leopard'],['🦓','zebra'],['🦍','gorilla'],['🦧','orangutan'],['🐘','elephant'],['🦛','hippo'],['🦏','rhino'],['🐪','camel'],['🦒','giraffe'],['🦘','kangaroo'],['🦬','bison'],['🐃','buffalo'],['🐄','cow'],['🐎','horse'],['🐖','pig'],['🐏','ram'],['🐑','sheep'],['🦙','llama'],['🐐','goat'],['🦌','deer'],['🐕','dog'],['🐈','cat'],['🪶','feather'],['🐓','rooster'],['🦃','turkey'],['🦤','dodo'],['🦢','swan'],['🦩','flamingo'],['🦚','peacock'],['🦜','parrot'],['🌵','cactus'],['🎄','tree'],['🌲','evergreen'],['🌳','tree'],['🌴','palm'],['🪵','wood'],['🌱','seedling'],['🌿','herb'],['☘️','shamrock'],['🍀','clover'],['🎍','bamboo'],['🪴','plant'],['🌷','tulip'],['🌹','rose'],['🌻','sunflower'],['🌼','blossom'],['🌸','cherry blossom'],['💐','bouquet']] },
    { name: 'Food', icon: '🍔', items: [['🍏','apple'],['🍎','apple red'],['🍐','pear'],['🍊','orange'],['🍋','lemon'],['🍌','banana'],['🍉','watermelon'],['🍇','grapes'],['🍓','strawberry'],['🫐','blueberries'],['🍈','melon'],['🍒','cherries'],['🍑','peach'],['🥭','mango'],['🍍','pineapple'],['🥥','coconut'],['🥝','kiwi'],['🍅','tomato'],['🍆','eggplant'],['🥑','avocado'],['🥦','broccoli'],['🥬','greens'],['🥒','cucumber'],['🌶️','pepper'],['🫑','bell pepper'],['🌽','corn'],['🥕','carrot'],['🫒','olive'],['🧄','garlic'],['🧅','onion'],['🥔','potato'],['🍠','sweet potato'],['🥐','croissant'],['🥯','bagel'],['🍞','bread'],['🥖','baguette'],['🥨','pretzel'],['🧀','cheese'],['🥚','egg'],['🍳','cooking'],['🧈','butter'],['🥞','pancakes'],['🧇','waffle'],['🥓','bacon'],['🥩','steak'],['🍗','chicken'],['🍖','meat'],['🌭','hot dog'],['🍔','burger'],['🍟','fries'],['🍕','pizza'],['🥪','sandwich'],['🥙','stuffed flatbread'],['🧆','falafel'],['🌮','taco'],['🌯','burrito'],['🫔','tamale'],['🥗','salad'],['🥘','paella'],['🫕','fondue'],['🥫','canned food'],['🍝','pasta'],['🍜','ramen'],['🍲','stew'],['🍛','curry'],['🍣','sushi'],['🍱','bento'],['🥟','dumpling'],['🦪','oyster'],['🍤','shrimp'],['🍙','rice ball'],['🍚','rice'],['🍘','cracker'],['🍥','fish cake'],['🥠','fortune cookie'],['🥮','moon cake'],['🍢','oden'],['🍡','dango'],['🍧','shaved ice'],['🍨','ice cream'],['🍦','soft serve'],['🥧','pie'],['🧁','cupcake'],['🍰','cake'],['🎂','birthday'],['🍮','custard'],['🍭','lollipop'],['🍬','candy'],['🍫','chocolate'],['🍿','popcorn'],['🍩','donut'],['🍪','cookie'],['🌰','chestnut'],['🥜','peanuts'],['🍯','honey'],['🥛','milk'],['🍼','baby bottle'],['☕','coffee'],['🫖','teapot'],['🍵','tea'],['🧃','juice'],['🥤','drink'],['🧋','bubble tea'],['🍶','sake'],['🍺','beer'],['🍻','cheers'],['🥂','toast'],['🍷','wine'],['🥃','tumbler'],['🍸','cocktail'],['🍹','tropical drink'],['🧉','mate'],['🍾','champagne'],['🧊','ice']] },
    { name: 'Activities', icon: '⚽', items: [['⚽','soccer'],['🏀','basketball'],['🏈','football'],['⚾','baseball'],['🥎','softball'],['🎾','tennis'],['🏐','volleyball'],['🏉','rugby'],['🥏','frisbee'],['🎱','pool'],['🪀','yo yo'],['🏓','ping pong'],['🏸','badminton'],['🏒','hockey'],['🏑','field hockey'],['🥍','lacrosse'],['🏏','cricket'],['🪃','boomerang'],['🥅','goal'],['⛳','golf'],['🪁','kite'],['🏹','archery'],['🎣','fishing'],['🤿','diving'],['🥊','boxing'],['🥋','martial arts'],['🎽','running shirt'],['🛹','skateboard'],['🛼','roller skate'],['🛷','sled'],['⛸️','ice skate'],['🥌','curling'],['🎿','ski'],['⛷️','skier'],['🏂','snowboard'],['🏋️','lifting'],['🤼','wrestling'],['🤸','cartwheel'],['⛹️','basketball person'],['🤺','fencing'],['🤾','handball'],['🏌️','golf person'],['🏇','horse racing'],['🧘','meditation'],['🏄','surfing'],['🏊','swimming'],['🤽','water polo'],['🚣','rowing'],['🧗','climbing'],['🚵','mountain bike'],['🚴','bike'],['🏆','trophy'],['🥇','gold medal'],['🥈','silver medal'],['🥉','bronze medal'],['🏅','medal'],['🎖️','military medal'],['🏵️','rosette'],['🎗️','ribbon'],['🎫','ticket'],['🎟️','tickets'],['🎪','circus'],['🤹','juggling'],['🎭','theater'],['🩰','ballet'],['🎨','art'],['🎬','movie'],['🎤','microphone'],['🎧','headphones'],['🎼','music'],['🎹','piano'],['🥁','drum'],['🪘','drum'],['🎷','saxophone'],['🎺','trumpet'],['🪗','accordion'],['🎸','guitar'],['🪕','banjo'],['🎻','violin'],['🎲','dice'],['♟️','chess'],['🎯','target'],['🎳','bowling'],['🎮','game'],['🎰','slot'],['🧩','puzzle']] },
    { name: 'Travel', icon: '✈️', items: [['🚗','car'],['🚕','taxi'],['🚙','suv'],['🚌','bus'],['🚎','trolley'],['🏎️','race car'],['🚓','police car'],['🚑','ambulance'],['🚒','fire truck'],['🚐','van'],['🛻','pickup'],['🚚','truck'],['🚛','truck'],['🚜','tractor'],['🦯','cane'],['🦽','wheelchair'],['🛴','scooter'],['🚲','bike'],['🛵','motor scooter'],['🏍️','motorcycle'],['🛺','auto rickshaw'],['🚨','siren'],['🚔','police'],['🚍','bus'],['🚘','car front'],['🚖','taxi'],['🚡','tram'],['🚠','cableway'],['🚟','railway'],['🚃','rail car'],['🚋','tram'],['🚞','mountain railway'],['🚝','monorail'],['🚄','train'],['🚅','bullet train'],['🚈','light rail'],['🚂','locomotive'],['🚆','train'],['🚇','metro'],['🚊','tram'],['🚉','station'],['✈️','airplane'],['🛫','departure'],['🛬','arrival'],['🛩️','small plane'],['💺','seat'],['🛰️','satellite'],['🚀','rocket'],['🛸','ufo'],['🚁','helicopter'],['🛶','canoe'],['⛵','sailboat'],['🚤','speedboat'],['🛥️','motor boat'],['🛳️','ship'],['⛴️','ferry'],['🚢','ship'],['⚓','anchor'],['🪝','hook'],['⛽','fuel'],['🚧','construction'],['🚦','traffic light'],['🚥','traffic light'],['🚏','bus stop'],['🗺️','map'],['🗿','moai'],['🗽','statue liberty'],['🗼','tokyo tower'],['🏰','castle'],['🏯','castle japan'],['🏟️','stadium'],['🎡','ferris wheel'],['🎢','roller coaster'],['🎠','carousel'],['⛲','fountain'],['⛱️','umbrella beach'],['🏖️','beach'],['🏝️','island'],['🏜️','desert'],['🌋','volcano'],['⛰️','mountain'],['🏔️','snow mountain'],['🗻','fuji'],['🏕️','camping'],['⛺','tent'],['🛖','hut'],['🏠','house'],['🏡','home'],['🏢','office'],['🏣','post office'],['🏥','hospital'],['🏦','bank'],['🏨','hotel'],['🏪','store'],['🏫','school'],['🏬','department store'],['🏭','factory'],['🏛️','classical building'],['⛪','church'],['🕌','mosque'],['🕍','synagogue'],['🛕','temple'],['🕋','kaaba'],['⛩️','shrine'],['🌅','sunrise'],['🌄','sunrise mountains'],['🌠','shooting star'],['🎇','sparkler'],['🎆','fireworks'],['🌇','sunset'],['🌃','night'],['🌌','milky way'],['🌁','fog'],['🌉','bridge'],['♨️','hot springs']] },
    { name: 'Objects', icon: '💡', items: [['⌚','watch'],['📱','phone'],['📲','phone arrow'],['💻','laptop'],['⌨️','keyboard'],['🖥️','desktop'],['🖨️','printer'],['🖱️','mouse'],['🖲️','trackball'],['🕹️','joystick'],['🗜️','clamp'],['💽','minidisc'],['💾','floppy'],['💿','disc'],['📀','dvd'],['📼','vhs'],['📷','camera'],['📸','camera flash'],['📹','video camera'],['🎥','movie camera'],['📽️','projector'],['🎞️','film'],['📞','phone'],['☎️','telephone'],['📟','pager'],['📠','fax'],['📺','tv'],['📻','radio'],['🎙️','studio mic'],['🎚️','slider'],['🎛️','control knobs'],['🧭','compass'],['⏱️','stopwatch'],['⏲️','timer'],['⏰','alarm'],['🕰️','clock'],['⌛','hourglass'],['⏳','hourglass'],['📡','satellite dish'],['🔋','battery'],['🔌','plug'],['💡','light bulb'],['🔦','flashlight'],['🕯️','candle'],['🪔','lamp'],['🧯','extinguisher'],['🛢️','oil'],['💸','money'],['💵','dollar'],['💴','yen'],['💶','euro'],['💷','pound'],['🪙','coin'],['💰','money bag'],['💳','credit card'],['💎','gem'],['⚖️','scale'],['🪜','ladder'],['🧰','toolbox'],['🔧','wrench'],['🔨','hammer'],['⚒️','hammer pick'],['🛠️','tools'],['⛏️','pick'],['🪓','axe'],['🪚','saw'],['🔩','nut bolt'],['⚙️','gear'],['🪤','trap'],['🧱','brick'],['⛓️','chains'],['🧲','magnet'],['🔫','water pistol'],['💣','bomb'],['🧨','firecracker'],['🪓','axe'],['🔪','knife'],['🗡️','dagger'],['⚔️','swords'],['🛡️','shield'],['🚬','cigarette'],['⚰️','coffin'],['⚱️','urn'],['🏺','amphora'],['🔮','crystal ball'],['📿','beads'],['🧿','nazar'],['💈','barber'],['⚗️','alembic'],['🔭','telescope'],['🔬','microscope'],['🕳️','hole'],['🩹','bandage'],['🩺','stethoscope'],['💊','pill'],['💉','syringe'],['🩸','blood'],['🧬','dna'],['🦠','microbe'],['🧫','petri dish'],['🧪','test tube'],['🌡️','thermometer'],['🧹','broom'],['🧺','basket'],['🧻','paper'],['🚽','toilet'],['🚰','water'],['🚿','shower'],['🛁','bathtub'],['🪒','razor'],['🧽','sponge'],['🧴','lotion'],['🛎️','bell'],['🔑','key'],['🗝️','old key'],['🚪','door'],['🪑','chair'],['🛋️','couch'],['🛏️','bed'],['🧸','teddy'],['🖼️','picture'],['🛍️','shopping bags'],['🛒','cart'],['🎁','gift'],['🎈','balloon'],['🎏','carp streamer'],['🎀','ribbon'],['🪄','magic wand'],['🪅','pinata'],['🎊','confetti'],['🎉','party popper'],['📩','mail'],['📨','incoming mail'],['📧','email'],['💌','love letter'],['📥','inbox'],['📤','outbox'],['📦','package'],['🏷️','label'],['📌','pin'],['📍','pin'],['📎','paperclip'],['🖇️','paperclips'],['📏','ruler'],['📐','triangle ruler'],['✂️','scissors'],['🗃️','card box'],['🗄️','file cabinet'],['🗑️','trash'],['🔒','lock'],['🔓','unlock'],['🔏','locked pen'],['🔐','locked key'],['🖊️','pen'],['🖋️','fountain pen'],['✒️','nib'],['📝','memo'],['📁','folder'],['📂','open folder'],['🗂️','dividers'],['📅','calendar'],['📆','tear calendar'],['🗒️','notepad'],['🗓️','calendar'],['📇','card index'],['📈','chart up'],['📉','chart down'],['📊','bar chart'],['📋','clipboard'],['📚','books'],['📖','book'],['🔖','bookmark'],['🧷','safety pin']] },
    { name: 'Symbols', icon: '✅', items: [['✅','check'],['☑️','checked box'],['✔️','check mark'],['❌','cross'],['❎','cross mark'],['➕','plus'],['➖','minus'],['➗','divide'],['✖️','multiply'],['♾️','infinity'],['‼️','double exclamation'],['⁉️','exclamation question'],['❓','question'],['❔','question white'],['❕','exclamation white'],['❗','exclamation'],['〰️','wavy dash'],['💱','currency exchange'],['💲','dollar'],['⚕️','medical'],['♻️','recycle'],['⚜️','fleur de lis'],['🔱','trident'],['📛','name badge'],['🔰','beginner'],['⭕','circle'],['🟢','green circle'],['🟡','yellow circle'],['🟠','orange circle'],['🔴','red circle'],['🟣','purple circle'],['🔵','blue circle'],['⚫','black circle'],['⚪','white circle'],['🟤','brown circle'],['⬛','black square'],['⬜','white square'],['◼️','black medium square'],['◻️','white medium square'],['◾','black small square'],['◽','white small square'],['▪️','black tiny square'],['▫️','white tiny square'],['🔶','orange diamond'],['🔷','blue diamond'],['🔸','small orange diamond'],['🔹','small blue diamond'],['🔺','red triangle up'],['🔻','red triangle down'],['💠','diamond dot'],['🔘','radio'],['🔳','white square button'],['🔲','black square button'],['🏁','checkered flag'],['🚩','triangular flag'],['🎌','crossed flags'],['🏳️','white flag'],['🏴','black flag'],['🏳️‍🌈','rainbow flag'],['🏳️‍⚧️','trans flag'],['🔔','bell'],['🔕','bell off'],['📣','megaphone'],['📢','loudspeaker'],['💬','speech'],['💭','thought'],['🗯️','anger bubble'],['♈','aries'],['♉','taurus'],['♊','gemini'],['♋','cancer'],['♌','leo'],['♍','virgo'],['♎','libra'],['♏','scorpio'],['♐','sagittarius'],['♑','capricorn'],['♒','aquarius'],['♓','pisces'],['⛎','ophiuchus'],['🔀','shuffle'],['🔁','repeat'],['🔂','repeat one'],['▶️','play'],['⏩','fast forward'],['⏭️','next'],['⏯️','play pause'],['◀️','reverse'],['⏪','rewind'],['⏮️','previous'],['🔼','up'],['⏫','fast up'],['🔽','down'],['⏬','fast down'],['⏸️','pause'],['⏹️','stop'],['⏺️','record'],['⏏️','eject'],['🎦','cinema'],['🔅','low brightness'],['🔆','brightness'],['📶','signal'],['📳','vibration'],['📴','mobile off'],['♀️','female'],['♂️','male'],['⚧️','transgender'],['✳️','asterisk'],['✴️','star'],['❇️','sparkle'],['©️','copyright'],['®️','registered'],['™️','trademark'],['#️⃣','hash'],['*️⃣','asterisk key'],['0️⃣','zero'],['1️⃣','one'],['2️⃣','two'],['3️⃣','three'],['4️⃣','four'],['5️⃣','five'],['6️⃣','six'],['7️⃣','seven'],['8️⃣','eight'],['9️⃣','nine'],['🔟','ten']] },
    { name: 'Flags', icon: '🏳️', items: [['🇺🇸','flag united states usa'],['🇵🇭','flag philippines'],['🇨🇦','flag canada'],['🇲🇽','flag mexico'],['🇧🇷','flag brazil'],['🇦🇷','flag argentina'],['🇬🇧','flag united kingdom uk'],['🇮🇪','flag ireland'],['🇫🇷','flag france'],['🇩🇪','flag germany'],['🇮🇹','flag italy'],['🇪🇸','flag spain'],['🇵🇹','flag portugal'],['🇳🇱','flag netherlands'],['🇧🇪','flag belgium'],['🇨🇭','flag switzerland'],['🇦🇹','flag austria'],['🇸🇪','flag sweden'],['🇳🇴','flag norway'],['🇩🇰','flag denmark'],['🇫🇮','flag finland'],['🇵🇱','flag poland'],['🇺🇦','flag ukraine'],['🇬🇷','flag greece'],['🇹🇷','flag turkey'],['🇮🇱','flag israel'],['🇸🇦','flag saudi arabia'],['🇦🇪','flag uae'],['🇮🇳','flag india'],['🇵🇰','flag pakistan'],['🇧🇩','flag bangladesh'],['🇨🇳','flag china'],['🇯🇵','flag japan'],['🇰🇷','flag korea'],['🇹🇭','flag thailand'],['🇻🇳','flag vietnam'],['🇮🇩','flag indonesia'],['🇲🇾','flag malaysia'],['🇸🇬','flag singapore'],['🇦🇺','flag australia'],['🇳🇿','flag new zealand'],['🇿🇦','flag south africa'],['🇳🇬','flag nigeria'],['🇰🇪','flag kenya'],['🇪🇬','flag egypt']] }
];

function flattenKodusEmojis() {
    return KODUS_EMOJI_CATEGORIES.flatMap(category => category.items.map(item => ({
        emoji: item[0],
        keywords: item[1],
        category: category.name
    })));
}

function renderEmojiMenu(menu, onSelect, options = {}) {
    if (!menu) {
        return;
    }

    const buttonClass = options.reaction ? 'chat-reaction-add emoji-item' : 'emoji-item';
    const grid = document.createElement('div');
    grid.className = 'emoji-grid';
    KODUS_EMOJI_CATEGORIES.forEach(function(category) {
        const label = document.createElement('div');
        label.className = 'emoji-panel-label';
        label.textContent = category.name;
        grid.appendChild(label);
        category.items.forEach(function(item) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = buttonClass;
            button.textContent = item[0];
            button.dataset.emoji = item[0];
            button.dataset.keywords = `${category.name} ${item[1]}`.toLowerCase();
            if (typeof onSelect === 'function') {
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    onSelect(item[0], event);
                });
            }
            grid.appendChild(button);
        });
    });

    const head = document.createElement('div');
    head.className = 'emoji-panel-head';
    const headLabel = document.createElement('span');
    headLabel.textContent = 'Emoji';
    const cats = document.createElement('span');
    cats.className = 'emoji-panel-cats';
    KODUS_EMOJI_CATEGORIES.forEach(function(category) {
        const catButton = document.createElement('button');
        catButton.type = 'button';
        catButton.className = 'emoji-panel-cat';
        catButton.textContent = category.icon;
        catButton.title = category.name;
        catButton.setAttribute('aria-label', category.name);
        catButton.addEventListener('click', function(event) {
            event.preventDefault();
            const label = Array.from(grid.querySelectorAll('.emoji-panel-label')).find(function(node) {
                return node.textContent === category.name;
            });
            if (label) {
                label.scrollIntoView({ block: 'start' });
            }
        });
        cats.appendChild(catButton);
    });
    head.append(headLabel, cats);

    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'emoji-panel-search';
    search.placeholder = 'Search emoji';
    search.setAttribute('aria-label', 'Search emoji');
    search.addEventListener('input', function() {
        const query = this.value.trim().toLowerCase();
        grid.querySelectorAll('.emoji-item').forEach(function(button) {
            button.hidden = query !== '' && !button.textContent.toLowerCase().includes(query) && !(button.dataset.keywords || '').includes(query);
        });
        grid.querySelectorAll('.emoji-panel-label').forEach(function(label) {
            const nextVisible = Array.from(grid.querySelectorAll('.emoji-item')).some(function(button) {
                return !button.hidden && button.dataset.keywords?.includes(label.textContent.toLowerCase());
            });
            label.hidden = query !== '' && !nextVisible;
        });
    });

    menu.replaceChildren(head, search, grid);
    menu.dataset.enhanced = '1';
}

function enhanceEmojiMenu(menu) {
    if (!menu || menu.dataset.enhanced === '1') {
        return;
    }

    if (menu.classList.contains('chat-reaction-picker') || menu.dataset.emojiPicker === 'reaction') {
        renderEmojiMenu(menu, null, { reaction: true });
        return;
    }

    const existingButtons = Array.from(menu.querySelectorAll('.emoji-item'));
    if (!existingButtons.length) {
        renderEmojiMenu(menu, null);
        return;
    }

    renderEmojiMenu(menu, function(emoji, event) {
        const original = existingButtons.find(button => button.textContent === emoji);
        if (original) {
            original.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
        }
    });
}

function positionEmojiMenu(trigger, menu) {
    if (!trigger || !menu || menu.hidden) {
        return;
    }

    enhanceEmojiMenu(menu);

    const modalContent = trigger.closest?.('.modal-content') || null;
    const modalRect = modalContent ? modalContent.getBoundingClientRect() : null;

    if (modalContent) {
        if (menu.parentElement !== modalContent) {
            modalContent.appendChild(menu);
        }
        menu.classList.add('is-modal-bound');
    } else {
        menu.classList.remove('is-modal-bound');
    }

    const triggerRect = trigger.getBoundingClientRect();
    const menuRect = menu.getBoundingClientRect();
    const gap = 8;
    const bounds = modalRect
        ? {
            left: 12,
            top: 12,
            right: Math.max(12, modalRect.width - 12),
            bottom: Math.max(12, modalRect.height - 12)
        }
        : {
            left: 8,
            top: 8,
            right: window.innerWidth - 8,
            bottom: window.innerHeight - 8
        };
    const localTrigger = modalRect
        ? {
            left: triggerRect.left - modalRect.left,
            right: triggerRect.right - modalRect.left,
            top: triggerRect.top - modalRect.top,
            bottom: triggerRect.bottom - modalRect.top
        }
        : triggerRect;
    const menuWidth = Math.min(menuRect.width || 328, bounds.right - bounds.left);
    const menuHeight = Math.min(menuRect.height || 238, bounds.bottom - bounds.top);

    let left = localTrigger.right - menuWidth;
    let top = localTrigger.bottom + gap;

    if (top + menuHeight > bounds.bottom) {
        top = localTrigger.top - menuHeight - gap;
    }

    if (left < bounds.left) {
        left = localTrigger.left;
    }

    left = Math.max(bounds.left, Math.min(left, bounds.right - menuWidth));
    top = Math.max(bounds.top, Math.min(top, bounds.bottom - menuHeight));

    menu.style.left = `${Math.round(left)}px`;
    menu.style.top = `${Math.round(top)}px`;
    menu.style.right = 'auto';
    menu.style.bottom = 'auto';
    menu.style.maxWidth = `${Math.max(180, Math.round(bounds.right - bounds.left))}px`;
}

function closeEmojiPicker(trigger, menu) {
    if (!menu) {
        return;
    }

    menu.hidden = true;
    menu.style.left = '';
    menu.style.top = '';
    menu.style.right = '';
    menu.style.bottom = '';

    if (trigger) {
        trigger.setAttribute('aria-expanded', 'false');
    }
}

function toggleEmojiPicker(trigger, menu) {
    if (!trigger || !menu) {
        return false;
    }

    const shouldOpen = menu.hidden;
    closeEmojiPicker(trigger, menu);
    menu.hidden = !shouldOpen;
    trigger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');

    if (shouldOpen) {
        positionEmojiMenu(trigger, menu);
    }

    return shouldOpen;
}

function repositionOpenEmojiMenus() {
    document.querySelectorAll('.emoji-menu:not([hidden])').forEach(function(menu) {
        const trigger = menu.parentElement?.querySelector('[aria-expanded="true"]')
            || (menu.id ? document.querySelector(`[aria-controls="${menu.id}"]`) : null);
        if (trigger) {
            positionEmojiMenu(trigger, menu);
        }
    });
}

window.KODUSPositionEmojiMenu = positionEmojiMenu;
window.KODUSEnhanceEmojiMenu = enhanceEmojiMenu;
window.KODUSRepositionOpenEmojiMenus = repositionOpenEmojiMenus;
window.KODUSEmojiPicker = {
    close: closeEmojiPicker,
    toggle: toggleEmojiPicker,
    position: positionEmojiMenu,
    enhance: enhanceEmojiMenu,
    render: renderEmojiMenu,
    all: flattenKodusEmojis
};

function syncMessengerViewport() {
    const wrapper = document.querySelector('.wrapper.mailbox-app');
    const topbar = document.getElementById('mainTopbar') || document.querySelector('.main-header');
    const footer = document.querySelector('.main-footer');

    if (!wrapper) {
        return;
    }

    const topOffset = Math.max(0, Math.round(topbar?.getBoundingClientRect().height || 0));
    const footerOffset = Math.max(0, Math.round(footer?.getBoundingClientRect().height || 0));
    const availableHeight = Math.max(420, window.innerHeight - topOffset - footerOffset);

    wrapper.style.setProperty('--messenger-top-offset', `${topOffset}px`);
    wrapper.style.setProperty('--messenger-footer-offset', `${footerOffset}px`);
    wrapper.style.setProperty('--messenger-shell-height', `${availableHeight}px`);

    if (document.body) {
        document.body.classList.add('messenger-page');
        document.body.style.overflow = 'hidden';
    }
    if (document.documentElement) {
        document.documentElement.classList.add('messenger-page-root');
        document.documentElement.style.overflow = 'hidden';
    }
}

function getFolderEmptyMessage() {
    if (currentMailboxFolder === 'trash') {
        return {
            title: 'No chat selected',
            body: 'Your archive is currently empty.'
        };
    }

    return {
        title: 'No chat selected',
        body: 'Your chat list is currently empty.'
    };
}

function isMobileMailboxView() {
    return window.matchMedia('(max-width: 767.98px)').matches;
}

function updateDetailTitle() {
    const activeRow = lastOpenedId ? $(`#messageList .message-item[data-id="${lastOpenedId}"]`) : $();
    const title = activeRow.length
        ? (activeRow.find('.mailbox-name').text().trim() || activeRow.attr('data-subject') || 'Chat')
        : 'Chat';

    $('#mailboxDetailTitle').text(title);
}

function setMobileMailboxView(mode) {
    const app = $('.mailbox-app');
    app.removeClass('mobile-thread-list mobile-thread-detail');

    if (!isMobileMailboxView()) {
        $('#mobileBackToList').hide();
        return;
    }

    const nextMode = mode === 'detail' && lastOpenedId ? 'detail' : 'list';
    app.addClass(nextMode === 'detail' ? 'mobile-thread-detail' : 'mobile-thread-list');
    $('#mobileBackToList').toggle(nextMode === 'detail');
    updateDetailTitle();
}

function normalizeHtml(html) {
    return String(html || '').replace(/\s+/g, ' ').trim();
}

function getConversationMarkup() {
    return normalizeHtml($('#conversationWrapper').prop('outerHTML') || '');
}

function getComparableConversationMarkupFromHtml(html) {
    const wrapper = document.createElement('div');
    wrapper.innerHTML = String(html || '');
    wrapper.querySelectorAll('.chat-reaction-picker').forEach(function(picker) {
        picker.remove();
    });
    wrapper.querySelectorAll('.chat-reaction-trigger').forEach(function(trigger) {
        trigger.setAttribute('aria-expanded', 'false');
    });
    return normalizeHtml(wrapper.innerHTML);
}

function isConversationNearBottom() {
    return false;
}

function updateMailboxSummary() {
    const items = $('#messageList .message-item');
    const unreadItems = $('#messageList .message-item.unread');
    const unreadCount = unreadItems.length;

    $('#messageCountLabel').text(items.length);
    $('#unreadCountLabel').text(unreadCount);
    if (currentMailboxFolder === 'trash') {
        $('#sidebarTrashBadge').text(items.length);
    }

    if (currentMailboxFolder === 'inbox') {
        const sidebarBadge = $('#sidebarUnreadBadge, #sidebarMailUnreadBadge');
        if (unreadCount > 0) {
            sidebarBadge.text(unreadCount).show();
        } else {
            sidebarBadge.hide();
        }
    }

    syncBulkSelectionUi();
}

function renderEmptyDetail() {
    closeReactionPickers();
    const emptyMessage = getFolderEmptyMessage();
    $('#messageDetail').html(`
      <div class="mailbox-empty-detail">
        <i class="far fa-comment-dots fa-3x mb-3"></i>
        <p class="mb-1"><strong>${emptyMessage.title}</strong></p>
        <p class="mb-0">${emptyMessage.body}</p>
      </div>
    `);
    stopTypingHeartbeat();
    clearTypingIndicator();
    window.KODUSActiveMessengerThreadId = null;
    window.activeThreadId = '';
    updateDetailTitle();
}

function updateRefreshLabel(hasChanges = false) {
    if (!hasChanges) {
        $('#mailboxRefreshLabel').text('Synced');
        return;
    }

    const now = new Date();
    $('#mailboxRefreshLabel').text('Updated ' + now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }));
}

function applyMessageFilters() {
    const query = ($('#mailboxSearch').val() || '').toLowerCase().trim();
    const filter = $('.mailbox-filter.active').data('filter') || 'all';
    let visibleCount = 0;

    $('#messageList .message-item').each(function() {
        const $item = $(this);
        const haystack = $item.text().toLowerCase();
        const matchesQuery = haystack.includes(query);
        const isUnread = $item.attr('data-unread') === '1';
        const hasAttachment = $item.attr('data-has-attachment') === '1';

        let matchesFilter = true;
        if (filter === 'unread') {
            matchesFilter = isUnread;
        } else if (filter === 'attachments') {
            matchesFilter = hasAttachment;
        }

        const shouldShow = matchesQuery && matchesFilter;
        $item.toggle(shouldShow);
        if (shouldShow) {
            visibleCount++;
        }
    });

    $('#messageList .table-responsive').toggle(visibleCount > 0);
    $('#mailboxNoResults').toggle(visibleCount === 0 && $('#messageList .message-item').length > 0);
    syncBulkSelectionUi();
}

function getSelectedMessageIds() {
    return $('#messageList .mailbox-message-select:checked').map(function() {
        return Number($(this).val() || 0);
    }).get().filter(function(id) {
        return id > 0;
    });
}

function syncBulkSelectionUi() {
    const $selectAll = $('#mailboxSelectAll');
    const $visibleCheckboxes = $('#messageList .message-item:visible .mailbox-message-select');
    const visibleCount = $visibleCheckboxes.length;
    const checkedVisibleCount = $visibleCheckboxes.filter(':checked').length;
    const totalSelectedCount = getSelectedMessageIds().length;
    const hasAction = String($('#mailboxBulkAction').val() || '') !== '';

    if ($selectAll.length) {
        $selectAll.prop('checked', visibleCount > 0 && checkedVisibleCount === visibleCount);
        $selectAll.prop('indeterminate', checkedVisibleCount > 0 && checkedVisibleCount < visibleCount);
    }

    $('#mailboxBulkSummary').text(totalSelectedCount + ' selected');
    $('#mailboxBulkApply').prop('disabled', totalSelectedCount === 0 || !hasAction);
}

function openMessage(id) {
    if (!id) {
        return;
    }

    stopTypingHeartbeat();
    clearTypingIndicator();
    clearReplyQuoteDraft();
    lastOpenedId = id;
    window.KODUSActiveMessengerThreadId = Number(id) || null;
    window.activeThreadId = String(id || '');
    firstLoadDone = false;

    $('.message-item').removeClass('active');
    $(`#messageList .message-item[data-id="${id}"]`).addClass('active').removeClass('unread');
    updateMailboxSummary();

    $.get('get_thread.php', { id: id, folder: currentMailboxFolder, bubble: isKodusChatBubbleMode ? 1 : 0 }, function(html) {
        closeReactionPickers();
        $('#messageDetail').html(html);
        updateDetailTitle();
        setMobileMailboxView('detail');
        initializeThreadExperience();
        normalizeBubbleThreadMenu();
        syncVisibleMessengerPresence();
        notifyParentKodusBubbleThreadState();
        scrollConversationToBottomOnOpen();
        firstLoadDone = true;
    });

    $.post('mark_read.php', { id: id, csrf_token: window.KODUS_CSRF_TOKEN }, function() {
        $(`#messageList .message-item[data-id="${id}"]`).attr('data-unread', '0');
        $(`#messageList .message-item[data-id="${id}"] .mailbox-star i`).removeClass('text-warning').addClass('text-muted');
        updateUnreadCount();
        updateMailboxSummary();
        applyMessageFilters();
    });
}

function updateMessageList() {
    $.get('fetch_messages.php', { folder: currentMailboxFolder }, function(html) {
        const normalizedIncoming = normalizeHtml(html);
        const normalizedCurrent = normalizeHtml($('#messageList').html());

        if (normalizedIncoming === normalizedCurrent) {
            updateRefreshLabel(false);
            return;
        }

        $('#messageList').html(html);

        if (lastOpenedId) {
            const activeRow = $(`#messageList .message-item[data-id="${lastOpenedId}"]`);
            if (activeRow.length) {
                activeRow.addClass('active');
            } else {
                lastOpenedId = null;
                renderEmptyDetail();
                setMobileMailboxView('list');
            }
        }

        if ($('#messageList').find('.message-item').length === 0) {
            renderEmptyDetail();
            setMobileMailboxView('list');
        }

        updateMailboxSummary();
        updateRefreshLabel(true);
        applyMessageFilters();
        syncVisibleMessengerPresence();
    });
}

function applyUnreadCount(count) {
    const unreadCount = Number(count || 0);
    const badge = $('#sidebarMailUnreadBadge');
    const sidebarBadge = $('#sidebarUnreadBadge');
    const topbarBadge = $('#topbarUnreadBadge');
    const mailNavLabel = $('.nav-item a[href$="messenger/"] p');

    if (unreadCount > 0) {
        sidebarBadge.text(unreadCount).show();
        if (badge.length) {
            badge.text(unreadCount);
        } else {
            mailNavLabel.append(`<span class="right badge badge-danger" id="sidebarMailUnreadBadge">${unreadCount}</span>`);
        }

        if (topbarBadge.length) {
            topbarBadge.text(unreadCount).show();
        }
    } else {
        sidebarBadge.hide();
        badge.remove();
        topbarBadge.remove();
    }
}

function updateUnreadCount() {
    $.getJSON('get_unread_count.php', function(data) {
        applyUnreadCount(data.count || 0);
    });
}

function preserveConversationScrollPosition(previousScrollTop) {
    const conv = $('#conversationWrapper .conversation-scroll');
    if (!conv.length) {
        return;
    }
    conv.scrollTop(Math.max(0, Number(previousScrollTop || 0)));
}

function scrollConversationToBottomOnOpen() {
    const conv = $('#conversationWrapper .conversation-scroll');
    if (!conv.length) {
        return;
    }
    conv.scrollTop(conv[0].scrollHeight);
}

function isConversationNearBottom() {
    const conv = $('#conversationWrapper .conversation-scroll');
    if (!conv.length || !conv[0]) {
        return true;
    }

    const node = conv[0];
    return (node.scrollHeight - node.clientHeight - node.scrollTop) <= 96;
}

function escapeRegExp(value) {
    return String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function(char) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char] || char;
    });
}

function logKodusRealtimeDebug() {
    try {
        if (window.localStorage && window.localStorage.getItem('KODUS_SOCKET_DEBUG') === '1') {
            console.debug.apply(console, arguments);
        }
    } catch (error) {
        // Ignore storage access errors in restricted browser modes.
    }
}

function stopTypingHeartbeat() {
    if (typingHeartbeatTimer) {
        clearTimeout(typingHeartbeatTimer);
        typingHeartbeatTimer = null;
    }
    if (typingStopTimer) {
        clearTimeout(typingStopTimer);
        typingStopTimer = null;
    }

    if (!lastTypingMessageId) {
        return;
    }

    $.post('update_typing_status.php', {
        message_id: lastTypingMessageId,
        is_typing: 0,
        csrf_token: window.KODUS_CSRF_TOKEN
    });
    lastTypingMessageId = null;
}

function clearTypingIndicator() {
    if (typingRefreshTimeout) {
        clearTimeout(typingRefreshTimeout);
        typingRefreshTimeout = null;
    }
    if (window.typingTimeout) {
        clearTimeout(window.typingTimeout);
        window.typingTimeout = null;
    }
    Object.values(liveTypingUsers).forEach(function(entry) {
        if (entry && entry.timer) {
            clearTimeout(entry.timer);
        }
    });
    liveTypingUsers = {};
    const el = document.getElementById('threadTypingIndicator');
    if (el) {
        el.hidden = true;
        el.style.display = 'none';
    }
}

function ensureTypingIndicatorPlacement() {
    const conversation = document.querySelector('#conversationWrapper .conversation-scroll');
    let el = document.getElementById('threadTypingIndicator');

    if (!conversation) {
        return null;
    }

    if (!el) {
        el = document.createElement('div');
        el.id = 'threadTypingIndicator';
        el.hidden = true;
        el.style.display = 'none';
    }

    el.className = 'chat-typing-indicator reply theirs';
    if (el.parentElement !== conversation) {
        conversation.appendChild(el);
    }

    return el;
}

function renderLiveTypingIndicator() {
    const el = ensureTypingIndicatorPlacement();
    if (!el) {
        return;
    }

    const names = Object.values(liveTypingUsers)
        .map(function(entry) {
            return String(entry.name || 'Someone').trim();
        })
        .filter(Boolean);

    if (!names.length) {
        el.hidden = true;
        el.style.display = 'none';
        return;
    }

    const copy = names.length === 1
        ? `${names[0]} is typing...`
        : `${names.slice(0, 2).join(', ')} are typing...`;
    el.innerHTML = '<span class="chat-typing-dots"><span></span><span></span><span></span></span><span class="chat-typing-copy"></span>';
    const copyEl = el.querySelector('.chat-typing-copy');
    if (copyEl) {
        copyEl.textContent = copy;
    }
    el.hidden = false;
    el.style.display = 'flex';
    logKodusRealtimeDebug('KODUS typing indicator shown', {
        parent: el.parentElement ? el.parentElement.className : null
    });

    const conversation = el.parentElement;
    if (conversation) {
        conversation.scrollTop = conversation.scrollHeight;
    }
}

function showLiveTypingUser(userId, name) {
    const key = String(userId || name || 'unknown');
    if (liveTypingUsers[key]?.timer) {
        clearTimeout(liveTypingUsers[key].timer);
    }

    liveTypingUsers[key] = {
        name: String(name || 'Someone'),
        timer: setTimeout(function() {
            delete liveTypingUsers[key];
            renderLiveTypingIndicator();
        }, 3000)
    };
    renderLiveTypingIndicator();
    clearTimeout(window.typingTimeout);
    window.typingTimeout = setTimeout(function() {
        const el = document.getElementById('threadTypingIndicator');
        if (el) {
            el.style.display = 'none';
            el.hidden = true;
        }
        liveTypingUsers = {};
    }, 3000);
}

function hideLiveTypingUser(userId) {
    const key = String(userId || 'unknown');
    if (!liveTypingUsers[key]) {
        return;
    }

    if (liveTypingUsers[key].timer) {
        clearTimeout(liveTypingUsers[key].timer);
    }
    delete liveTypingUsers[key];
    renderLiveTypingIndicator();
}

function sendTypingHeartbeat() {
    if (!lastOpenedId || !canReplyInCurrentFolder) {
        return;
    }

    lastTypingMessageId = Number(lastOpenedId);
    $.post('update_typing_status.php', {
        message_id: lastTypingMessageId,
        is_typing: 1,
        csrf_token: window.KODUS_CSRF_TOKEN
    });
}

function scheduleTypingHeartbeat() {
    if (!lastOpenedId || !canReplyInCurrentFolder) {
        return;
    }

    sendTypingHeartbeat();

    const queueTypingHeartbeat = function() {
        typingHeartbeatTimer = setTimeout(function() {
            typingHeartbeatTimer = null;
            if (!lastTypingMessageId || !canReplyInCurrentFolder) {
                return;
            }
            sendTypingHeartbeat();
            queueTypingHeartbeat();
        }, 5000);
    };

    if (!typingHeartbeatTimer) {
        queueTypingHeartbeat();
    }

    if (typingStopTimer) {
        clearTimeout(typingStopTimer);
    }

    typingStopTimer = setTimeout(function() {
        stopTypingHeartbeat();
    }, 6500);
}

function refreshTypingState() {
    const shell = $('#messageDetail .chat-shell');
    if (!shell.length || !lastOpenedId) {
        clearTypingIndicator();
        return;
    }

    $.getJSON('get_typing_state.php', { message_id: lastOpenedId }, function(payload) {
        const typing = Array.isArray(payload?.typing) ? payload.typing : [];
        if (!typing.length) {
            clearTypingIndicator();
            return;
        }

        const names = typing.map(function(entry) {
            return String(entry.username || 'Someone');
        });
        Object.values(liveTypingUsers).forEach(function(entry) {
            if (entry && entry.timer) {
                clearTimeout(entry.timer);
            }
        });
        liveTypingUsers = {};
        const copy = names.length === 1
            ? `${names[0]} is typing...`
            : `${names.slice(0, 2).join(', ')} are typing...`;
        const indicator = ensureTypingIndicatorPlacement();
        if (!indicator) {
            return;
        }
        indicator.innerHTML = '<span class="chat-typing-dots"><span></span><span></span><span></span></span><span class="chat-typing-copy"></span>';
        const copyEl = indicator.querySelector('.chat-typing-copy');
        if (copyEl) {
            copyEl.textContent = copy;
        }
        indicator.hidden = false;
        indicator.style.display = 'flex';
        if (typingRefreshTimeout) {
            clearTimeout(typingRefreshTimeout);
        }
        typingRefreshTimeout = setTimeout(refreshTypingState, 12500);
    });
}

function renderReactionSummary($container, summary) {
    const summaryItems = Array.isArray(summary) ? summary : [];
    const messageId = String($container.data('message-id') || '');
    const replyId = $container.attr('data-reply-id');
    const summaryHtml = summaryItems.map(function(item) {
        const emoji = String(item.emoji || '');
        const count = Number(item.count || 0);
        const active = item.reacted ? ' is-active' : '';
        const reactors = Array.isArray(item.reactors) ? item.reactors.filter(Boolean) : [];
        const details = `${emoji} ${reactors.length ? reactors.join(', ') : 'No reactions yet'}`;
        return `<button type="button" class="chat-reaction-chip${active}" data-emoji="${escapeReplyHtml(emoji)}" data-reaction-details="${escapeReplyHtml(details)}" aria-label="${escapeReplyHtml(details)}"><span class="chat-reaction-emoji">${escapeReplyHtml(emoji)}</span><span class="chat-reaction-count">${count}</span></button>`;
    }).join('');

    $container.attr('data-message-id', messageId);
    if (replyId) {
        $container.attr('data-reply-id', replyId);
    }
    $container.html(`
      <div class="chat-reaction-summary">
        ${summaryHtml}
        <div class="chat-reaction-picker-wrap">
          <button type="button" class="chat-reaction-trigger" aria-label="Add reaction" aria-expanded="false">
            <i class="far fa-smile"></i>
          </button>
          <div class="chat-reaction-picker" data-emoji-picker="reaction" hidden></div>
        </div>
      </div>
	    `);
}

let activeReactionPicker = null;

function logReactionPickerDebug() {
    try {
        if (localStorage.getItem('KODUS_SOCKET_DEBUG') === '1') {
            console.debug.apply(console, ['KODUS reaction picker'].concat(Array.from(arguments)));
        }
    } catch (error) {}
}

function closeReactionPickers(exceptTrigger = null) {
    if (activeReactionPicker && activeReactionPicker.picker) {
        const picker = activeReactionPicker.picker;
        picker.hidden = true;
        picker.style.left = '';
        picker.style.top = '';
        picker.style.width = '';
        if (activeReactionPicker.wrap && activeReactionPicker.wrap.isConnected && picker.parentElement !== activeReactionPicker.wrap) {
            activeReactionPicker.wrap.appendChild(picker);
        }
    }
    activeReactionPicker = null;
    $('.chat-reaction-picker').attr('hidden', true);
    $('.chat-reaction-trigger').each(function() {
        if (!exceptTrigger || this !== exceptTrigger) {
            $(this).attr('aria-expanded', 'false');
        }
    });
}

window.closeReactionPickers = closeReactionPickers;

function findReactionTarget(state) {
    if (!state || !state.messageId) {
        return null;
    }

    const selector = state.replyId
        ? `.chat-reactions[data-message-id="${state.messageId}"][data-reply-id="${state.replyId}"]`
        : `.chat-reactions[data-message-id="${state.messageId}"]:not([data-reply-id])`;
    return document.querySelector(selector);
}

function positionReactionPicker(state) {
    if (!state || !state.picker) {
        return;
    }

    const target = state.reactions && state.reactions.isConnected ? state.reactions : findReactionTarget(state);
    if (!target) {
        logReactionPickerDebug('close: target missing', state);
        closeReactionPickers();
        return;
    }

    state.reactions = target;
    state.trigger = target.querySelector('.chat-reaction-trigger');
    state.wrap = target.querySelector('.chat-reaction-picker-wrap');

    const messageBubble = target.closest('.reply') || target;
    const picker = state.picker;
    const anchorRect = messageBubble.getBoundingClientRect();
    const pickerWidth = Math.min(328, Math.max(220, window.innerWidth - 16));
    const pickerHeight = picker.offsetHeight || 320;
    const gap = 8;
    const minLeft = gap;
    const maxLeft = Math.max(minLeft, window.innerWidth - pickerWidth - gap);
    const minTop = gap;
    const maxTop = Math.max(minTop, window.innerHeight - pickerHeight - gap);
    const isBubbleMode = document.body.classList.contains('kodus-chat-bubble-mode');
    const openLeft = messageBubble.classList.contains('mine');
    let left = openLeft ? anchorRect.left - pickerWidth - gap : anchorRect.right + gap;
    let top = anchorRect.top + Math.max(0, (anchorRect.height - pickerHeight) / 2);

    if (isBubbleMode || left < minLeft || left > maxLeft) {
        left = Math.min(Math.max(anchorRect.left, minLeft), maxLeft);
        top = anchorRect.top - pickerHeight - gap;
    }

    if (top < minTop) {
        top = Math.min(Math.max(anchorRect.top + gap, minTop), maxTop);
    }
    top = Math.min(Math.max(top, minTop), maxTop);

    picker.style.left = `${left}px`;
    picker.style.top = `${top}px`;
    picker.style.width = `${pickerWidth}px`;
    picker.style.zIndex = '2140';
    logReactionPickerDebug('position', { messageId: state.messageId, replyId: state.replyId, left, top, anchorRect });
}

function getOpenReactionPickerState() {
    if (!activeReactionPicker || !activeReactionPicker.messageId) {
        return null;
    }

    return {
        messageId: activeReactionPicker.messageId,
        replyId: activeReactionPicker.replyId
    };
}

function restoreReactionPickerState(state) {
    if (!state || !state.messageId) {
        return;
    }

    const selector = state.replyId
        ? `.chat-reactions[data-message-id="${state.messageId}"][data-reply-id="${state.replyId}"]`
        : `.chat-reactions[data-message-id="${state.messageId}"]:not([data-reply-id])`;
    const $reactions = $('#conversationWrapper').find(selector).first();
    if (!$reactions.length) {
        return;
    }

    openReactionPicker($reactions.find('.chat-reaction-trigger').first()[0]);
}

function openReactionPicker(trigger) {
    if (!trigger) {
        return;
    }

    const reactions = trigger.closest('.chat-reactions');
    const wrap = trigger.closest('.chat-reaction-picker-wrap');
    const picker = wrap ? wrap.querySelector('.chat-reaction-picker') : null;
    if (!reactions || !wrap || !picker) {
        return;
    }

    const messageId = String(reactions.getAttribute('data-message-id') || '');
    if (!messageId) {
        return;
    }

    const replyId = String(reactions.getAttribute('data-reply-id') || '');
    closeReactionPickers(trigger);
    window.KODUSEmojiPicker?.enhance(picker);
    document.body.appendChild(picker);
    picker.hidden = false;
    trigger.setAttribute('aria-expanded', 'true');
    activeReactionPicker = { picker, trigger, wrap, reactions, messageId, replyId };
    positionReactionPicker(activeReactionPicker);
}

function applyThreadSearch(query) {
    const trimmed = String(query || '').trim();
    const status = $('#threadSearchStatus');
    const bodies = $('#messageDetail .chat-bubble-body');
    let matches = 0;

    bodies.each(function() {
        const $body = $(this);
        const plainText = $body.text();
        const escapedText = $('<div>').text(plainText).html();
        const mentionHighlightedText = escapedText.replace(/(^|[\s(])(@(?:everyone|[\p{L}\p{N}_.-]+))/gu, '$1<span class="chat-mention-chip">$2</span>');

        if (!trimmed) {
            $body.html(mentionHighlightedText.replace(/\n/g, '<br>'));
            return;
        }

        const pattern = new RegExp(`(${escapeRegExp(trimmed)})`, 'ig');
        const highlighted = mentionHighlightedText.replace(pattern, '<mark class="chat-search-hit">$1</mark>');
        const count = (plainText.match(new RegExp(escapeRegExp(trimmed), 'ig')) || []).length;
        matches += count;
        $body.html(highlighted.replace(/\n/g, '<br>'));
    });

    if (!trimmed) {
        status.text('No search yet');
    } else if (matches === 0) {
        status.text('No matches found');
    } else {
        status.text(`${matches} match${matches === 1 ? '' : 'es'} found`);
    }
}

function initializeThreadExperience() {
    restoreReplyQuoteDraft();
    const searchValue = ($('#threadSearchInput').val() || '').trim();
    if (searchValue) {
        applyThreadSearch(searchValue);
    } else {
        const status = $('#threadSearchStatus');
        if (status.length) {
            status.text('Search by keyword or phrase');
        }
    }
}

function escapeReplyHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

window.KODUSReplyQuoteDraft = null;

function getActiveReplyQuoteDraft() {
    const targetType = String($('#replyQuoteTargetType').val() || '').trim();
    if (!targetType) {
        return null;
    }

    return {
        targetType: targetType,
        replyId: String($('#replyQuoteReplyId').val() || ''),
        author: $('#replyQuoteAuthor').text().trim() || 'Quoted message',
        excerpt: $('#replyQuoteText').text().trim() || 'Message unavailable'
    };
}

function applyReplyQuoteDraft(quote) {
    if (!quote || !$('#replyForm').length) {
        return;
    }

    window.KODUSReplyQuoteDraft = quote;
    if (typeof window.KODUSApplyReplyQuote === 'function') {
        window.KODUSApplyReplyQuote(quote);
    } else {
        $('#replyQuoteTargetType').val(quote.targetType || '');
        $('#replyQuoteReplyId').val(quote.replyId || '');
        $('#replyQuoteAuthor').text(quote.author || 'Quoted message');
        $('#replyQuoteText').text(quote.excerpt || 'Message unavailable');
        $('#replyQuoteComposer').prop('hidden', !quote.targetType);
    }
}

function clearReplyQuoteDraft() {
    window.KODUSReplyQuoteDraft = null;
    if (typeof window.KODUSClearReplyQuote === 'function') {
        window.KODUSClearReplyQuote();
    } else {
        $('#replyQuoteTargetType, #replyQuoteReplyId').val('');
        $('#replyQuoteAuthor, #replyQuoteText').text('');
        $('#replyQuoteComposer').prop('hidden', true);
    }
}

function restoreReplyQuoteDraft() {
    if (window.KODUSReplyQuoteDraft) {
        applyReplyQuoteDraft(window.KODUSReplyQuoteDraft);
    }
}

function isAttachmentMediaFile(file) {
    const type = typeof file === 'object' && file ? String(file.type || '') : '';
    const name = typeof file === 'string' ? file : String(file?.name || '');
    const ext = (name.split('.').pop() || '').toLowerCase();
    return type.startsWith('image/')
        || type.startsWith('video/')
        || type.startsWith('audio/')
        || ['avif', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'ogv', 'mov', 'm4v', 'mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'].includes(ext);
}

function renderLocalAttachmentPreviewHtml(file) {
    const name = typeof file === 'string' ? file : String(file?.name || 'Attachment');
    const type = typeof file === 'object' && file ? String(file.type || '') : '';
    const ext = (name.split('.').pop() || '').toLowerCase();
    const isMedia = isAttachmentMediaFile(file);
    const url = typeof file === 'object' && file && isMedia ? URL.createObjectURL(file) : '';

    if (type.startsWith('image/') || ['avif', 'jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
        return url
            ? `<img src="${url}" alt="">`
            : '<div class="d-flex align-items-center justify-content-center" style="height:84px;"><i class="fas fa-file-image fa-2x text-secondary"></i></div>';
    }

    if (type.startsWith('video/') || ['mp4', 'webm', 'ogv', 'mov', 'm4v'].includes(ext)) {
        return url
            ? `<video src="${url}" controls preload="metadata"></video>`
            : '<div class="d-flex align-items-center justify-content-center" style="height:84px;"><i class="fas fa-file-video fa-2x text-secondary"></i></div>';
    }

    if (type.startsWith('audio/') || ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'].includes(ext)) {
        return url
            ? `<audio src="${url}" controls preload="metadata"></audio>`
            : '<div class="d-flex align-items-center justify-content-center" style="height:84px;"><i class="fas fa-file-audio fa-2x text-secondary"></i></div>';
    }

    return `<div class="d-flex align-items-center justify-content-center" style="height:84px;"><i class="fas fa-file fa-2x text-secondary"></i></div>
            <div class="filename">${escapeReplyHtml(name)}</div>`;
}

function appendOptimisticReply(replyText, attachments = [], quote = null) {
    const conversation = $('#conversationWrapper .conversation-scroll');
    const attachmentItems = Array.isArray(attachments) ? attachments : [];
    if (!conversation.length || (!replyText && !attachmentItems.length)) {
        return;
    }

    const timeLabel = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    const safeReply = escapeReplyHtml(replyText).replace(/\n/g, '<br>');
    const safeReplyWithMentions = safeReply.replace(/(^|[\s(])(@(?:everyone|[\p{L}\p{N}_.-]+))/gu, '$1<span class="chat-mention-chip">$2</span>');
    const userLabel = $('#mainSidebar .user-panel .info > a').first().text().trim() || 'You';
    const avatarSrc = $('#mainSidebar .user-panel .image img').attr('src') || '../dist/img/default.webp';
    const quoteHtml = quote
        ? `<div class="chat-quote-preview">
             <div class="chat-quote-preview-author">${escapeReplyHtml(quote.author || 'Quoted message')}</div>
             <div class="chat-quote-preview-text">${escapeReplyHtml(quote.excerpt || 'Message unavailable')}</div>
           </div>`
        : '';
    const attachmentHtml = attachmentItems.length
        ? `<div class="attachments optimistic-attachments">
             <div class="attachments-list">
               ${attachmentItems.map(function(file) {
                   return `<div class="attachment-thumb optimistic-attachment-thumb">
                     ${renderLocalAttachmentPreviewHtml(file)}
                   </div>`;
               }).join('')}
             </div>
           </div>`
        : '';

    const optimisticReply = $(`
      <div class="reply mine optimistic-reply" data-optimistic="1">
        <div class="reply-head">
          <img src="${avatarSrc}" alt="${escapeReplyHtml(userLabel)}" class="reply-avatar">
          <div class="reply-meta">
            <strong>${escapeReplyHtml(userLabel)}</strong>
            <span>${timeLabel}</span>
            <span class="text-info optimistic-status">Sending...</span>
          </div>
        </div>
        ${quoteHtml}
        ${safeReply ? `<div class="chat-bubble-body">${safeReplyWithMentions}</div>` : ''}
        ${attachmentHtml}
      </div>
    `);

    conversation.append(optimisticReply);
    conversation.scrollTop(conversation[0].scrollHeight);

    return optimisticReply;
}

function refreshCurrentThread(options = {}) {
    if (!lastOpenedId) {
        return $.Deferred().resolve().promise();
    }

    const previousScrollTop = $('#conversationWrapper .conversation-scroll').scrollTop() || 0;
    return $.get('get_thread.php', { id: lastOpenedId, folder: currentMailboxFolder, bubble: isKodusChatBubbleMode ? 1 : 0 }, function(html) {
        closeReactionPickers();
        $('#messageDetail').html(html);
        updateDetailTitle();
        initializeThreadExperience();
        normalizeBubbleThreadMenu();
        syncVisibleMessengerPresence();
        preserveConversationScrollPosition(previousScrollTop);
    });
}

$(document).on('click', '.message-item', function(event) {
    if ($(event.target).closest('.mailbox-select-cell').length) {
        return;
    }
    openMessage($(this).data('id'));
});

function openReplyEditor(replyId, existingReply) {
    Swal.fire({
        title: 'Edit message',
        input: 'textarea',
        inputValue: existingReply,
        inputAttributes: {
            'aria-label': 'Edit message'
        },
        inputValidator: (value) => {
            if (!String(value || '').trim()) {
                return 'Message text cannot be empty.';
            }
            return undefined;
        },
        showCancelButton: true,
        confirmButtonText: 'Save changes'
    }).then(function(result) {
        if (!result.isConfirmed) {
            return;
        }

        $.ajax({
            url: 'edit_reply.php',
            type: 'POST',
            dataType: 'json',
            data: {
                reply_id: replyId,
                reply: result.value,
                csrf_token: window.KODUS_CSRF_TOKEN
            }
        }).done(function() {
            refreshCurrentThread();
            updateMessageList();
        }).fail(function(xhr) {
            Swal.fire('Unable to edit message', xhr.responseJSON?.error || 'Please try again.', 'error');
        });
    });
}

function confirmReplyDelete(replyId) {
    Swal.fire({
        icon: 'warning',
        title: 'Remove this message?',
        text: 'The message text and attachments will be replaced with a removed notice for everyone in the chat.',
        showCancelButton: true,
        confirmButtonText: 'Remove',
        confirmButtonColor: '#dc3545'
    }).then(function(result) {
        if (!result.isConfirmed) {
            return;
        }

        $.ajax({
            url: 'delete_reply.php',
            type: 'POST',
            dataType: 'json',
            data: {
                reply_id: replyId,
                csrf_token: window.KODUS_CSRF_TOKEN
            }
        }).done(function() {
            refreshCurrentThread();
            updateMessageList();
        }).fail(function(xhr) {
            Swal.fire('Unable to remove message', xhr.responseJSON?.error || 'Please try again.', 'error');
        });
    });
}

function postConversationAction(messageId, action, extraData = null) {
    const formData = extraData instanceof FormData ? extraData : new FormData();
    formData.set('message_id', messageId);
    formData.set('action', action);
    formData.set('csrf_token', window.KODUS_CSRF_TOKEN);

    return fetch('conversation_action.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).then(response => response.json().then(payload => ({ response, payload })))
      .then(({ response, payload }) => {
          if (!response.ok || !payload.success) {
              throw new Error(payload.error || 'Unable to update this conversation.');
          }
          return payload;
      });
}

function resetActiveThreadUi() {
    stopTypingHeartbeat();
    clearTypingIndicator();
    lastOpenedId = null;
    window.KODUSActiveMessengerThreadId = null;
    window.activeThreadId = '';
    renderEmptyDetail();
    setMobileMailboxView('list');
}

function showGroupEditDialog(messageId, currentName) {
    Swal.fire({
        title: 'Group details',
        html: `
            <input id="swalGroupName" class="swal2-input" placeholder="Group name" value="${escapeHtml(currentName || '')}">
            <input id="swalGroupPhoto" class="swal2-file" type="file" accept="image/jpeg,image/png,image/webp">
        `,
        showCancelButton: true,
        confirmButtonText: 'Save',
        preConfirm: () => {
            const name = String(document.getElementById('swalGroupName')?.value || '').trim();
            if (!name) {
                Swal.showValidationMessage('Group name is required.');
                return false;
            }
            const data = new FormData();
            data.set('group_name', name);
            const file = document.getElementById('swalGroupPhoto')?.files?.[0];
            if (file) {
                data.set('group_photo', file);
            }
            return postConversationAction(messageId, 'update_group', data);
        }
    }).then(result => {
        if (result.isConfirmed) {
            refreshCurrentThread();
            updateMessageList();
        }
    }).catch(error => Swal.fire('Not saved', error.message || 'Please try again.', 'error'));
}

function showConversationOptions(trigger) {
    const messageId = Number(trigger.getAttribute('data-message-id') || 0);
    const isGroup = trigger.getAttribute('data-is-group') === '1';
    const isMuted = trigger.getAttribute('data-group-muted') === '1';
    const hasLeft = trigger.getAttribute('data-group-left') === '1';
    const groupName = trigger.getAttribute('data-group-name') || 'Group chat';
    if (!messageId) {
        return;
    }

    const buttons = [
        '<button type="button" class="swal2-confirm swal2-styled" id="conversationDeleteBtn">Delete conversation</button>'
    ];

    if (isGroup && !hasLeft) {
        buttons.unshift(`<button type="button" class="swal2-confirm swal2-styled" id="conversationMuteBtn">${isMuted ? 'Unmute group' : 'Mute group'}</button>`);
        buttons.unshift('<button type="button" class="swal2-confirm swal2-styled" id="conversationEditGroupBtn">Edit group</button>');
        buttons.push('<button type="button" class="swal2-deny swal2-styled" id="conversationLeaveBtn" style="display:inline-block;background:#dc3545;">Leave group</button>');
    }
    buttons.push('<button type="button" class="swal2-cancel swal2-styled" id="conversationCancelBtn">Cancel</button>');

    Swal.fire({
        title: 'Conversation options',
        html: `<div class="d-flex flex-wrap justify-content-center" style="gap:0.5rem;">${buttons.join('')}</div>`,
        showConfirmButton: false,
        didOpen: () => {
            const popup = Swal.getPopup();
            popup.querySelector('#conversationCancelBtn')?.addEventListener('click', () => Swal.close());
            popup.querySelector('#conversationEditGroupBtn')?.addEventListener('click', () => {
                Swal.close();
                showGroupEditDialog(messageId, groupName);
            });
            popup.querySelector('#conversationMuteBtn')?.addEventListener('click', () => {
                Swal.close();
                postConversationAction(messageId, isMuted ? 'unmute' : 'mute')
                    .then(() => refreshCurrentThread())
                    .catch(error => Swal.fire('Action failed', error.message, 'error'));
            });
            popup.querySelector('#conversationLeaveBtn')?.addEventListener('click', () => {
                Swal.close();
                Swal.fire({
                    icon: 'warning',
                    title: 'Leave this group?',
                    text: 'You will stop receiving new group messages.',
                    showCancelButton: true,
                    confirmButtonText: 'Leave group',
                    confirmButtonColor: '#dc3545'
                }).then(result => {
                    if (!result.isConfirmed) return;
                    postConversationAction(messageId, 'leave')
                        .then(() => {
                            refreshCurrentThread();
                            updateMessageList();
                        })
                        .catch(error => Swal.fire('Unable to leave', error.message, 'error'));
                });
            });
            popup.querySelector('#conversationDeleteBtn')?.addEventListener('click', () => {
                Swal.close();
                Swal.fire({
                    icon: 'warning',
                    title: 'Delete conversation?',
                    text: isGroup ? 'This removes the group from your chat list only.' : 'This hides the conversation for you only. The other person keeps their copy.',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    confirmButtonColor: '#dc3545'
                }).then(result => {
                    if (!result.isConfirmed) return;
                    postConversationAction(messageId, 'delete')
                        .then(() => {
                            $(`#messageList .message-item[data-id="${messageId}"]`).remove();
                            resetActiveThreadUi();
                            updateMessageList();
                            updateUnreadCount();
                        })
                        .catch(error => Swal.fire('Unable to delete', error.message, 'error'));
                });
            });
        }
    });
}

$(document).on('click', '.conversation-options-trigger', function(e) {
    e.stopPropagation();
    closeReplyMenus();
    closeReactionPickers();
});

function getConversationMenuTrigger(element) {
    return $(element).closest('.chat-thread-options').find('.conversation-options-trigger').get(0);
}

function hideConversationDropdown(element) {
    const $dropdown = $(element).closest('.dropdown');
    const $trigger = $dropdown.find('[data-toggle="dropdown"]').first();
    if ($trigger.length && typeof $trigger.dropdown === 'function') {
        $trigger.dropdown('hide');
    }
}

function showAddMemberDialog(messageId) {
    const options = $('#groupComposeMembers option').map(function() {
        return {
            value: this.value,
            label: $(this).text().trim()
        };
    }).get().filter(option => option.value && option.label);

    if (!options.length) {
        Swal.fire('No members available', 'There are no users available to add right now.', 'info');
        return;
    }

    const optionsHtml = options.map(option => (
        `<option value="${escapeReplyHtml(option.value)}">${escapeReplyHtml(option.label)}</option>`
    )).join('');

    Swal.fire({
        title: 'Add member',
        html: `<select id="swalGroupAddMembers" class="swal2-select" multiple style="min-height:12rem;width:100%;">${optionsHtml}</select>`,
        showCancelButton: true,
        confirmButtonText: 'Add member',
        didOpen: () => {
            const select = $('#swalGroupAddMembers');
            if (window.jQuery && $.fn.select2) {
                select.select2({
                    theme: 'bootstrap4',
                    width: '100%',
                    dropdownParent: $(Swal.getPopup()),
                    placeholder: 'Choose members'
                });
            }
        },
        preConfirm: () => {
            const select = document.getElementById('swalGroupAddMembers');
            const selected = Array.from(select?.selectedOptions || []).map(option => option.value);
            if (!selected.length) {
                Swal.showValidationMessage('Choose at least one member.');
                return false;
            }
            const data = new FormData();
            selected.forEach(value => data.append('members[]', value));
            return postConversationAction(messageId, 'add_member', data);
        }
    }).then(result => {
        if (result.isConfirmed) {
            refreshGroupMembersModal(messageId);
            refreshCurrentThread();
            updateMessageList();
        }
    }).catch(error => Swal.fire('Member not added', error.message || 'Please try again.', 'error'));
}

let activeGroupMembersMessageId = null;

function groupStatusBadgeClass(status) {
    const normalized = String(status || '').toLowerCase();
    if (normalized === 'left') {
        return 'badge-secondary';
    }
    if (normalized === 'muted') {
        return 'badge-warning';
    }
    if (normalized.startsWith('last active')) {
        return 'badge-warning';
    }
    return 'badge-success';
}

function renderGroupMembers(payload) {
    const members = Array.isArray(payload?.members) ? payload.members : [];
    const list = $('#groupMembersList');
    $('#groupMembersModalLabel').text(payload?.group_name || 'Group members');
    $('#groupMembersModalMeta').text(`${members.length} member${members.length === 1 ? '' : 's'} in this group chat`);
    $('#groupMembersAddBtn').prop('hidden', !payload?.can_add_member).data('message-id', payload?.message_id || activeGroupMembersMessageId || 0);

    if (!members.length) {
        list.html('<div class="text-center text-muted py-4">No members found.</div>');
        return;
    }

    list.html(members.map(function(member) {
        const roles = Array.isArray(member.roles) ? member.roles : [];
        const roleBadges = roles.map(role => `<span class="badge badge-info">${escapeReplyHtml(role)}</span>`).join('');
        const status = String(member.status || 'Active');
        const removeButton = member.can_remove
            ? `<button type="button" class="btn btn-outline-danger btn-sm group-member-remove" data-user-id="${Number(member.user_id || 0)}"><i class="fas fa-user-minus mr-1"></i> Remove</button>`
            : '';
        return `
          <div class="group-member-row">
            <img src="${escapeReplyHtml(member.avatar_url || '../dist/img/default.webp')}" alt="${escapeReplyHtml(member.name || 'Member')}" class="group-member-avatar">
            <div class="group-member-main">
              <div class="group-member-name">${escapeReplyHtml(member.name || 'Member')}${member.is_self ? ' <span class="text-muted font-weight-normal">(You)</span>' : ''}</div>
              <div class="group-member-email">${escapeReplyHtml(member.email || '')}</div>
              <div class="group-member-badges">
                ${roleBadges}
                <span class="badge ${groupStatusBadgeClass(status)}">${escapeReplyHtml(status)}</span>
              </div>
            </div>
            <div class="group-member-actions">${removeButton}</div>
          </div>
        `;
    }).join(''));
}

function refreshGroupMembersModal(messageId = null) {
    const targetId = Number(messageId || activeGroupMembersMessageId || 0);
    if (!targetId) {
        return;
    }

    activeGroupMembersMessageId = targetId;
    $('#groupMembersList').html('<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin mr-1"></i> Loading members...</div>');

    $.getJSON('get_group_members.php', { message_id: targetId })
        .done(renderGroupMembers)
        .fail(function(xhr) {
            $('#groupMembersList').html(`<div class="alert alert-danger mb-0">${escapeReplyHtml(xhr.responseJSON?.error || 'Unable to load group members.')}</div>`);
            $('#groupMembersAddBtn').prop('hidden', true);
        });
}

function openGroupMembersModal(messageId) {
    if (!messageId) {
        return;
    }

    activeGroupMembersMessageId = Number(messageId);
    $('#groupMembersModal').modal('show');
    refreshGroupMembersModal(activeGroupMembersMessageId);
}

$(document).on('click', '#messengerComposeMenu', function(e) {
    if (window.jQuery && typeof $(this).dropdown === 'function') {
        return;
    }

    if (window.bootstrap && window.bootstrap.Dropdown) {
        e.preventDefault();
        const dropdown = window.bootstrap.Dropdown.getInstance(this) || new window.bootstrap.Dropdown(this);
        dropdown.toggle();
    }
});

function removeGroupMember(messageId, memberId) {
    if (!messageId || !memberId) {
        return;
    }

    Swal.fire({
        icon: 'warning',
        title: 'Remove member?',
        text: 'This marks the member as left for this group chat.',
        showCancelButton: true,
        confirmButtonText: 'Remove',
        confirmButtonColor: '#dc3545'
    }).then(result => {
        if (!result.isConfirmed) {
            return;
        }
        const data = new FormData();
        data.set('member_id', memberId);
        postConversationAction(messageId, 'remove_member', data)
            .then(() => {
                refreshGroupMembersModal(messageId);
                refreshCurrentThread();
                updateMessageList();
            })
            .catch(error => Swal.fire('Unable to remove member', error.message || 'Please try again.', 'error'));
    });
}

$(document).on('click', '.conversation-see-members-trigger', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const trigger = getConversationMenuTrigger(this);
    hideConversationDropdown(this);
    const messageId = Number(trigger?.getAttribute('data-message-id') || 0);
    openGroupMembersModal(messageId);
});

$(document).on('click', '#groupMembersAddBtn', function(e) {
    e.preventDefault();
    const messageId = Number($(this).data('message-id') || activeGroupMembersMessageId || 0);
    if (messageId) {
        showAddMemberDialog(messageId);
    }
});

$(document).on('click', '#groupMembersModal [data-dismiss="modal"], #groupMembersModal [data-bs-dismiss="modal"]', function(e) {
    e.preventDefault();
    const modalElement = document.getElementById('groupMembersModal');
    if (!modalElement) {
        return;
    }

    if (window.jQuery && typeof $('#groupMembersModal').modal === 'function') {
        $('#groupMembersModal').modal('hide');
        return;
    }

    if (window.bootstrap && window.bootstrap.Modal) {
        const modalInstance = window.bootstrap.Modal.getInstance(modalElement) || new window.bootstrap.Modal(modalElement);
        modalInstance.hide();
    }
});

$(document).on('click', '#groupComposeModal [data-dismiss="modal"], #groupComposeModal [data-bs-dismiss="modal"]', function(e) {
    e.preventDefault();
    if (window.jQuery && typeof $('#groupComposeModal').modal === 'function') {
        $('#groupComposeModal').modal('hide');
        return;
    }

    const modalElement = document.getElementById('groupComposeModal');
    if (modalElement && window.bootstrap && window.bootstrap.Modal) {
        const modalInstance = window.bootstrap.Modal.getInstance(modalElement) || new window.bootstrap.Modal(modalElement);
        modalInstance.hide();
    }
});

$(document).on('click', '.group-member-remove', function(e) {
    e.preventDefault();
    const memberId = Number($(this).data('user-id') || 0);
    removeGroupMember(Number(activeGroupMembersMessageId || 0), memberId);
});

$('#groupMembersModal').on('hidden.bs.modal', function() {
    activeGroupMembersMessageId = null;
});

$(document).on('click', '.conversation-add-member-trigger', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const trigger = getConversationMenuTrigger(this);
    hideConversationDropdown(this);
    const messageId = Number(trigger?.getAttribute('data-message-id') || 0);
    if (messageId) {
        showAddMemberDialog(messageId);
    }
});

$(document).on('click', '.conversation-edit-group-trigger', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const trigger = getConversationMenuTrigger(this);
    hideConversationDropdown(this);
    const messageId = Number(trigger?.getAttribute('data-message-id') || 0);
    if (messageId) {
        showGroupEditDialog(messageId, trigger?.getAttribute('data-group-name') || 'Group chat');
    }
});

$(document).on('click', '.conversation-mute-trigger', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const trigger = getConversationMenuTrigger(this);
    hideConversationDropdown(this);
    const messageId = Number(trigger?.getAttribute('data-message-id') || 0);
    const isMuted = trigger?.getAttribute('data-group-muted') === '1';
    if (!messageId) {
        return;
    }
    postConversationAction(messageId, isMuted ? 'unmute' : 'mute')
        .then(() => refreshCurrentThread())
        .catch(error => Swal.fire('Action failed', error.message, 'error'));
});

$(document).on('click', '.conversation-leave-trigger', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const trigger = getConversationMenuTrigger(this);
    hideConversationDropdown(this);
    const messageId = Number(trigger?.getAttribute('data-message-id') || 0);
    if (!messageId) {
        return;
    }
    Swal.fire({
        icon: 'warning',
        title: 'Leave this group?',
        text: 'You will stop receiving new group messages.',
        showCancelButton: true,
        confirmButtonText: 'Leave group',
        confirmButtonColor: '#dc3545'
    }).then(result => {
        if (!result.isConfirmed) return;
        postConversationAction(messageId, 'leave')
            .then(() => {
                refreshCurrentThread();
                updateMessageList();
            })
            .catch(error => Swal.fire('Unable to leave', error.message, 'error'));
    });
});

$(document).on('click', '.conversation-delete-trigger', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const trigger = getConversationMenuTrigger(this);
    hideConversationDropdown(this);
    const messageId = Number(trigger?.getAttribute('data-message-id') || 0);
    const isGroup = trigger?.getAttribute('data-is-group') === '1';
    if (!messageId) {
        return;
    }
    Swal.fire({
        icon: 'warning',
        title: 'Delete conversation?',
        text: isGroup ? 'This removes the group from your chat list only.' : 'This hides the conversation for you only. The other person keeps their copy.',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#dc3545'
    }).then(result => {
        if (!result.isConfirmed) return;
        postConversationAction(messageId, 'delete')
            .then(() => {
                $(`#messageList .message-item[data-id="${messageId}"]`).remove();
                resetActiveThreadUi();
                updateMessageList();
                updateUnreadCount();
            })
            .catch(error => Swal.fire('Unable to delete', error.message, 'error'));
    });
});

function closeReplyMenus(exceptButton = null) {
    $('.reply-menu-dropdown').attr('hidden', true);
    $('.reply-menu-trigger').each(function() {
        if (!exceptButton || this !== exceptButton) {
            $(this).attr('aria-expanded', 'false');
        }
    });
}

function isMobileReplyActions() {
    return window.matchMedia('(max-width: 767.98px)').matches;
}

function openReplyActions(replyId, existingReply, canEdit, canDelete) {
    if (!replyId || (!canEdit && !canDelete)) {
        return;
    }

    const actions = [];
    if (canEdit) {
        actions.push('<button type="button" class="swal2-confirm swal2-styled" id="replyActionEditBtn" style="display:inline-block;">Edit</button>');
    }
    if (canDelete) {
        actions.push('<button type="button" class="swal2-deny swal2-styled" id="replyActionDeleteBtn" style="display:inline-block;background:#dc3545;">Remove</button>');
    }
    actions.push('<button type="button" class="swal2-cancel swal2-styled" id="replyActionCancelBtn" style="display:inline-block;">Cancel</button>');

    Swal.fire({
        title: 'Message actions',
        html: `<div class="d-flex flex-wrap justify-content-center" style="gap:0.5rem;">${actions.join('')}</div>`,
        showConfirmButton: false,
        showCancelButton: false,
        didOpen: () => {
            const popup = Swal.getPopup();
            popup.querySelector('#replyActionEditBtn')?.addEventListener('click', function() {
                Swal.close();
                openReplyEditor(replyId, existingReply);
            });
            popup.querySelector('#replyActionDeleteBtn')?.addEventListener('click', function() {
                Swal.close();
                confirmReplyDelete(replyId);
            });
            popup.querySelector('#replyActionCancelBtn')?.addEventListener('click', function() {
                Swal.close();
            });
        }
    });
}

$(document).on('click', '.reply-quote-trigger', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const reply = $(this).closest('.reply');
    const quote = {
        targetType: String(reply.attr('data-quote-target-type') || ''),
        replyId: String(reply.attr('data-quote-reply-id') || ''),
        author: String(reply.attr('data-quote-author') || 'Quoted message'),
        excerpt: String(reply.attr('data-quote-excerpt') || 'Message unavailable')
    };

    if (!quote.targetType || !$('#replyForm').length) {
        return;
    }

    closeReplyMenus();
    applyReplyQuoteDraft(quote);
    const composer = document.getElementById('replyForm');
    composer?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    $('#replyText').trigger('focus');
});

$(document).on('click', '.reply-quote-clear', function(e) {
    e.preventDefault();
    clearReplyQuoteDraft();
    $('#replyText').trigger('focus');
});

$(document).on('click', '.reply-menu-trigger', function(e) {
    e.preventDefault();
    e.stopPropagation();

    const $trigger = $(this);
    const $menu = $trigger.siblings('.reply-menu-dropdown');
    const willOpen = $menu.is('[hidden]');

    closeReplyMenus(this);

    if (willOpen) {
        $menu.removeAttr('hidden');
        $trigger.attr('aria-expanded', 'true');
    }
});

let replyLongPressTimer = null;
let replyLongPressFired = false;

$(document).on('touchstart', '.reply[data-can-edit="1"], .reply[data-can-delete="1"]', function(e) {
    if (!isMobileReplyActions()) {
        return;
    }

    const touchTarget = e.target;
    if ($(touchTarget).closest('.attachment-thumb, .reply-menu-dropdown, .reply-menu-trigger, a, button, input, textarea, form').length) {
        return;
    }

    const $reply = $(this);
    const replyId = Number($reply.data('reply-id') || 0);
    const existingReply = String($reply.attr('data-reply-text') || '');
    const canEdit = String($reply.attr('data-can-edit') || '0') === '1';
    const canDelete = String($reply.attr('data-can-delete') || '0') === '1';

    replyLongPressFired = false;
    clearTimeout(replyLongPressTimer);
    replyLongPressTimer = setTimeout(function() {
        replyLongPressFired = true;
        openReplyActions(replyId, existingReply, canEdit, canDelete);
    }, 450);
});

$(document).on('touchend touchcancel touchmove', '.reply[data-can-edit="1"], .reply[data-can-delete="1"]', function() {
    clearTimeout(replyLongPressTimer);
});

$(document).on('contextmenu', '.reply[data-can-edit="1"], .reply[data-can-delete="1"]', function(e) {
    if (!isMobileReplyActions()) {
        return;
    }

    e.preventDefault();
    const $reply = $(this);
    openReplyActions(
        Number($reply.data('reply-id') || 0),
        String($reply.attr('data-reply-text') || ''),
        String($reply.attr('data-can-edit') || '0') === '1',
        String($reply.attr('data-can-delete') || '0') === '1'
    );
});

$(document).on('click', function() {
    closeReplyMenus();
    closeReactionPickers();
});

$(document).on('click', '.reply-menu-dropdown', function(e) {
    e.stopPropagation();
});

$(document).on('click', '.reply-edit-trigger', function() {
    const replyId = Number($(this).data('reply-id') || 0);
    const existingReply = String($(this).data('reply-text') || '');
    closeReplyMenus();

    if (!replyId) {
        return;
    }

    openReplyEditor(replyId, existingReply);
});

$(document).on('click', '.reply-delete-trigger', function() {
    const replyId = Number($(this).data('reply-id') || 0);
    closeReplyMenus();

    if (!replyId) {
        return;
    }

    confirmReplyDelete(replyId);
});

$(document).on('input', '#replyText', function() {
    scheduleTypingHeartbeat();
});

$(document).on('input', '#threadSearchInput', function() {
    applyThreadSearch($(this).val());
});

$(document).on('click', '.chat-reaction-trigger', function(event) {
    event.preventDefault();
    event.stopPropagation();

    const trigger = this;
    const isOpen = activeReactionPicker && activeReactionPicker.trigger === trigger && !activeReactionPicker.picker.hidden;

    closeReactionPickers(trigger);

    if (!isOpen) {
        openReactionPicker(trigger);
    }
});

$(document).on('click', '.chat-reaction-picker', function(event) {
    event.stopPropagation();
});

function repositionOpenReactionPicker() {
    if (!activeReactionPicker || !activeReactionPicker.picker || activeReactionPicker.picker.hidden) {
        return;
    }
    positionReactionPicker(activeReactionPicker);
}

$(window).on('resize scroll', repositionOpenReactionPicker);
$(document).on('scroll', '.conversation-scroll', repositionOpenReactionPicker);

$(document).on('click', '.chat-reaction-count', function(event) {
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
    const details = $(this).closest('.chat-reaction-chip').attr('data-reaction-details') || '';
    if (details) {
        Swal.fire({
            title: 'Reactions',
            text: details,
            confirmButtonText: 'Close'
        });
    }
});

$(document).on('click', '.chat-reaction-chip, .chat-reaction-add', function(event) {
    event.preventDefault();
    event.stopPropagation();
    const $button = $(this);
    const isPickerChoice = $button.hasClass('chat-reaction-add');
    const reactionsNode = isPickerChoice && activeReactionPicker
        ? activeReactionPicker.reactions
        : $button.closest('.chat-reactions')[0];
    const $reactions = $(reactionsNode);
    const emoji = String($button.data('emoji') || '');
    const messageId = Number($reactions.data('message-id') || 0);
    const replyIdRaw = $reactions.attr('data-reply-id');
    const replyId = replyIdRaw ? Number(replyIdRaw) : null;

    if (!emoji || !messageId) {
        return;
    }

    if (isPickerChoice) {
        closeReactionPickers();
    }

    $button.prop('disabled', true);

    $.ajax({
        url: 'toggle_reaction.php',
        type: 'POST',
        dataType: 'json',
        data: {
            message_id: messageId,
            reply_id: replyId,
            emoji: emoji,
            csrf_token: window.KODUS_CSRF_TOKEN
        }
    }).done(function(response) {
        renderReactionSummary($reactions, response.summary || []);
    }).fail(function(xhr) {
        Swal.fire('Unable to react', xhr.responseJSON?.error || 'Please try again.', 'error');
    }).always(function() {
        $button.prop('disabled', false);
    });
});

$(document).on('click', '#mobileBackToList', function() {
    stopTypingHeartbeat();
    clearTypingIndicator();
    window.KODUSActiveMessengerThreadId = null;
    window.activeThreadId = '';
    setMobileMailboxView('list');
});

window.addEventListener('resize', syncMessengerViewport, { passive: true });
window.addEventListener('orientationchange', syncMessengerViewport, { passive: true });

$(document).on('submit', '#replyForm', function(e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(this);
    const replyText = ($(this).find('#replyText').val() || '').trim();
    const replyMailCsrfToken = $(this).find('input[name="csrf_token"]').val() || window.KODUS_CSRF_TOKEN || '';
    const outgoingFiles = Array.from($(this).find('input[type="file"]')[0]?.files || []);
    const quoteDraft = getActiveReplyQuoteDraft();
    const attachmentError = this.querySelector('#replyAttachmentError');
    const validation = validateMessengerAttachments(outgoingFiles);
    if (!validation.valid) {
        setAttachmentValidationMessage(attachmentError, validation.message);
        Swal.fire({
            icon: 'warning',
            title: 'Attachment too large',
            text: validation.message
        });
        return;
    }
    setAttachmentValidationMessage(attachmentError, '');
    const optimisticReply = appendOptimisticReply(replyText, outgoingFiles, quoteDraft);
    const submitButton = $(this).find('[type="submit"]');
    submitButton.prop('disabled', true);

    $.ajax({
        url: 'send_reply.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(resp) {
            if (resp.status === 'success') {
                stopTypingHeartbeat();
                if (typeof window.KODUSResetReplyComposer === 'function') {
                    window.KODUSResetReplyComposer();
                } else {
                    form.reset();
                    form.dataset.mentionedUserIds = '';
                    $('#replyFilePreview').empty();
                    $('#mentionedUserIds').val('');
                    clearReplyQuoteDraft();
                    $('#replyText').trigger('input').focus();
                }
                closeReactionPickers();
                optimisticReply?.find('.optimistic-status')
                    .removeClass('text-info text-danger')
                    .addClass('text-success')
                    .text('Sent');

                if (resp.reply_id) {
                    $.ajax({
                        url: 'send_reply_mail.php',
                        type: 'POST',
                        data: {
                            reply_id: resp.reply_id,
                            csrf_token: replyMailCsrfToken
                        }
                    }).fail(function(xhr) {
                        logKodusRealtimeDebug('Reply mail background send failed.', xhr.responseText || xhr.statusText);
                    });
                }

                updateMessageList();

                if (lastOpenedId) {
                    window.setTimeout(function() {
                        $.get('get_thread.php', { id: lastOpenedId, folder: currentMailboxFolder, bubble: isKodusChatBubbleMode ? 1 : 0 }, function(html) {
                            closeReactionPickers();
                            $('#messageDetail').html(html);
                            $(`#messageList .message-item[data-id="${lastOpenedId}"]`).addClass('active').removeClass('unread');
                            $(`#messageList .message-item[data-id="${lastOpenedId}"]`).attr('data-unread', '0');
                            initializeThreadExperience();
                            normalizeBubbleThreadMenu();
                            scrollConversationToBottomOnOpen();
                            $('#replyText').trigger('input').focus();
                        });
                    }, 350);
                }
            } else {
                optimisticReply?.find('.optimistic-status')
                    .removeClass('text-info text-success')
                    .addClass('text-danger')
                    .text('Failed');
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: resp.message || 'Failed to send reply'
                });
            }
        },
        error: function(xhr) {
            optimisticReply?.find('.optimistic-status')
                .removeClass('text-info text-success')
                .addClass('text-danger')
                .text('Failed');
            Swal.fire({
                icon: 'error',
                title: 'Server Error',
                text: messengerUploadErrorMessage(null, xhr)
            });
        },
        complete: function() {
            submitButton.prop('disabled', false);
        }
    });
});

function refreshConversationIfChanged(options = {}) {
    if (!lastOpenedId) {
        return;
    }

    if (!canReplyInCurrentFolder) {
        return;
    }

    const draft = $('#replyText').val() || '';
    const previousMarkup = getComparableConversationMarkupFromHtml($('#conversationWrapper').prop('outerHTML') || '');
    const openReactionPicker = getOpenReactionPickerState();
    const conv = $('#conversationWrapper .conversation-scroll');
    const previousScrollTop = conv.length ? conv.scrollTop() : 0;
    const shouldScrollToBottom = !!options.scrollToBottom || isConversationNearBottom();

    $.get('get_thread.php', { id: lastOpenedId, only_conversation: 1, folder: currentMailboxFolder }, function(html) {
        if (getComparableConversationMarkupFromHtml(html) === previousMarkup) {
            if ($('#replyText').length) {
                $('#replyText').val(draft);
            }
            restoreReactionPickerState(openReactionPicker);
            return;
        }

        if ($('#conversationWrapper').length) {
            closeReactionPickers();
            $('#conversationWrapper').replaceWith(html);
        } else {
            closeReactionPickers();
            $('#messageDetail').append(html);
        }
        if (Object.keys(liveTypingUsers).length > 0) {
            renderLiveTypingIndicator();
        }
        syncVisibleMessengerPresence();

        if ($('#replyText').length) {
            $('#replyText').val(draft);
        }

        const threadSearchValue = ($('#threadSearchInput').val() || '').trim();
        if (threadSearchValue) {
            applyThreadSearch(threadSearchValue);
        }
        if (shouldScrollToBottom) {
            scrollConversationToBottomOnOpen();
        } else {
            preserveConversationScrollPosition(previousScrollTop);
        }
        restoreReactionPickerState(openReactionPicker);
        repositionOpenEmojiMenus();
        firstLoadDone = true;
    });
}

if (window.KODUSLiveRefresh && typeof window.KODUSLiveRefresh.watchSocket === 'function') {
    window.KODUSLiveRefresh.watchSocket({
        key: 'inbox-presence',
        channel: 'kodus.presence',
        events: ['presence.changed'],
        onMessage: function(payload) {
            const data = payload && payload.data ? payload.data : {};
            applyMessengerPresence(data.user_id, data.online || data.status === 'online', data.last_active_at || data.last_active || data.at);
        }
    });

    if (typeof window.KODUSLiveRefresh.connectSocket === 'function' && currentMessengerUserId) {
        window.KODUSLiveRefresh.connectSocket().then(function(socket) {
            if (!socket || socket.__kodusMessengerPresenceBound) {
                return;
            }

            socket.__kodusMessengerPresenceBound = true;
            const announcePresence = function() {
                socket.emit('presence.join', { user_id: currentMessengerUserId });
            };
            socket.on('connect', announcePresence);
            if (socket.connected) {
                announcePresence();
            }
        });
    }

    window.KODUSLiveRefresh.watchSocket({
        key: 'inbox-mailbox',
        channel: 'kodus.mailbox',
        events: ['mail.changed', 'mail.typing'],
        onMessage: function(payload) {
            const data = payload && payload.data ? payload.data : {};
            const action = String(data.action || '');
            const messageId = Number(data.message_id || 0);
            const actorId = Number(data.actor_id || data.user_id || 0);

            if (payload && payload.event === 'mail.typing') {
                const threadId = String(data.thread_id || data.conversation_id || messageId || '');
                const senderId = String(data.sender_id || data.actor_id || data.user_id || '');
                const receiverIds = Array.isArray(data.receiver_ids)
                    ? data.receiver_ids.map(String)
                    : [String(data.receiver_id || '')].filter(Boolean);
                const currentUserId = String(window.currentUserId || currentMessengerUserId || '');
                const activeThreadId = String(window.activeThreadId || lastOpenedId || '');
                const isReceiver = receiverIds.includes(currentUserId);
                const isSameThread = threadId === activeThreadId;

                logKodusRealtimeDebug('KODUS mail.typing match', {
                    thread_id: threadId,
                    active_thread_id: activeThreadId,
                    is_receiver: isReceiver
                });

                if (!threadId || !isSameThread || !isReceiver || senderId === currentUserId) {
                    return;
                }

                if (action === 'typing_stopped') {
                    hideLiveTypingUser(senderId);
                } else {
                    showLiveTypingUser(senderId, data.sender_name || data.actor_name || 'User');
                }
                return;
            }

            if ((action === 'reply_created' || action === 'message_created') && messageId && messageId === Number(lastOpenedId || 0)) {
                clearTypingIndicator();
            }

            updateMessageList();
            const groupMembershipActions = ['group_member_added', 'group_member_removed', 'group_left', 'group_muted', 'group_unmuted', 'group_updated'];
            if (groupMembershipActions.includes(action) && messageId && messageId === Number(activeGroupMembersMessageId || 0) && $('#groupMembersModal').hasClass('show')) {
                refreshGroupMembersModal(messageId);
            }

            if (groupMembershipActions.includes(action) && messageId && messageId === Number(lastOpenedId || 0)) {
                refreshCurrentThread();
            } else if ((action === 'reply_created' || action === 'message_created' || action === 'reaction_toggled') && messageId && messageId === Number(lastOpenedId || 0)) {
                refreshConversationIfChanged({ scrollToBottom: isConversationNearBottom() });
                if (action === 'reply_created' || action === 'message_created') {
                    $.post('mark_read.php', { id: messageId, csrf_token: window.KODUS_CSRF_TOKEN }, function() {
                        updateUnreadCount();
                        updateMailboxSummary();
                    });
                }
            }
            updateUnreadCount();
        }
    });
}

$('#mailboxSearch').on('input', applyMessageFilters);

$('#mailboxBulkAction').on('change', syncBulkSelectionUi);

$(document).on('change', '#mailboxSelectAll', function() {
    const shouldCheck = $(this).is(':checked');
    $('#messageList .message-item:visible .mailbox-message-select').prop('checked', shouldCheck);
    syncBulkSelectionUi();
});

$(document).on('change', '.mailbox-message-select', function() {
    syncBulkSelectionUi();
});

$(document).on('click', '.mailbox-message-select, .mailbox-row-select', function(event) {
    event.stopPropagation();
});

$('#mailboxBulkApply').on('click', handleBulkAction);

$(document).on('click', '.mailbox-filter', function() {
    $('.mailbox-filter').removeClass('active');
    $(this).addClass('active');
    applyMessageFilters();
});

function buildFolderEmptyHtml() {
    const title = currentMailboxFolder === 'trash' ? 'Archive is empty' : 'No chats yet';
    const body = currentMailboxFolder === 'trash'
        ? 'Chats you remove from your main list will stay here.'
        : 'New conversations will show up here.';

    return `
      <div class="mailbox-empty">
        <img src="<?php echo app_url('dist/img/empty-inbox.png'); ?>" alt="Empty Inbox">
        <p class="mb-1"><strong>${title}</strong></p>
        <p class="mb-0">${body}</p>
      </div>
    `;
}

function removeMessageFromCurrentView(messageId, scope = 'self') {
    const $row = $(`#messageList .message-item[data-id="${messageId}"]`);
    const wasUnread = $row.attr('data-unread') === '1';
    if ($row.length) {
        $row.remove();
    }

    if (String(lastOpenedId) === String(messageId)) {
        lastOpenedId = null;
        renderEmptyDetail();
        setMobileMailboxView('list');
    }

    if ($('#messageList .message-item').length === 0) {
        $('#messageList').html(buildFolderEmptyHtml());
    } else {
        $('#messageList .table-responsive').show();
        $('#mailboxNoResults').hide();
    }

    updateMailboxSummary();
    updateUnreadCount();

    if (scope === 'self' && currentMailboxFolder === 'inbox') {
        const nextTrashCount = Number($('#sidebarTrashBadge').text() || 0) + 1;
        $('#sidebarTrashBadge').text(nextTrashCount);
    } else if (scope === 'restore' && currentMailboxFolder === 'trash') {
        const nextTrashCount = Math.max(0, Number($('#sidebarTrashBadge').text() || 0) - 1);
        $('#sidebarTrashBadge').text(nextTrashCount);
    } else if (scope === 'everyone' && currentMailboxFolder === 'trash') {
        const nextTrashCount = Math.max(0, Number($('#sidebarTrashBadge').text() || 0) - 1);
        $('#sidebarTrashBadge').text(nextTrashCount);
    }

    if (wasUnread) {
        updateUnreadCount();
    }
}

function moveConversation(messageId, scope) {
    return $.ajax({
        url: 'delete_message.php',
        type: 'POST',
        dataType: 'json',
        data: {
            id: messageId,
            scope: scope,
            csrf_token: window.KODUS_CSRF_TOKEN
        }
    });
}

function updateMessageReadState(messageId) {
    const $row = $(`#messageList .message-item[data-id="${messageId}"]`);
    if (!$row.length) {
        return;
    }

    $row.attr('data-unread', '0').removeClass('unread');
    $row.find('.mailbox-star i').removeClass('text-warning').addClass('text-muted');
}

let mailboxBulkActionInFlight = false;

function handleBulkAction() {
    if (mailboxBulkActionInFlight) {
        return;
    }

    const action = String($('#mailboxBulkAction').val() || '').trim();
    const messageIds = getSelectedMessageIds();

    if (!action) {
        Swal.fire('Select an action', 'Choose a bulk action before applying it.', 'warning');
        return;
    }

    if (messageIds.length === 0) {
        Swal.fire('No chats selected', 'Select one or more chats first.', 'warning');
        return;
    }

    let actionLabel = 'update';
    let successLabel = 'updated';
    if (action === 'delete') {
        actionLabel = 'archive';
        successLabel = 'archived';
    } else if (action === 'delete_permanent') {
        actionLabel = 'delete forever';
        successLabel = 'deleted forever';
    } else if (action === 'restore') {
        actionLabel = 'restore';
        successLabel = 'restored';
    } else if (action === 'mark_read') {
        actionLabel = 'mark as seen';
        successLabel = 'marked as seen';
    }

    Swal.fire({
        icon: 'question',
        title: 'Apply chat action?',
        text: `This will ${actionLabel} ${messageIds.length} selected chat${messageIds.length === 1 ? '' : 's'}.`,
        showCancelButton: true,
        confirmButtonText: 'Apply'
    }).then(function(result) {
        if (!result.isConfirmed) {
            return;
        }

        const messageCount = messageIds.length;
        let loaderTitle = 'Updating chats...';
        if (action === 'delete') {
            loaderTitle = `Archiving ${messageCount} chat${messageCount === 1 ? '' : 's'}...`;
        } else if (action === 'delete_permanent') {
            loaderTitle = `Deleting ${messageCount} chat${messageCount === 1 ? '' : 's'} forever...`;
        } else if (action === 'restore') {
            loaderTitle = `Restoring ${messageCount} chat${messageCount === 1 ? '' : 's'}...`;
        } else if (action === 'mark_read') {
            loaderTitle = `Marking ${messageCount} chat${messageCount === 1 ? '' : 's'} as seen...`;
        }

        mailboxBulkActionInFlight = true;
        $('#mailboxBulkApply').prop('disabled', true);
        $('#mailboxBulkAction').prop('disabled', true);
        $('#mailboxSelectAll').prop('disabled', true);
        $('#messageList .mailbox-message-select').prop('disabled', true);

        Swal.fire({
            title: loaderTitle,
            html: `
                <div class="text-center text-muted small mb-2">Please wait while KODUS updates the selected chats.</div>
                <div class="progress" style="height: 0.75rem;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 100%"></div>
                </div>
            `,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function() {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: 'bulk_actions.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: action,
                'message_ids[]': messageIds,
                csrf_token: window.KODUS_CSRF_TOKEN
            }
        }).done(function(response) {
            const processedIds = Array.isArray(response.processed_ids) ? response.processed_ids : [];

            if (action === 'delete') {
                processedIds.forEach(function(messageId) {
                    removeMessageFromCurrentView(messageId, 'self');
                });
            } else if (action === 'restore') {
                processedIds.forEach(function(messageId) {
                    removeMessageFromCurrentView(messageId, 'restore');
                });
            } else if (action === 'delete_permanent') {
                processedIds.forEach(function(messageId) {
                    removeMessageFromCurrentView(messageId, 'everyone');
                });
            } else {
                processedIds.forEach(function(messageId) {
                    updateMessageReadState(messageId);
                });
                updateUnreadCount();
                updateMailboxSummary();
                applyMessageFilters();
            }

            mailboxBulkActionInFlight = false;
            $('#mailboxBulkAction').val('');
            $('#mailboxBulkAction').prop('disabled', false);
            $('#mailboxSelectAll').prop('disabled', false);
            $('#messageList .mailbox-message-select').prop('disabled', false);
            $('#mailboxSelectAll').prop('checked', false).prop('indeterminate', false);
            syncBulkSelectionUi();

            Swal.fire({
                icon: response.success ? 'success' : 'warning',
                title: 'Chat action complete',
                text: response.message || (`Selected chats were ${successLabel}.`)
            });
        }).fail(function(xhr) {
            mailboxBulkActionInFlight = false;
            $('#mailboxBulkAction').prop('disabled', false);
            $('#mailboxSelectAll').prop('disabled', false);
            $('#messageList .mailbox-message-select').prop('disabled', false);
            Swal.fire('Unable to apply chat action', xhr.responseJSON?.error || 'Please try again.', 'error');
            syncBulkSelectionUi();
        });
    });
}

$(document).on('click', '.mailbox-delete-trigger', function() {
    const messageId = Number($(this).data('id') || 0);
    const canDeleteEveryone = String($(this).data('can-delete-everyone') || '0') === '1';

    if (!messageId) {
        return;
    }

    const isTrashView = currentMailboxFolder === 'trash';
    const buttons = isTrashView
        ? []
        : ['<button type="button" class="swal2-confirm swal2-styled" id="mailDeleteSelfBtn" style="display:inline-block;background:#6c757d;">Archive chat</button>'];

    if (canDeleteEveryone) {
        buttons.push('<button type="button" class="swal2-deny swal2-styled" id="mailDeleteEveryoneBtn" style="display:inline-block;background:#dc3545;">Delete for everyone</button>');
    }

    buttons.push('<button type="button" class="swal2-cancel swal2-styled" id="mailDeleteCancelBtn" style="display:inline-block;">Cancel</button>');

    Swal.fire({
        icon: 'warning',
        title: isTrashView ? 'Delete this chat forever?' : 'Manage this chat',
        html: `
          <div class="text-left">
            <p class="mb-2">${isTrashView ? 'This chat is already in your archive.' : 'Choose how you want to handle this chat.'}</p>
            <p class="small text-muted mb-0">${isTrashView ? 'Deleting for everyone permanently removes the chat and its attachments for all participants.' : 'Archive chat removes it from your main list. Delete for everyone permanently removes it for all participants.'}</p>
          </div>
          <div class="mt-3 d-flex flex-wrap justify-content-center" style="gap:0.5rem;">
            ${buttons.join('')}
          </div>
        `,
        showConfirmButton: false,
        showCancelButton: false,
        didOpen: () => {
            const popup = Swal.getPopup();

            popup.querySelector('#mailDeleteSelfBtn')?.addEventListener('click', function() {
                Swal.close();
                moveConversation(messageId, 'self').done(function() {
                    removeMessageFromCurrentView(messageId, 'self');
                }).fail(function(xhr) {
                    Swal.fire('Unable to archive chat', xhr.responseJSON?.error || 'Please try again.', 'error');
                });
            });

            popup.querySelector('#mailDeleteEveryoneBtn')?.addEventListener('click', function() {
                Swal.fire({
                    icon: 'warning',
                    title: 'Delete for everyone?',
                    text: 'This permanently removes the chat and its attachments for all participants.',
                    showCancelButton: true,
                    confirmButtonText: 'Delete forever',
                    confirmButtonColor: '#dc3545'
                }).then(function(result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    moveConversation(messageId, 'everyone').done(function() {
                        removeMessageFromCurrentView(messageId, 'everyone');
                    }).fail(function(xhr) {
                        Swal.fire('Unable to delete chat', xhr.responseJSON?.error || 'Please try again.', 'error');
                    });
                });
            });

            popup.querySelector('#mailDeleteCancelBtn')?.addEventListener('click', function() {
                Swal.close();
            });
        }
    });
});

$(document).on('click', '.conversation-open-bubble-trigger', function() {
    const messageId = Number($(this).closest('.chat-shell').data('message-id') || lastOpenedId || 0);
    if (!messageId || typeof window.openKodusChatBubbleFromUrl !== 'function') {
        return;
    }

    const title = $('#mailboxDetailTitle').text().trim() || $('.chat-thread-header .mailbox-read-subject').text().trim() || 'Messenger';
    window.openKodusChatBubbleFromUrl(`${messengerBaseUrl}?msg=${encodeURIComponent(messageId)}`, title);
});

$(document).on('click', '.conversation-open-messenger-trigger', function() {
    const messageId = Number($(this).closest('.chat-shell').data('message-id') || lastOpenedId || 0);
    if (!messageId) {
        return;
    }

    try {
        localStorage.removeItem('kodus.chatBubble.state');
    } catch (error) {}

    const targetUrl = `${messengerBaseUrl}?msg=${encodeURIComponent(messageId)}`;
    if (window.top && window.top !== window) {
        window.top.location.href = targetUrl;
    } else {
        window.location.href = targetUrl;
    }
});

$(document).on('click', '.mailbox-restore-trigger', function() {
    const messageId = Number($(this).data('id') || 0);
    if (!messageId) {
        return;
    }

    moveConversation(messageId, 'restore').done(function() {
        removeMessageFromCurrentView(messageId, 'restore');
    }).fail(function(xhr) {
        Swal.fire('Unable to restore chat', xhr.responseJSON?.error || 'Please try again.', 'error');
    });
});

(function() {
    function safeParseAttachments(raw) {
        if (!raw) return [];
        if (Array.isArray(raw)) return raw;
        try {
            return JSON.parse(raw);
        } catch (e) {
            return [];
        }
    }

    function openCarousel(attachments, startIndex, type) {
        if (!Array.isArray(attachments) || attachments.length === 0) {
            return;
        }

        let idx = Math.max(0, Math.min(startIndex || 0, attachments.length - 1));
        const basePath = '<?php echo app_url('inbox/uploads/'); ?>';
        const folder = type === 'reply' ? 'reply_attachments/' : 'contact_attachments/';
        const attachmentsBase = basePath + folder;
        const imageExts = ['avif', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
        const videoExts = ['mp4', 'webm', 'ogv', 'mov', 'm4v'];
        const audioExts = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'];

        function escapeCarouselHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        if (window._swalKeyHandler) {
            window.removeEventListener('keydown', window._swalKeyHandler, true);
            window._swalKeyHandler = null;
        }

        function renderPreviewHtml(file, index, total) {
            const ext = (file.split('.').pop() || '').toLowerCase();
            const path = attachmentsBase + encodeURIComponent(file);
            const safeName = escapeCarouselHtml(file);
            const hasPrevious = index > 0;
            const hasNext = index < total - 1;
            let preview = `<div class="text-center p-4"><i class="fas fa-file fa-3x mb-3 text-secondary"></i><div class="font-weight-bold">${safeName}</div><div class="text-muted small">Preview unavailable</div></div>`;

            if (imageExts.includes(ext)) {
                preview = `<img src="${path}" alt="${safeName}">`;
            } else if (videoExts.includes(ext)) {
                preview = `<video src="${path}" controls preload="metadata"></video>`;
            } else if (audioExts.includes(ext)) {
                preview = `<div class="text-center p-4 w-100"><i class="fas fa-file-audio fa-3x mb-3 text-secondary"></i><audio src="${path}" controls preload="metadata" style="width:min(520px,100%);"></audio></div>`;
            } else if (ext === 'pdf') {
                preview = `<iframe src="${path}" title="${safeName}" style="width:100%;height:100%;border:none;"></iframe>`;
            }

            return `
              <div class="attachment-preview-stage">
                <button id="swalPrev" type="button" class="attachment-preview-nav attachment-preview-nav--prev" aria-label="Previous attachment"${hasPrevious ? '' : ' hidden disabled'}>&lt;</button>
                <div class="attachment-preview-media">${preview}</div>
                <button id="swalNext" type="button" class="attachment-preview-nav attachment-preview-nav--next" aria-label="Next attachment"${hasNext ? '' : ' hidden disabled'}>&gt;</button>
              </div>
            `;
        }

        function bindButtons() {
            const prev = document.getElementById('swalPrev');
            const next = document.getElementById('swalNext');
            const download = document.getElementById('swalDownloadAttachment');
            const currentFile = attachments[idx] || '';
            const currentPath = attachmentsBase + encodeURIComponent(currentFile);

            if (prev) prev.onclick = () => updateContent(idx - 1);
            if (next) next.onclick = () => updateContent(idx + 1);
            if (download) {
                download.href = currentPath;
                download.setAttribute('download', currentFile);
            }
        }

        function updateContent(newIndex) {
            if (newIndex < 0 || newIndex >= attachments.length) {
                return;
            }
            idx = newIndex;
            Swal.update({ html: renderPreviewHtml(attachments[idx], idx, attachments.length) });
            bindButtons();
        }

        window._swalKeyHandler = function(e) {
            if (e.key === 'ArrowLeft' && idx > 0) {
                e.preventDefault();
                updateContent(idx - 1);
            } else if (e.key === 'ArrowRight' && idx < attachments.length - 1) {
                e.preventDefault();
                updateContent(idx + 1);
            } else if (e.key === 'Escape' && Swal.isVisible()) {
                Swal.close();
            }
        };

        Swal.fire({
            html: renderPreviewHtml(attachments[idx], idx, attachments.length),
            showConfirmButton: false,
            showCloseButton: true,
            width: 'min(90vw, 1100px)',
            heightAuto: false,
            customClass: {
                popup: 'attachment-preview-popup'
            },
            allowOutsideClick: true,
            didOpen: function() {
                const closeButton = Swal.getCloseButton();
                if (closeButton && !document.getElementById('swalDownloadAttachment')) {
                    const downloadButton = document.createElement('a');
                    downloadButton.id = 'swalDownloadAttachment';
                    downloadButton.className = 'swal2-close';
                    downloadButton.style.right = '2.6em';
                    downloadButton.style.textDecoration = 'none';
                    downloadButton.setAttribute('aria-label', 'Download attachment');
                    downloadButton.setAttribute('title', 'Download');
                    downloadButton.innerHTML = '<i class="fas fa-download" aria-hidden="true"></i>';
                    closeButton.parentElement?.appendChild(downloadButton);
                }
                bindButtons();
                window.addEventListener('keydown', window._swalKeyHandler, true);
            },
            willClose: function() {
                window.removeEventListener('keydown', window._swalKeyHandler, true);
                window._swalKeyHandler = null;
            }
        });
    }

    $(document).on('click', '.attachment-thumb', function(event) {
        if ($(event.target).closest('video, audio').length) {
            return;
        }

        const attachments = safeParseAttachments($(this).attr('data-attachments'));
        const idx = parseInt($(this).attr('data-index'), 10) || 0;
        const type = $(this).data('type') || 'contact';
        openCarousel(attachments, idx, type);
    });
})();

$(function() {
    syncMessengerViewport();
    updateMailboxSummary();
    updateRefreshLabel();
    updateDetailTitle();
    applyMessageFilters();

    const params = new URLSearchParams(window.location.search);
    const requestedId = params.get('msg') || params.get('id');
    if (requestedId) {
        openMessage(requestedId);
    } else if (isMobileMailboxView()) {
        setMobileMailboxView('list');
    } else {
        setMobileMailboxView('list');
    }

$(window).on('resize', function() {
    syncMessengerViewport();
    setMobileMailboxView(lastOpenedId ? 'detail' : 'list');
});

$(window).on('beforeunload pagehide', function() {
    stopTypingHeartbeat();
});

    const composeModal = $('#composeModal');
    const composeForm = document.getElementById('composeForm');
    const composeModalClose = document.getElementById('composeModalClose');
    const composeRecipient = document.getElementById('composeRecipient');
    const composeSubject = document.getElementById('composeSubject');
    const composeMessage = document.getElementById('composeMessage');
    const composeAttachments = document.getElementById('composeAttachments');
    const composeAttachTrigger = document.getElementById('composeAttachTrigger');
    const composeMessageShell = document.querySelector('.compose-message-shell');
    const composeFilePreview = document.getElementById('composeFilePreview');
    const composeFileSummary = document.getElementById('composeFileSummary');
    const composeAttachmentError = document.getElementById('composeAttachmentError');
    const composeRecipientSummary = document.getElementById('composeRecipientSummary');
    const composeSubjectCount = document.getElementById('composeSubjectCount');
    const composeMessageCount = document.getElementById('composeMessageCount');
    const composeResetBtn = document.getElementById('composeResetBtn');
    const composeEmojiTrigger = document.getElementById('composeEmojiTrigger');
    const composeEmojiMenu = document.getElementById('composeEmojiMenu');
    const composeMentionPreview = document.getElementById('composeMentionPreview');
    const composeSubmitBtns = composeForm ? Array.from(composeForm.querySelectorAll('[type="submit"]')) : [];
    let selectedComposeFiles = [];
    const composePreviewUrls = new Map();

    function openComposeModal() {
        composeModal.modal('show');
        setTimeout(function() {
            if ($('#composeRecipient').data('select2')) {
                $('#composeRecipient').select2('open');
            } else {
                composeSubject?.focus();
            }
        }, 180);
    }

    function closeComposeEmojiMenu() {
        window.KODUSEmojiPicker?.close(composeEmojiTrigger, composeEmojiMenu);
    }

    function positionComposeEmojiMenu() {
        window.KODUSEmojiPicker?.position(composeEmojiTrigger, composeEmojiMenu);
    }

    function handleComposeEmojiDocumentClick(event) {
        if (!composeEmojiMenu || !composeEmojiTrigger) {
            return;
        }
        if (!composeEmojiMenu.contains(event.target) && event.target !== composeEmojiTrigger && !composeEmojiTrigger.contains(event.target)) {
            closeComposeEmojiMenu();
        }
    }

    function insertComposeTextAtCursor(field, text) {
        if (!field) {
            return;
        }
        const start = field.selectionStart ?? field.value.length;
        const end = field.selectionEnd ?? field.value.length;
        field.value = field.value.slice(0, start) + text + field.value.slice(end);
        field.focus();
        const cursor = start + text.length;
        field.setSelectionRange(cursor, cursor);
        field.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function renderComposerMentionPreview(value) {
        return escapeReplyHtml(value)
            .replace(/(^|[\s(])(@(?:everyone|[\p{L}\p{N}_.-]+))/gu, '$1<span class="composer-mention-chip">$2</span>')
            .replace(/\n/g, '<br>');
    }

    function syncComposeMentionPreview() {
        if (!composeMentionPreview || !composeMessage) {
            return;
        }
        const hasText = composeMessage.value.length > 0;
        composeMessageShell?.classList.toggle('has-mention-preview-text', hasText);
        composeMentionPreview.innerHTML = hasText ? renderComposerMentionPreview(composeMessage.value) : '';
        composeMentionPreview.scrollTop = composeMessage.scrollTop;
    }

    function buildComposeEmojiMenu() {
        if (!composeEmojiMenu || !composeMessage || composeEmojiMenu.childElementCount > 0) {
            return;
        }
        window.KODUSEmojiPicker?.render(composeEmojiMenu, function(emoji) {
            insertComposeTextAtCursor(composeMessage, emoji);
            closeComposeEmojiMenu();
        });
    }

    function clearComposeFiles() {
        selectedComposeFiles = [];
        composePreviewUrls.forEach(function(url) {
            URL.revokeObjectURL(url);
        });
        composePreviewUrls.clear();
        if (composeAttachments) {
            composeAttachments.value = '';
        }
        if (composeFilePreview) {
            composeFilePreview.innerHTML = '';
            composeFilePreview.classList.remove('is-stacked');
        }
        composeMessageShell?.classList.remove('has-file-preview');
        if (composeFileSummary) {
            composeFileSummary.textContent = 'No files selected yet.';
        }
        setAttachmentValidationMessage(composeAttachmentError, '');
    }

    function syncComposeFileInput() {
        if (!composeAttachments) {
            return;
        }
        const transfer = new DataTransfer();
        selectedComposeFiles.forEach(function(file) {
            transfer.items.add(file);
        });
        composeAttachments.files = transfer.files;
    }

    function getComposePreviewUrl(file) {
        const key = file.name + ':' + file.size + ':' + file.lastModified;
        if (!composePreviewUrls.has(key)) {
            composePreviewUrls.set(key, URL.createObjectURL(file));
        }
        return composePreviewUrls.get(key);
    }

    function renderComposeAttachments() {
        if (!composeFilePreview || !composeFileSummary) {
            return;
        }

        composeFilePreview.innerHTML = '';
        const files = selectedComposeFiles;
        composeMessageShell?.classList.toggle('has-file-preview', files.length > 0);
        if (!files.length) {
            clearComposeFiles();
            return;
        }

        const totalSize = files.reduce((sum, file) => sum + Number(file.size || 0), 0);
        composeFileSummary.textContent = files.length === 1
            ? `${isAttachmentMediaFile(files[0]) ? 'Media attachment' : files[0].name} (${formatAttachmentBytes(files[0].size)})`
            : `${files.length} files selected (${formatAttachmentBytes(totalSize)} total)`;

        const isStacked = files.length > 4;
        const visibleFiles = isStacked ? files.slice(0, 4) : files;
        composeFilePreview.classList.toggle('is-stacked', isStacked);

        visibleFiles.forEach(function(file, index) {
            const card = document.createElement('div');
            card.className = 'compose-file-card';
            card.style.zIndex = String(visibleFiles.length - index + 1);
            const ext = (file.name.split('.').pop() || '').toLowerCase();

            if (file.type.startsWith('image/') || ['avif', 'webp', 'jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
                const img = document.createElement('img');
                img.src = getComposePreviewUrl(file);
                img.alt = '';
                card.appendChild(img);
            } else if (file.type.startsWith('video/') || ['mp4', 'webm', 'ogv', 'mov', 'm4v'].includes(ext)) {
                const video = document.createElement('video');
                video.src = getComposePreviewUrl(file);
                video.controls = true;
                video.preload = 'metadata';
                card.appendChild(video);
            } else if (file.type.startsWith('audio/') || ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'].includes(ext)) {
                const audio = document.createElement('audio');
                audio.src = getComposePreviewUrl(file);
                audio.controls = true;
                audio.preload = 'metadata';
                card.appendChild(audio);
            } else {
                const iconWrap = document.createElement('span');
                iconWrap.className = 'compose-file-icon';
                const icon = document.createElement('i');
                icon.className = file.type.startsWith('audio/')
                    ? 'fas fa-file-audio'
                    : (file.type.startsWith('video/') ? 'fas fa-file-video' : 'fas fa-file');
                iconWrap.appendChild(icon);
                card.appendChild(iconWrap);
            }

            if (!isAttachmentMediaFile(file)) {
                const label = document.createElement('span');
                label.className = 'compose-file-name';
                label.textContent = file.name;
                card.appendChild(label);
            }

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'compose-file-remove';
            remove.setAttribute('aria-label', 'Remove ' + file.name);
            remove.textContent = '×';
            remove.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                selectedComposeFiles.splice(index, 1);
                composePreviewUrls.forEach(function(url) {
                    URL.revokeObjectURL(url);
                });
                composePreviewUrls.clear();
                syncComposeFileInput();
                renderComposeAttachments();
                setAttachmentValidationMessage(composeAttachmentError, '');
                composeMessage?.focus();
            });
            card.appendChild(remove);

            composeFilePreview.appendChild(card);
        });

        if (isStacked) {
            const count = document.createElement('div');
            count.className = 'compose-file-count';
            count.textContent = '+' + (files.length - visibleFiles.length);
            count.title = files.length + ' attachments selected';
            composeFilePreview.appendChild(count);
        }
    }

    function updateComposeAttachments() {
        if (!composeAttachments || !composeFilePreview || !composeFileSummary) {
            return;
        }

        const nextFiles = selectedComposeFiles.concat(Array.prototype.slice.call(composeAttachments.files || []));
        const validation = validateMessengerAttachments(nextFiles);
        if (!validation.valid) {
            composeAttachments.value = '';
            setAttachmentValidationMessage(composeAttachmentError, validation.message);
            Swal.fire({
                icon: 'warning',
                title: 'Attachment too large',
                text: validation.message
            });
            return;
        }

        selectedComposeFiles = nextFiles;
        setAttachmentValidationMessage(composeAttachmentError, '');
        syncComposeFileInput();
        renderComposeAttachments();
        composeMessage?.focus();
    }

    function renderComposeRecipientOption(option) {
        if (!option.id) {
            return option.text;
        }

        const data = option.element ? option.element.dataset : {};
        const avatar = data.avatar || '<?php echo app_url('dist/img/default.webp'); ?>';
        const name = data.name || option.text;
        const meta = data.kind === 'user' ? (data.presence || 'Offline') : 'Group';
        return $(`
            <span class="compose-recipient-option">
              <img src="${avatar}" alt="" class="compose-recipient-avatar" width="18" height="18">
              <span class="compose-recipient-copy">
                <span class="compose-recipient-name">${name}</span>
                <span class="compose-recipient-meta">${meta}</span>
              </span>
            </span>
        `);
    }

    function renderComposeRecipientSelection(option) {
        if (!option.id) {
            return option.text;
        }

        const data = option.element ? option.element.dataset : {};
        const avatar = data.avatar || '<?php echo app_url('dist/img/default.webp'); ?>';
        const name = data.name || option.text;
        return $(`
            <span class="compose-recipient-chip">
              <img src="${avatar}" alt="" class="compose-recipient-avatar" width="14" height="14">
              <span class="compose-recipient-name">${name}</span>
            </span>
        `);
    }

    function updateComposeRecipientMeta() {
        if (!composeRecipient) {
            return;
        }

        const selectedOptions = Array.prototype.slice.call(composeRecipient.selectedOptions || []);
        if (!selectedOptions.length) {
            composeRecipientSummary.textContent = 'Choose one or more people';
            return;
        }

        const labels = selectedOptions.map(option => option.text);
        composeRecipientSummary.textContent = labels.length === 1
            ? labels[0]
            : `${labels.length} people selected`;
    }

    function buildComposeSubject() {
        const selectedOptions = Array.prototype.slice.call(composeRecipient?.selectedOptions || []);
        const labels = selectedOptions.map(option => option.text).filter(Boolean);
        const messageText = String(composeMessage?.value || '').trim().replace(/\s+/g, ' ');

        if (messageText) {
            return messageText.length > 80 ? (messageText.slice(0, 77) + '...') : messageText;
        }

        if (labels.length === 1) {
            return `Chat with ${labels[0]}`;
        }

        if (labels.length > 1) {
            return `Group chat with ${labels.length} people`;
        }

        return 'New chat';
    }

    function updateComposePreview() {
        if (!composeMessage) {
            return;
        }

        if (composeSubject) {
            composeSubject.value = buildComposeSubject();
        }
        composeMessageCount.textContent = String((composeMessage.value || '').length);
        syncComposeMentionPreview();
    }

    function resetComposeForm() {
        if (!composeForm) {
            return;
        }
        composeForm.reset();
        if (window.jQuery && $.fn.select2 && composeRecipient) {
            $('#composeRecipient').val(null).trigger('change');
        }
        clearComposeFiles();
        updateComposeRecipientMeta();
        updateComposePreview();
        closeComposeEmojiMenu();
        composeMessage?.focus();
    }

    function appendOptimisticComposeThread(labels, messageText, attachments = []) {
        const list = document.querySelector('#messageList tbody');
        if (!list) {
            return null;
        }

        const tempId = `compose-${Date.now()}`;
        const displayName = labels.length ? labels.join(', ') : 'New chat';
        const attachmentItems = Array.isArray(attachments) ? attachments : [];
        const preview = messageText
            ? `You: ${messageText}`
            : (attachmentItems.length ? `You sent ${attachmentItems.length === 1 ? (isAttachmentMediaFile(attachmentItems[0]) ? 'a media attachment' : attachmentItems[0].name) : attachmentItems.length + ' attachments'}` : 'You sent an attachment');
        const row = document.createElement('tr');
        row.className = 'message-item active optimistic-compose-thread';
        row.dataset.id = tempId;
        row.dataset.unread = '0';
        row.dataset.hasAttachment = '0';
        row.innerHTML = `
          <td class="mailbox-avatar-cell text-center">
            <span class="mailbox-avatar-wrap">
              <img src="<?php echo app_url('dist/img/default.webp'); ?>" alt="" class="mailbox-avatar">
              <span class="mailbox-presence-dot mailbox-presence-offline" aria-hidden="true"></span>
            </span>
          </td>
          <td class="mailbox-name">${escapeReplyHtml(displayName)}</td>
          <td class="mailbox-subject"><span class="mailbox-snippet">${escapeReplyHtml(preview)}</span> <span class="text-info optimistic-status">Sending...</span></td>
          <td class="mailbox-date">Now</td>
        `;

        document.querySelectorAll('#messageList .message-item').forEach(item => item.classList.remove('active'));
        list.prepend(row);
        updateMailboxSummary();
        return row;
    }

    function setComposeSubmitting(isSubmitting) {
        if (!composeSubmitBtns.length) {
            return;
        }

        composeSubmitBtns.forEach(function(button) {
            button.disabled = !!isSubmitting;
            if (button.id === 'composeInlineSendBtn') {
                button.innerHTML = isSubmitting
                    ? '<i class="fas fa-spinner fa-spin"></i>'
                    : '<i class="fas fa-paper-plane"></i>';
                return;
            }

            button.innerHTML = isSubmitting
                ? '<i class="fas fa-spinner fa-spin mr-1"></i> Sending...'
                : '<i class="fas fa-paper-plane mr-1"></i> Start chat';
        });
    }

    $(document).on('click', '.mailbox-compose-trigger', function(e) {
        e.preventDefault();
        openComposeModal();
    });

    composeRecipient?.addEventListener('change', function() {
        updateComposeRecipientMeta();
        updateComposePreview();
    });
    composeMessage?.addEventListener('input', updateComposePreview);
    composeMessage?.addEventListener('scroll', syncComposeMentionPreview, { passive: true });
    composeAttachments?.addEventListener('change', updateComposeAttachments);
    composeAttachTrigger?.addEventListener('click', function(e) {
        e.preventDefault();
        composeAttachments?.click();
    });
    composeResetBtn?.addEventListener('click', resetComposeForm);
    composeEmojiTrigger?.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        buildComposeEmojiMenu();
        window.KODUSEmojiPicker?.toggle(composeEmojiTrigger, composeEmojiMenu);
    });
    composeEmojiTrigger?.addEventListener('mousedown', function(e) {
        e.preventDefault();
        e.stopPropagation();
    });
    composeEmojiMenu?.addEventListener('click', function(e) {
        e.stopPropagation();
    });
    composeEmojiMenu?.addEventListener('mousedown', function(e) {
        e.stopPropagation();
    });
    composeMessage?.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeComposeEmojiMenu();
            return;
        }

        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            closeComposeEmojiMenu();
            composeForm?.requestSubmit();
        }
    });
    document.addEventListener('click', handleComposeEmojiDocumentClick);
    composeModalClose?.addEventListener('click', function(e) {
        e.preventDefault();
        closeComposeEmojiMenu();
        composeModal.modal('hide');
    });
    composeForm?.addEventListener('submit', function(e) {
        e.preventDefault();

        if (!composeForm) {
            return;
        }

        const selectedLabels = Array.prototype.slice.call(composeRecipient?.selectedOptions || []).map(option => option.text).filter(Boolean);
        const outgoingMessage = String(composeMessage?.value || '').trim().replace(/\s+/g, ' ');
        const outgoingFiles = selectedComposeFiles.slice();
        const validation = validateMessengerAttachments(outgoingFiles);
        if (!validation.valid) {
            setAttachmentValidationMessage(composeAttachmentError, validation.message);
            Swal.fire({
                icon: 'warning',
                title: 'Attachment too large',
                text: validation.message
            });
            return;
        }
        const optimisticRow = appendOptimisticComposeThread(selectedLabels, outgoingMessage, outgoingFiles);

        setComposeSubmitting(true);
        closeComposeEmojiMenu();
        if (composeSubject) {
            composeSubject.value = buildComposeSubject();
        }
        const formData = new FormData(composeForm);
        composeModal.modal('hide');
        resetComposeForm();

        fetch(composeForm.action, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(async function(response) {
            const text = await response.text();
            let payload = {};

            try {
                payload = text ? JSON.parse(text) : {};
            } catch (error) {
                const parseError = new Error(text || ('HTTP ' + response.status));
                parseError.status = response.status;
                throw parseError;
            }

            if (!response.ok || payload.success === false) {
                const responseError = new Error(payload.message || payload.error || ('HTTP ' + response.status));
                responseError.status = response.status;
                throw responseError;
            }

            return payload;
        })
        .then(function(payload) {
            optimisticRow?.querySelector('.optimistic-status')?.classList.remove('text-info', 'text-danger');
            optimisticRow?.querySelector('.optimistic-status')?.classList.add('text-success');
            if (optimisticRow?.querySelector('.optimistic-status')) {
                optimisticRow.querySelector('.optimistic-status').textContent = 'Sent';
            }
            updateMessageList();
            updateUnreadCount();
            updateMailboxSummary();

            if (payload.message_id) {
                openMessage(payload.message_id);
                window.setTimeout(function() {
                    $('#replyText').trigger('input').focus();
                }, 450);
            }
        })
        .catch(function(error) {
            optimisticRow?.querySelector('.optimistic-status')?.classList.remove('text-info', 'text-success');
            optimisticRow?.querySelector('.optimistic-status')?.classList.add('text-danger');
            if (optimisticRow?.querySelector('.optimistic-status')) {
                optimisticRow.querySelector('.optimistic-status').textContent = 'Failed';
            }
            updateMailboxSummary();
            Swal.fire({
                icon: 'error',
                title: 'Chat failed',
                text: messengerUploadErrorMessage(error)
            });
        })
        .finally(function() {
            setComposeSubmitting(false);
        });
    });
    composeModal.on('hidden.bs.modal', function() {
        closeComposeEmojiMenu();
        setComposeSubmitting(false);
    });

    window.addEventListener('resize', function() {
        if (composeEmojiMenu && !composeEmojiMenu.hidden) {
            positionComposeEmojiMenu();
        }
    }, { passive: true });

    if (window.jQuery && $.fn.select2 && composeRecipient) {
        $('#composeRecipient').select2({
            theme: 'bootstrap4',
            width: '100%',
            dropdownParent: composeModal,
            placeholder: 'Choose people',
            templateResult: renderComposeRecipientOption,
            templateSelection: renderComposeRecipientSelection,
            escapeMarkup: function(markup) { return markup; }
        });
        $('#composeRecipient').on('change', updateComposeRecipientMeta);
    }

    const groupComposeModal = $('#groupComposeModal');
    const groupComposeForm = document.getElementById('groupComposeForm');
    const groupComposeMembers = document.getElementById('groupComposeMembers');

    $(document).on('click', '.group-compose-trigger', function(e) {
        e.preventDefault();
        groupComposeModal.modal('show');
    });

    if (window.jQuery && $.fn.select2 && groupComposeMembers) {
        $('#groupComposeMembers').select2({
            theme: 'bootstrap4',
            width: '100%',
            dropdownParent: groupComposeModal,
            placeholder: 'Choose at least 2 members',
            templateResult: renderComposeRecipientOption,
            templateSelection: renderComposeRecipientSelection,
            escapeMarkup: function(markup) { return markup; }
        });
    }

    groupComposeForm?.addEventListener('submit', function(e) {
        e.preventDefault();
        const selectedCount = Array.from(groupComposeMembers?.selectedOptions || []).length;
        if (selectedCount < 2) {
            Swal.fire('More members needed', 'Select at least 2 members for a group chat.', 'warning');
            return;
        }

        fetch(groupComposeForm.action, {
            method: 'POST',
            body: new FormData(groupComposeForm),
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(response => response.json().then(payload => ({ response, payload })))
        .then(({ response, payload }) => {
            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Could not create the group chat.');
            }
            groupComposeModal.modal('hide');
            groupComposeForm.reset();
            $('#groupComposeMembers').val(null).trigger('change');
            updateMessageList();
            if (payload.message_id) {
                openMessage(payload.message_id);
            }
        })
        .catch(error => {
            Swal.fire('Group not created', error.message || 'Please try again.', 'error');
        });
    });

    updateComposeRecipientMeta();
    updateComposePreview();
    clearComposeFiles();

    if (shouldOpenCompose) {
        openComposeModal();
    }
});
</script>
</body>
</html>
