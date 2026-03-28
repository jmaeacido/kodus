<?php
include('../header.php');
include('../sidenav.php');
include('../config.php');
require_once __DIR__ . '/mailbox_helpers.php';

mailboxEnsureSchema($conn);

function mailbox_user_avatar_url(?string $picture, string $baseUrl): string
{
    $defaultAvatar = $baseUrl . 'kodus/dist/img/default.webp';
    $picture = (string) $picture;

    if ($picture === '') {
        return $defaultAvatar;
    }

    $filePath = __DIR__ . '/../dist/img/' . $picture;
    if (!file_exists($filePath)) {
        return $defaultAvatar;
    }

    return $baseUrl . 'kodus/dist/img/' . rawurlencode($picture);
}

$userId = $_SESSION['user_id'] ?? null;
$currentFolder = mailboxGetFolder($_GET['folder'] ?? 'inbox');
$trashPredicate = mailboxTrashPredicate($currentFolder, 'mr');
$folderTitle = $currentFolder === 'trash' ? 'Trash' : 'Inbox';

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
               TRIM(BOTH ', ' FROM CONCAT_WS(', ',
                   CASE
                       WHEN cm.recipient IS NULL OR cm.recipient = '' OR cm.recipient = 'admin' THEN 'Admin'
                       ELSE NULL
                   END,
                   (
                       SELECT GROUP_CONCAT(DISTINCT u.username ORDER BY u.username SEPARATOR ', ')
                       FROM users u
                       WHERE FIND_IN_SET(u.email, cm.recipient) > 0
                   )
               )) AS recipient_names,
               (
                   SELECT u.picture
                   FROM users u
                   WHERE FIND_IN_SET(u.email, cm.recipient) > 0
                   ORDER BY u.username
                   LIMIT 1
               ) AS recipient_picture
        FROM contact_messages cm
        LEFT JOIN (
            SELECT message_id, MAX(sent_at) AS latest_reply_at
            FROM contact_replies
            GROUP BY message_id
        ) reply_summary ON reply_summary.message_id = cm.id
        LEFT JOIN users sender_user ON sender_user.email = cm.user_email
        LEFT JOIN message_reads mr
            ON cm.id = mr.message_id AND mr.user_id = ?
        WHERE (
            cm.user_email IN (SELECT email FROM users WHERE id = ?)
            OR EXISTS (
                SELECT 1
                FROM users u
                WHERE FIND_IN_SET(u.email, cm.recipient) > 0
                  AND u.userType = 'admin'
            )
        )
          AND {$trashPredicate}
        ORDER BY latest_activity_at DESC, cm.id DESC
    ");
    $stmt->bind_param("ii", $userId, $userId);
} else {
    $userEmail = $_SESSION['email'] ?? '';
    $stmt = $conn->prepare("
        SELECT cm.*,
               COALESCE(reply_summary.latest_reply_at, cm.sent_at) AS latest_activity_at,
               COALESCE(mr.is_read, 0) AS user_read,
               sender_user.picture AS sender_picture,
               TRIM(BOTH ', ' FROM CONCAT_WS(', ',
                   CASE
                       WHEN cm.recipient IS NULL OR cm.recipient = '' OR cm.recipient = 'admin' THEN 'Admin'
                       ELSE NULL
                   END,
                   (
                       SELECT GROUP_CONCAT(DISTINCT u.username ORDER BY u.username SEPARATOR ', ')
                       FROM users u
                       WHERE FIND_IN_SET(u.email, cm.recipient) > 0
                   )
               )) AS recipient_names,
               (
                   SELECT u.picture
                   FROM users u
                   WHERE FIND_IN_SET(u.email, cm.recipient) > 0
                   ORDER BY u.username
                   LIMIT 1
               ) AS recipient_picture
        FROM contact_messages cm
        LEFT JOIN (
            SELECT message_id, MAX(sent_at) AS latest_reply_at
            FROM contact_replies
            GROUP BY message_id
        ) reply_summary ON reply_summary.message_id = cm.id
        LEFT JOIN users sender_user ON sender_user.email = cm.user_email
        LEFT JOIN message_reads mr
            ON cm.id = mr.message_id AND mr.user_id = ?
        WHERE (cm.user_email = ?
           OR FIND_IN_SET(?, cm.recipient) > 0)
          AND {$trashPredicate}
        ORDER BY latest_activity_at DESC, cm.id DESC
    ");
    $stmt->bind_param("iss", $userId, $userEmail, $userEmail);
}

$stmt->execute();
$messagesResult = $stmt->get_result();
$messages = $messagesResult ? $messagesResult->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

$messageCount = count($messages);
$unreadCount = 0;
foreach ($messages as $message) {
    if ((int) ($message['user_read'] ?? 0) === 0) {
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
            cm.user_email IN (SELECT email FROM users WHERE id = ?)
            OR EXISTS (
                SELECT 1
                FROM users u
                WHERE FIND_IN_SET(u.email, cm.recipient) > 0
                  AND u.userType = 'admin'
            )
        )
          AND COALESCE(mr.is_trashed, 0) = 1
    ");
    $trashCountStmt->bind_param("ii", $userId, $userId);
} else {
    $userEmail = $_SESSION['email'] ?? '';
    $trashCountStmt = $conn->prepare("
        SELECT COUNT(*) AS trash_count
        FROM contact_messages cm
        LEFT JOIN message_reads mr
            ON cm.id = mr.message_id AND mr.user_id = ?
        WHERE (cm.user_email = ? OR FIND_IN_SET(?, cm.recipient) > 0)
          AND COALESCE(mr.is_trashed, 0) = 1
    ");
    $trashCountStmt->bind_param("iss", $userId, $userEmail, $userEmail);
}

$trashCountStmt->execute();
$trashCountResult = $trashCountStmt->get_result()->fetch_assoc();
$trashCount = (int) ($trashCountResult['trash_count'] ?? 0);
$trashCountStmt->close();

$composeOpen = isset($_GET['compose']) && $_GET['compose'] !== '0';
$composeRecipients = [];
$recipientResult = $conn->query("SELECT id, username, email, picture FROM users ORDER BY username");
if ($recipientResult) {
    while ($recipientRow = $recipientResult->fetch_assoc()) {
        $recipientRow['avatar_url'] = mailbox_user_avatar_url($recipientRow['picture'] ?? '', $base_url);
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
  <title>KODUS | Mailbox</title>
  <link rel="stylesheet" href="<?php echo $base_url; ?>kodus/plugins/select2/css/select2.min.css">
  <link rel="stylesheet" href="<?php echo $base_url; ?>kodus/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
  <style>
    :root {
      --mailbox-surface: #ffffff;
      --mailbox-surface-muted: #f8fafc;
      --mailbox-border: #d9e2ec;
      --mailbox-border-strong: #c7d2e0;
      --mailbox-text: #1f2937;
      --mailbox-text-muted: #64748b;
      --mailbox-accent-soft: rgba(13, 110, 253, 0.1);
      --mailbox-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
    }

    body.dark-mode .mailbox-app,
    body[data-theme="dark"] .mailbox-app {
      --mailbox-surface: #1f2a37;
      --mailbox-surface-muted: #243140;
      --mailbox-border: rgba(255, 255, 255, 0.09);
      --mailbox-border-strong: rgba(255, 255, 255, 0.18);
      --mailbox-text: #f3f4f6;
      --mailbox-text-muted: #c0cad5;
      --mailbox-accent-soft: rgba(96, 165, 250, 0.18);
      --mailbox-shadow: 0 18px 36px rgba(0, 0, 0, 0.22);
    }

    body.dark-mode .mailbox-app .mailbox-read-info,
    body[data-theme="dark"] .mailbox-app .mailbox-read-info {
      box-shadow: none;
    }

    body.dark-mode .mailbox-app .attachment-thumb,
    body[data-theme="dark"] .mailbox-app .attachment-thumb {
      background: #2a3645;
      color: #f3f4f6;
      border-color: rgba(255, 255, 255, 0.1);
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
    }

    .mailbox-app .mailbox-messages-wrap {
      max-height: 48vh;
      overflow-y: auto;
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
      color: var(--mailbox-text);
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

    .mailbox-app .reply-menu-trigger {
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
    .mailbox-app .reply-menu-trigger:focus {
      background: rgba(255, 255, 255, 0.08);
      color: #fff;
      border-color: transparent;
      box-shadow: none;
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
      width: 132px;
      padding: 0.5rem;
      border-radius: 0.5rem;
      background: rgba(255, 255, 255, 0.92);
      color: #343a40;
      border: 1px solid rgba(0, 0, 0, 0.08);
      cursor: pointer;
    }

    .mailbox-app .attachment-thumb img {
      width: 100%;
      height: 84px;
      object-fit: cover;
      border-radius: 0.35rem;
    }

    .mailbox-app .attachment-thumb .filename {
      font-size: 0.75rem;
      margin-top: 0.45rem;
      word-break: break-word;
    }

    .mailbox-app .reply-form-shell {
      border: 1px solid var(--mailbox-border);
      border-radius: 0.5rem;
      padding: 1rem;
      background: linear-gradient(180deg, var(--mailbox-surface) 0%, var(--mailbox-surface-muted) 100%);
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .mailbox-app .reply-compose-tools {
      display: flex;
      justify-content: flex-end;
    }

    .mailbox-app .emoji-tools {
      position: relative;
      display: inline-flex;
    }

    .mailbox-app .emoji-menu {
      position: absolute;
      right: 0;
      top: calc(100% + 0.4rem);
      width: 214px;
      padding: 0.6rem;
      border-radius: 0.85rem;
      background: #1f2937;
      border: 1px solid rgba(255, 255, 255, 0.08);
      box-shadow: 0 12px 28px rgba(15, 23, 42, 0.28);
      display: grid;
      grid-template-columns: repeat(5, minmax(0, 1fr));
      gap: 0.35rem;
      z-index: 25;
    }

    .mailbox-app .emoji-menu[hidden] {
      display: none;
    }

    .mailbox-app .emoji-item {
      border: 0;
      background: transparent;
      border-radius: 0.55rem;
      padding: 0.35rem;
      font-size: 1.08rem;
      line-height: 1;
      color: inherit;
    }

    .mailbox-app .emoji-item:hover,
    .mailbox-app .emoji-item:focus {
      background: rgba(255, 255, 255, 0.08);
      outline: none;
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

    .mailbox-app .reply-form-hint {
      color: var(--mailbox-text-muted);
      font-size: 0.82rem;
    }

    .mailbox-app .compose-modal .modal-content {
      border: 1px solid var(--mailbox-border);
      border-radius: 1.35rem;
      background: linear-gradient(180deg, var(--mailbox-surface) 0%, var(--mailbox-surface-muted) 100%);
      box-shadow: var(--mailbox-shadow);
      overflow: hidden;
    }

    .mailbox-app .compose-modal .modal-header,
    .mailbox-app .compose-modal .modal-footer {
      border-color: var(--mailbox-border);
      background: color-mix(in srgb, var(--mailbox-surface) 92%, transparent);
    }

    .mailbox-app .compose-modal .modal-title {
      color: var(--mailbox-text);
      font-weight: 800;
      letter-spacing: -0.02em;
    }

    .mailbox-app .compose-panel {
      border: 1px solid var(--mailbox-border);
      border-radius: 1.1rem;
      background: color-mix(in srgb, var(--mailbox-surface) 94%, transparent);
      padding: 1rem;
    }

    .mailbox-app .compose-panel-head {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 1rem;
      margin-bottom: 0.85rem;
    }

    .mailbox-app .compose-panel-head h4 {
      margin: 0 0 0.25rem;
      color: var(--mailbox-text);
      font-size: 1rem;
      font-weight: 800;
    }

    .mailbox-app .compose-panel-head p,
    .mailbox-app .compose-meta {
      margin: 0;
      color: var(--mailbox-text-muted);
      font-size: 0.88rem;
    }

    .mailbox-app .compose-pill {
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

    .mailbox-app .compose-field label,
    .mailbox-app .compose-body label {
      display: block;
      color: var(--mailbox-text);
      font-size: 0.84rem;
      font-weight: 700;
      margin-bottom: 0.45rem;
    }

    .mailbox-app .compose-field + .compose-field,
    .mailbox-app .compose-body {
      margin-top: 0.95rem;
    }

    .mailbox-app .compose-field .form-control,
    .mailbox-app .compose-body .form-control,
    .mailbox-app .compose-attachment-label {
      border-radius: 0.95rem;
      border: 1px solid var(--mailbox-border);
      background: color-mix(in srgb, var(--mailbox-surface) 94%, transparent);
      color: var(--mailbox-text);
      box-shadow: none;
    }

    .mailbox-app .compose-field .form-control:focus,
    .mailbox-app .compose-body .form-control:focus {
      border-color: rgba(37, 99, 235, 0.45);
      box-shadow: 0 0 0 0.22rem rgba(37, 99, 235, 0.12);
    }

    .mailbox-app .compose-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 0.45rem;
    }

    .mailbox-app .compose-toolbar .btn {
      border-radius: 999px;
    }

    .mailbox-app .compose-toolbar-copy {
      color: var(--mailbox-text-muted);
      font-size: 0.82rem;
    }

    .mailbox-app .compose-attachment-wrap {
      margin-top: 0.95rem;
    }

    .mailbox-app .compose-attachment-label {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      padding: 0.9rem 1rem;
      cursor: pointer;
      margin: 0;
      border-style: dashed;
    }

    .mailbox-app .compose-file-summary {
      margin-top: 0.65rem;
      color: var(--mailbox-text-muted);
      font-size: 0.85rem;
    }

    .mailbox-app .compose-file-preview {
      display: flex;
      flex-wrap: wrap;
      gap: 0.65rem;
      margin-top: 0.8rem;
    }

    .mailbox-app .compose-file-card {
      width: 72px;
      height: 72px;
      border-radius: 1rem;
      border: 1px solid var(--mailbox-border);
      background: color-mix(in srgb, var(--mailbox-surface) 94%, transparent);
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      box-shadow: 0 10px 18px rgba(15, 23, 42, 0.08);
      color: var(--mailbox-text-muted);
    }

    .mailbox-app .compose-file-card img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .mailbox-app .compose-file-card.more {
      background: linear-gradient(135deg, #2563eb, #0f766e);
      color: #fff;
      font-weight: 800;
    }

    .mailbox-app .compose-stats {
      display: flex;
      flex-wrap: wrap;
      gap: 0.45rem 0.75rem;
      margin-top: 0.7rem;
      color: var(--mailbox-text-muted);
      font-size: 0.82rem;
    }

    .mailbox-app .compose-stat-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.38rem 0.7rem;
      border-radius: 999px;
      background: color-mix(in srgb, var(--mailbox-surface-muted) 88%, transparent);
      border: 1px solid var(--mailbox-border);
    }

    .mailbox-app .select2-container--bootstrap4 .select2-selection {
      min-height: 48px;
      border-radius: 0.95rem;
      border-color: var(--mailbox-border);
      background: color-mix(in srgb, var(--mailbox-surface) 94%, transparent);
    }

    .mailbox-app .select2-container--bootstrap4.select2-container--focus .select2-selection {
      border-color: rgba(37, 99, 235, 0.45);
      box-shadow: 0 0 0 0.22rem rgba(37, 99, 235, 0.12);
    }

    .mailbox-app .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__rendered {
      display: flex;
      flex-wrap: wrap;
      gap: 0.45rem;
      padding: 0.45rem 0.55rem;
    }

    .mailbox-app .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice {
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

    .mailbox-app .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__remove {
      color: var(--mailbox-text-muted);
      margin: 0 0.15rem 0 0;
      font-size: 0.8rem;
      line-height: 1;
    }

    .mailbox-app .select2-container--bootstrap4 .select2-selection--multiple .select2-search__field {
      color: var(--mailbox-text);
      margin-top: 0 !important;
    }

    .mailbox-app .select2-container--bootstrap4 .select2-dropdown {
      background: var(--mailbox-surface);
      border-color: var(--mailbox-border);
    }

    .mailbox-app .select2-container--bootstrap4 .select2-results__option {
      color: var(--mailbox-text);
    }

    .mailbox-app .compose-recipient-option,
    .mailbox-app .compose-recipient-chip {
      display: flex;
      align-items: center;
      gap: 0.55rem;
      min-width: 0;
    }

    .mailbox-app .compose-recipient-avatar {
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

    .mailbox-app .compose-recipient-copy {
      min-width: 0;
    }

    .mailbox-app .compose-recipient-name {
      display: block;
      color: var(--mailbox-text);
      font-size: 0.82rem;
      font-weight: 600;
      line-height: 1.2;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .mailbox-app .compose-recipient-meta {
      display: block;
      color: var(--mailbox-text-muted);
      font-size: 0.72rem;
      line-height: 1.2;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .mailbox-app .compose-recipient-chip .compose-recipient-meta {
      display: none;
    }

    .mailbox-app .compose-recipient-chip .compose-recipient-name {
      font-size: 0.76rem;
      font-weight: 600;
      line-height: 1.1;
    }

    .mailbox-app .select2-container--bootstrap4 .select2-selection__choice .compose-recipient-chip {
      gap: 0.3rem;
    }

    .mailbox-app .select2-container--bootstrap4 .select2-selection__choice .compose-recipient-avatar {
      width: 14px !important;
      height: 14px !important;
      min-width: 14px !important;
      max-width: 14px !important;
      min-height: 14px !important;
      max-height: 14px !important;
      display: inline-block !important;
      vertical-align: middle;
    }

    .mailbox-app .select2-container--bootstrap4 .select2-results__option .compose-recipient-avatar {
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
    .mailbox-app .select2-selection__choice .compose-recipient-chip {
      max-width: 100%;
    }

    .mailbox-app .select2-selection__choice__display .compose-recipient-name {
      display: inline-block;
      max-width: 140px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .mailbox-app .select2-results__option .compose-recipient-name {
      max-width: 240px;
    }

    .mailbox-app .select2-results__option .compose-recipient-meta {
      max-width: 240px;
    }

    @media (max-width: 991.98px) {
      .mailbox-app .compose-modal .modal-dialog {
        max-width: calc(100vw - 1.2rem);
      }
    }

    .mailbox-app .file-preview {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      margin-top: 0.75rem;
    }

    .mailbox-app .file-card {
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

    .mailbox-app .file-card img {
      width: 100%;
      height: 100%;
      object-fit: cover;
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
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }

      .mailbox-app .mailbox-name,
      .mailbox-app .mailbox-date {
        width: auto;
      }

      .mailbox-app .reply {
        max-width: 100%;
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
        overflow-x: visible;
      }

      .mailbox-app .mailbox-messages-wrap table {
        min-width: 0 !important;
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
      }

      .mailbox-app .mailbox-messages-wrap tbody {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        padding: 0.75rem;
      }

      .mailbox-app .mailbox-messages-wrap tr.message-item {
        display: grid;
        grid-template-columns: 18px 40px minmax(0, 1fr) auto;
        grid-template-areas:
          "star avatar name date"
          "star avatar subject subject";
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
        grid-template-columns: 18px 34px minmax(0, 1fr) auto;
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
        display: none;
      }

      .mailbox-app .reply-menu-trigger {
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
        width: calc(50% - 0.25rem);
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

      .mailbox-app .attachment-thumb {
        width: 100%;
      }
    }
  </style>
</head>
<body>
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
              <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>kodus/home">Home</a></li>
              <li class="breadcrumb-item active">Mailbox</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-lg-3 col-xl-2" id="mailboxSidebarColumn">
            <div class="card card-primary card-outline mailbox-sidebar-card">
              <div class="card-body box-profile">
                <button type="button" class="btn btn-primary btn-block mb-3 mailbox-compose-trigger">
                  <i class="fas fa-pen mr-1"></i> Compose
                </button>

                <div class="nav flex-column mailbox-folder-nav">
                  <a href="?folder=inbox" class="nav-link mailbox-folder-link <?= $currentFolder === 'inbox' ? 'active' : '' ?>">
                    <span><i class="fas fa-inbox mr-2"></i> Inbox</span>
                    <span class="badge bg-primary" id="sidebarUnreadBadge"><?= $unreadCount ?></span>
                  </a>
                  <a href="?folder=trash" class="nav-link mailbox-folder-link <?= $currentFolder === 'trash' ? 'active' : '' ?>">
                    <span><i class="far fa-trash-alt mr-2"></i> Trash</span>
                    <span class="badge bg-secondary" id="sidebarTrashBadge"><?= $trashCount ?></span>
                  </a>
                  <a href="?compose=1" class="nav-link mailbox-folder-link mailbox-compose-trigger">
                    <span><i class="far fa-envelope mr-2"></i> New Message</span>
                    <i class="fas fa-chevron-right small text-muted"></i>
                  </a>
                </div>

                <hr>

                <div class="text-muted small mailbox-sidebar-summary">
                  <div class="d-flex justify-content-between mb-2">
                    <span><?= $currentFolder === 'trash' ? 'Trashed conversations' : 'Total conversations' ?></span>
                    <strong id="messageCountLabel"><?= $messageCount ?></strong>
                  </div>
                  <div class="d-flex justify-content-between">
                    <span>Unread</span>
                    <strong id="unreadCountLabel"><?= $unreadCount ?></strong>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-9 col-xl-10">
            <div class="row">
              <div class="col-xl-5" id="mailboxListColumn">
                <div class="card card-primary card-outline mailbox-pane-card">
                  <div class="card-header">
                    <h3 class="card-title" id="mailboxListTitle"><?= htmlspecialchars($folderTitle) ?></h3>

                    <div class="card-tools mailbox-search">
                      <div class="input-group input-group-sm" style="width: 220px;">
                        <input type="text" class="form-control" id="mailboxSearch" placeholder="Search mail">
                        <div class="input-group-append">
                          <button type="button" class="btn btn-default">
                            <i class="fas fa-search"></i>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="card-body p-0">
                  <div class="mailbox-controls mailbox-toolbar">
                      <div class="mailbox-toolbar-copy">
                        <strong><?= htmlspecialchars($folderTitle) ?></strong>
                        <span><?= $messageCount ?> conversation<?= $messageCount === 1 ? '' : 's' ?></span>
                        <span><?= $unreadCount ?> unread</span>
                      </div>
                      <div class="mailbox-toolbar-copy">
                        <span class="mailbox-live-indicator">Auto-refresh every 30 seconds</span>
                        <span id="mailboxRefreshLabel">Live</span>
                      </div>
                    </div>
                    <div class="mailbox-filter-group">
                      <div class="btn-group btn-group-sm" role="group" aria-label="<?= htmlspecialchars($folderTitle) ?> filters">
                        <button type="button" class="btn btn-outline-primary mailbox-filter active" data-filter="all">All</button>
                        <button type="button" class="btn btn-outline-primary mailbox-filter" data-filter="unread">Unread</button>
                        <button type="button" class="btn btn-outline-primary mailbox-filter" data-filter="attachments">Attachments</button>
                      </div>
                    </div>

                    <div class="mailbox-messages-wrap" id="messageList">
                      <?php if ($messageCount > 0): ?>
                        <div class="table-responsive">
                          <table class="table table-hover table-striped mb-0">
                            <tbody>
                            <?php foreach ($messages as $row): ?>
                              <?php
                                $messageId = (int) $row['id'];
                                $recipientLabel = trim((string) ($row['recipient_names'] ?? ''));
                                $senderLabel = trim((string) ($row['user_name'] ?? ''));
                                if ($senderLabel === '') {
                                    $senderLabel = trim((string) ($row['user_email'] ?? 'Unknown'));
                                }
                                $isSenderView = !empty($_SESSION['email']) && strcasecmp((string) ($row['user_email'] ?? ''), (string) $_SESSION['email']) === 0;
                                $displayLabel = $isSenderView
                                    ? ($recipientLabel !== '' ? $recipientLabel : ((string) ($row['recipient'] ?? 'Unknown')))
                                    : $senderLabel;
                                $name = htmlspecialchars($displayLabel);
                                $displayPicture = $isSenderView ? ($row['recipient_picture'] ?? '') : ($row['sender_picture'] ?? '');
                                $avatarUrl = $base_url . 'kodus/dist/img/' . rawurlencode((string) $displayPicture);
                                $avatarPath = __DIR__ . '/../dist/img/' . (string) $displayPicture;
                                if (empty($displayPicture) || !file_exists($avatarPath)) {
                                    $avatarUrl = $base_url . 'kodus/dist/img/default.webp';
                                }
                                $subject = htmlspecialchars($row['subject'] ?? '(No Subject)');
                                $messageText = trim(preg_replace('/\s+/', ' ', (string) ($row['message'] ?? '')));
                                $snippet = htmlspecialchars(mb_strimwidth($messageText, 0, 70, '...'));
                                $email = htmlspecialchars($row['user_email'] ?? '');
                                $activityAt = (string) ($row['latest_activity_at'] ?? $row['sent_at'] ?? '');
                                $sentAt = htmlspecialchars($activityAt);
                                $timestamp = $activityAt !== '' ? strtotime($activityAt) : false;
                                $dateLabel = $timestamp ? date('M d', $timestamp) : '';
                                $hasAttachment = !empty($row['attachment']);
                                $rowClass = ((int) ($row['user_read'] ?? 0) === 0) ? 'unread' : '';
                              ?>
                              <tr class="message-item <?= $rowClass ?>"
                                  data-id="<?= $messageId ?>"
                                  data-email="<?= $email ?>"
                                  data-name="<?= $name ?>"
                                  data-subject="<?= $subject ?>"
                                  data-message="<?= htmlspecialchars($row['message'] ?? '') ?>"
                                  data-sent="<?= $sentAt ?>"
                                  data-unread="<?= $rowClass ? '1' : '0' ?>"
                                  data-has-attachment="<?= $hasAttachment ? '1' : '0' ?>">
                                <td class="mailbox-star text-center" style="width:40px;">
                                  <i class="fas fa-envelope <?= $rowClass ? 'text-warning' : 'text-muted' ?>"></i>
                                </td>
                                <td class="mailbox-avatar-cell text-center">
                                  <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= $name ?>" class="mailbox-avatar">
                                </td>
                                <td class="mailbox-name"><?= $name ?></td>
                                <td class="mailbox-subject">
                                  <?= $subject ?>
                                  <span class="mailbox-snippet"> - <?= $snippet ?></span>
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
                          <i class="far fa-envelope-open fa-3x mb-3"></i>
                          <p class="mb-1"><strong>No matching messages</strong></p>
                          <p class="mb-0">Try another search or filter.</p>
                        </div>
                      <?php else: ?>
                        <div class="mailbox-empty">
                          <img src="<?php echo $base_url; ?>kodus/dist/img/empty-inbox.png" alt="Empty Inbox">
                          <p class="mb-1"><strong><?= $currentFolder === 'trash' ? 'Trash is empty' : 'No messages yet' ?></strong></p>
                          <p class="mb-0"><?= $currentFolder === 'trash' ? 'Messages you delete for yourself will show up here.' : 'New conversations will show up here.' ?></p>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-xl-7" id="mailboxDetailColumn">
                <div class="card card-primary card-outline mailbox-pane-card">
                  <div class="card-header mailbox-detail-header">
                    <button type="button" class="btn btn-outline-secondary btn-sm mobile-only" id="mobileBackToList">
                      <i class="fas fa-arrow-left mr-1"></i> Back
                    </button>
                    <h3 class="card-title mb-0" id="mailboxDetailTitle">Read Mail</h3>
                  </div>
                  <div class="card-body mailbox-read-pane" id="messageDetail">
                    <?php if ($messageCount > 0): ?>
                      <div class="reply-placeholder">
                        <i class="far fa-envelope-open fa-3x mb-3"></i>
                        <p class="mb-1"><?= $currentFolder === 'trash' ? 'Select a trashed message to review it.' : 'Select a message to read the conversation.' ?></p>
                        <p class="mb-0 small"><?= $currentFolder === 'trash' ? 'You can restore it or leave it in Trash.' : 'Replies and attachments will appear here.' ?></p>
                      </div>
                    <?php else: ?>
                      <div class="mailbox-empty-detail">
                        <i class="far fa-envelope-open fa-3x mb-3"></i>
                        <p class="mb-1"><strong>No message selected</strong></p>
                        <p class="mb-0"><?= $currentFolder === 'trash' ? 'Your trash is currently empty.' : 'Your inbox is currently empty.' ?></p>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade compose-modal" id="composeModal" tabindex="-1" aria-labelledby="composeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form id="composeForm" action="../send_contact.php" method="POST" enctype="multipart/form-data">
        <div class="modal-header">
          <div>
            <h5 class="modal-title" id="composeModalLabel">New Message</h5>
            <p class="compose-meta">Compose and send without leaving your inbox.</p>
          </div>
          <button type="button" class="close text-reset" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="compose-panel">
            <div class="compose-panel-head">
              <div>
                <h4>Compose</h4>
                <p>Start a conversation.</p>
              </div>
              <span class="compose-pill"><i class="fas fa-bolt"></i> Fast send</span>
            </div>
            <div class="compose-field">
              <label for="composeRecipient">Recipients</label>
              <select name="recipient[]" id="composeRecipient" class="form-control" multiple required>
                <?php if ($userType === 'admin'): ?>
                  <option value="all" data-avatar="<?php echo htmlspecialchars($base_url . 'kodus/dist/img/default.webp'); ?>" data-kind="group">All Users & Admins</option>
                  <option value="users" data-avatar="<?php echo htmlspecialchars($base_url . 'kodus/dist/img/default.webp'); ?>" data-kind="group">All Users Only</option>
                <?php endif; ?>
                <option value="admins" data-avatar="<?php echo htmlspecialchars($base_url . 'kodus/dist/img/default.webp'); ?>" data-kind="group">Admin</option>
                    <?php foreach ($composeRecipients as $composeRecipient): ?>
                      <option
                        value="user_<?php echo (int) $composeRecipient['id']; ?>"
                        data-avatar="<?php echo htmlspecialchars($composeRecipient['avatar_url']); ?>"
                        data-kind="user"
                        data-name="<?php echo htmlspecialchars($composeRecipient['username']); ?>"
                        data-email="<?php echo htmlspecialchars($composeRecipient['email']); ?>"
                      >
                        <?php echo htmlspecialchars($composeRecipient['username']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
              <div class="compose-stats">
                <span class="compose-stat-pill" id="composeRecipientSummary">Choose one or more recipients</span>
              </div>
            </div>
            <div class="compose-field">
              <label for="composeSubject">Topic</label>
              <input type="text" name="subject" id="composeSubject" class="form-control" maxlength="180" placeholder="Type a conversation topic..." required>
            </div>
            <div class="compose-body">
              <div class="compose-toolbar">
                <label for="composeMessage" class="mb-0">Message</label>
                <div class="d-flex align-items-center" style="gap:0.5rem;">
                  <button type="button" class="btn btn-outline-secondary btn-sm" id="composeEmojiTrigger" aria-label="Insert emoji" aria-expanded="false">
                    <i class="far fa-smile mr-1"></i> Emoji
                  </button>
                  <div class="emoji-menu" id="composeEmojiMenu" hidden></div>
                </div>
              </div>
              <textarea name="message" id="composeMessage" class="form-control" rows="6" maxlength="5000" placeholder="Write your message..."></textarea>
              <div class="compose-stats">
                <span class="compose-stat-pill"><strong id="composeSubjectCount">0</strong>/180 topic</span>
                <span class="compose-stat-pill"><strong id="composeMessageCount">0</strong>/5000 message</span>
              </div>
              <div class="compose-attachment-wrap">
                <input type="file" name="attachments[]" id="composeAttachments" multiple hidden>
                <label class="compose-attachment-label" for="composeAttachments">
                  <span><i class="fas fa-paperclip mr-2"></i> Attach files</span>
                  <span class="small text-muted">Optional</span>
                </label>
                <div class="compose-file-summary" id="composeFileSummary">No files selected</div>
                <div class="compose-file-preview" id="composeFilePreview"></div>
              </div>
            </div>
          </div>

          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($composeCsrfToken, ENT_QUOTES) ?>">
          <input type="hidden" name="return_to" value="inbox/">
        </div>
        <div class="modal-footer justify-content-between">
          <div class="custom-control custom-checkbox">
            <input class="custom-control-input" type="checkbox" name="send_copy" id="composeSendCopy" value="1">
            <label class="custom-control-label" for="composeSendCopy">Send me a copy of this message</label>
          </div>
          <div class="d-flex align-items-center" style="gap:0.65rem;">
            <button type="button" class="btn btn-outline-secondary" id="composeResetBtn">Clear</button>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-paper-plane mr-1"></i> Send
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="<?php echo $base_url; ?>kodus/plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/select2/js/select2.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo $base_url; ?>kodus/dist/js/adminlte.min.js"></script>

<script>
let lastOpenedId = null;
let userIsScrolling = false;
let scrollTimeout = null;
let forceScrollNext = false;
let firstLoadDone = false;
const currentMailboxFolder = <?= json_encode($currentFolder, JSON_UNESCAPED_SLASHES) ?>;
const canReplyInCurrentFolder = currentMailboxFolder !== 'trash';
const shouldOpenCompose = <?= $composeOpen ? 'true' : 'false' ?>;

function getFolderEmptyMessage() {
    if (currentMailboxFolder === 'trash') {
        return {
            title: 'No message selected',
            body: 'Your trash is currently empty.'
        };
    }

    return {
        title: 'No message selected',
        body: 'Your inbox is currently empty.'
    };
}

function isMobileMailboxView() {
    return window.matchMedia('(max-width: 767.98px)').matches;
}

function updateDetailTitle() {
    const activeRow = lastOpenedId ? $(`#messageList .message-item[data-id="${lastOpenedId}"]`) : $();
    const title = activeRow.length
        ? (activeRow.attr('data-subject') || activeRow.find('.mailbox-name').text().trim() || 'Conversation')
        : 'Read Mail';

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

function isConversationNearBottom() {
    const conv = $('#conversationWrapper .conversation-scroll');
    if (!conv.length) {
        return true;
    }

    const el = conv[0];
    return (el.scrollHeight - el.scrollTop - el.clientHeight) < 24;
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
}

function renderEmptyDetail() {
    const emptyMessage = getFolderEmptyMessage();
    $('#messageDetail').html(`
      <div class="mailbox-empty-detail">
        <i class="far fa-envelope-open fa-3x mb-3"></i>
        <p class="mb-1"><strong>${emptyMessage.title}</strong></p>
        <p class="mb-0">${emptyMessage.body}</p>
      </div>
    `);
    updateDetailTitle();
}

function updateRefreshLabel(hasChanges = false) {
    if (!hasChanges) {
        $('#mailboxRefreshLabel').text('Waiting for new replies');
        return;
    }

    const now = new Date();
    $('#mailboxRefreshLabel').text('Updated ' + now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' }));
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
}

function openMessage(id) {
    if (!id) {
        return;
    }

    lastOpenedId = id;
    firstLoadDone = false;

    $('.message-item').removeClass('active');
    $(`#messageList .message-item[data-id="${id}"]`).addClass('active').removeClass('unread');
    updateMailboxSummary();

    $.get('get_thread.php', { id: id, folder: currentMailboxFolder }, function(html) {
        $('#messageDetail').html(html);
        updateDetailTitle();
        setMobileMailboxView('detail');
        autoScrollConversation();
    });

    $.post('mark_read.php', { id: id, csrf_token: window.KODUS_CSRF_TOKEN }, function() {
        $(`#messageList .message-item[data-id="${id}"]`).attr('data-unread', '0');
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
    });
}

function updateUnreadCount() {
    $.getJSON('get_unread_count.php', function(data) {
        const count = Number(data.count || 0);
        const badge = $('#sidebarMailUnreadBadge');
        const sidebarBadge = $('#sidebarUnreadBadge');
        const topbarBadge = $('#topbarUnreadBadge');
        const mailNavLabel = $('.nav-item a[href$="kodus/inbox/"] p');

        if (count > 0) {
            sidebarBadge.text(count).show();
            if (badge.length) {
                badge.text(count);
            } else {
                mailNavLabel.append(`<span class="right badge badge-danger" id="sidebarMailUnreadBadge">${count}</span>`);
            }

            if (topbarBadge.length) {
                topbarBadge.text(count).show();
            }
        } else {
            sidebarBadge.hide();
            badge.remove();
            topbarBadge.remove();
        }
    });
}

function autoScrollConversation() {
    const conv = $('#conversationWrapper .conversation-scroll');
    if (!conv.length) {
        return;
    }
    conv.scrollTop(conv[0].scrollHeight);
}

function escapeReplyHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function appendOptimisticReply(replyText) {
    const conversation = $('#conversationWrapper .conversation-scroll');
    if (!conversation.length || !replyText) {
        return;
    }

    const timeLabel = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    const safeReply = escapeReplyHtml(replyText).replace(/\n/g, '<br>');
    const userLabel = $('#mainSidebar .user-panel .info > a').first().text().trim() || 'You';
    const avatarSrc = $('#mainSidebar .user-panel .image img').attr('src') || '../dist/img/default.webp';

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
        <div>${safeReply}</div>
      </div>
    `);

    conversation.append(optimisticReply);

    setTimeout(function() {
        optimisticReply.find('.optimistic-status').fadeOut(200, function() {
            $(this).remove();
        });
    }, 2500);

    autoScrollConversation();
}

function refreshCurrentThread(options = {}) {
    if (!lastOpenedId) {
        return $.Deferred().resolve().promise();
    }

    const shouldScroll = Boolean(options.forceScroll);
    return $.get('get_thread.php', { id: lastOpenedId, folder: currentMailboxFolder }, function(html) {
        $('#messageDetail').html(html);
        updateDetailTitle();
        if (shouldScroll) {
            autoScrollConversation();
        }
    });
}

$(document).on('click', '.message-item', function() {
    openMessage($(this).data('id'));
});

function openReplyEditor(replyId, existingReply) {
    Swal.fire({
        title: 'Edit Reply',
        input: 'textarea',
        inputValue: existingReply,
        inputAttributes: {
            'aria-label': 'Edit reply'
        },
        inputValidator: (value) => {
            if (!String(value || '').trim()) {
                return 'Reply text cannot be empty.';
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
            Swal.fire('Unable to edit reply', xhr.responseJSON?.error || 'Please try again.', 'error');
        });
    });
}

function confirmReplyDelete(replyId) {
    Swal.fire({
        icon: 'warning',
        title: 'Delete this reply?',
        text: 'The reply content and attachments will be replaced with a deleted notice for all participants.',
        showCancelButton: true,
        confirmButtonText: 'Delete',
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
            Swal.fire('Unable to delete reply', xhr.responseJSON?.error || 'Please try again.', 'error');
        });
    });
}

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
        actions.push('<button type="button" class="swal2-deny swal2-styled" id="replyActionDeleteBtn" style="display:inline-block;background:#dc3545;">Delete</button>');
    }
    actions.push('<button type="button" class="swal2-cancel swal2-styled" id="replyActionCancelBtn" style="display:inline-block;">Cancel</button>');

    Swal.fire({
        title: 'Reply Actions',
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

$(document).on('click', '#mobileBackToList', function() {
    setMobileMailboxView('list');
});

$(document).on('scroll', '#conversationWrapper .conversation-scroll', function() {
    userIsScrolling = true;
    if (scrollTimeout) {
        clearTimeout(scrollTimeout);
    }
    scrollTimeout = setTimeout(() => {
        userIsScrolling = false;
    }, 3 * 60 * 1000);
});

$(document).on('submit', '#replyForm', function(e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(this);
    const replyText = ($(this).find('#replyText').val() || '').trim();
    const replyMailCsrfToken = $(this).find('input[name="csrf_token"]').val() || window.KODUS_CSRF_TOKEN || '';

    Swal.fire({
        icon: 'info',
        title: 'Sending...',
        html: 'Please wait...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    $.ajax({
        url: 'send_reply.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(resp) {
            Swal.close();
            if (resp.status === 'success') {
                form.reset();
                $('#replyFilePreview').empty();
                appendOptimisticReply(replyText);

                if (resp.reply_id) {
                    $.ajax({
                        url: 'send_reply_mail.php',
                        type: 'POST',
                        data: {
                            reply_id: resp.reply_id,
                            csrf_token: replyMailCsrfToken
                        }
                    }).fail(function(xhr) {
                        console.warn('Reply mail background send failed.', xhr.responseText || xhr.statusText);
                    });
                }

                forceScrollNext = true;
                updateMessageList();

                if (lastOpenedId) {
                    $.get('get_thread.php', { id: lastOpenedId, folder: currentMailboxFolder }, function(html) {
                        $('#messageDetail').html(html);
                        $(`#messageList .message-item[data-id="${lastOpenedId}"]`).addClass('active').removeClass('unread');
                        $(`#messageList .message-item[data-id="${lastOpenedId}"]`).attr('data-unread', '0');
                        autoScrollConversation();
                    });
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: resp.message || 'Failed to send reply'
                });
            }
        },
        error: function(xhr) {
            Swal.close();
            Swal.fire({
                icon: 'error',
                title: 'Server Error',
                text: xhr.responseText
            });
        }
    });
});

setInterval(function() {
    if (!lastOpenedId) {
        return;
    }

    if (!canReplyInCurrentFolder) {
        return;
    }

    const draft = $('#replyText').val() || '';
    const previousMarkup = getConversationMarkup();
    const conv = $('#conversationWrapper .conversation-scroll');
    const previousScrollTop = conv.length ? conv.scrollTop() : 0;
    const shouldStickToBottom = forceScrollNext || (!userIsScrolling && isConversationNearBottom());

    $.get('get_thread.php', { id: lastOpenedId, only_conversation: 1, folder: currentMailboxFolder }, function(html) {
        if (normalizeHtml(html) === previousMarkup) {
            if ($('#replyText').length) {
                $('#replyText').val(draft);
            }
            return;
        }

        if ($('#conversationWrapper').length) {
            $('#conversationWrapper').replaceWith(html);
        } else {
            $('#messageDetail').append(html);
        }

        if ($('#replyText').length) {
            $('#replyText').val(draft);
        }

        if (!firstLoadDone) {
            autoScrollConversation();
            firstLoadDone = true;
        } else if (forceScrollNext) {
            autoScrollConversation();
            forceScrollNext = false;
        } else if (shouldStickToBottom) {
            autoScrollConversation();
        } else {
            const nextConv = $('#conversationWrapper .conversation-scroll');
            if (nextConv.length) {
                nextConv.scrollTop(previousScrollTop);
            }
        }
    });
}, 15000);

setInterval(updateUnreadCount, 30000);
setInterval(updateMessageList, 30000);

$('#mailboxSearch').on('input', applyMessageFilters);

$(document).on('click', '.mailbox-filter', function() {
    $('.mailbox-filter').removeClass('active');
    $(this).addClass('active');
    applyMessageFilters();
});

function buildFolderEmptyHtml() {
    const title = currentMailboxFolder === 'trash' ? 'Trash is empty' : 'No messages yet';
    const body = currentMailboxFolder === 'trash'
        ? 'Messages you delete for yourself will show up here.'
        : 'New conversations will show up here.';

    return `
      <div class="mailbox-empty">
        <img src="<?php echo $base_url; ?>kodus/dist/img/empty-inbox.png" alt="Empty Inbox">
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

$(document).on('click', '.mailbox-delete-trigger', function() {
    const messageId = Number($(this).data('id') || 0);
    const canDeleteEveryone = String($(this).data('can-delete-everyone') || '0') === '1';

    if (!messageId) {
        return;
    }

    const isTrashView = currentMailboxFolder === 'trash';
    const buttons = isTrashView
        ? []
        : ['<button type="button" class="swal2-confirm swal2-styled" id="mailDeleteSelfBtn" style="display:inline-block;background:#6c757d;">Delete for me</button>'];

    if (canDeleteEveryone) {
        buttons.push('<button type="button" class="swal2-deny swal2-styled" id="mailDeleteEveryoneBtn" style="display:inline-block;background:#dc3545;">Delete for everyone</button>');
    }

    buttons.push('<button type="button" class="swal2-cancel swal2-styled" id="mailDeleteCancelBtn" style="display:inline-block;">Cancel</button>');

    Swal.fire({
        icon: 'warning',
        title: 'Delete conversation',
        html: `
          <div class="text-left">
            <p class="mb-2">${isTrashView ? 'This conversation is already in Trash.' : 'Choose how you want to delete this conversation.'}</p>
            <p class="small text-muted mb-0">${isTrashView ? 'Deleting for everyone permanently removes it and its attachments for all participants.' : 'Delete for me moves it to Trash. Delete for everyone permanently removes it for all participants.'}</p>
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
                    Swal.fire('Unable to move message', xhr.responseJSON?.error || 'Please try again.', 'error');
                });
            });

            popup.querySelector('#mailDeleteEveryoneBtn')?.addEventListener('click', function() {
                Swal.fire({
                    icon: 'warning',
                    title: 'Delete for everyone?',
                    text: 'This permanently removes the conversation and its attachments for all participants.',
                    showCancelButton: true,
                    confirmButtonText: 'Delete permanently',
                    confirmButtonColor: '#dc3545'
                }).then(function(result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    moveConversation(messageId, 'everyone').done(function() {
                        removeMessageFromCurrentView(messageId, 'everyone');
                    }).fail(function(xhr) {
                        Swal.fire('Unable to delete message', xhr.responseJSON?.error || 'Please try again.', 'error');
                    });
                });
            });

            popup.querySelector('#mailDeleteCancelBtn')?.addEventListener('click', function() {
                Swal.close();
            });
        }
    });
});

$(document).on('click', '.mailbox-restore-trigger', function() {
    const messageId = Number($(this).data('id') || 0);
    if (!messageId) {
        return;
    }

    moveConversation(messageId, 'restore').done(function() {
        removeMessageFromCurrentView(messageId, 'restore');
    }).fail(function(xhr) {
        Swal.fire('Unable to restore message', xhr.responseJSON?.error || 'Please try again.', 'error');
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
        const basePath = '<?php echo $base_url; ?>kodus/inbox/uploads/';
        const folder = type === 'reply' ? 'reply_attachments/' : 'contact_attachments/';
        const attachmentsBase = basePath + folder;

        if (window._swalKeyHandler) {
            window.removeEventListener('keydown', window._swalKeyHandler, true);
            window._swalKeyHandler = null;
        }

        function renderPreviewHtml(file, index, total) {
            const ext = (file.split('.').pop() || '').toLowerCase();
            const path = attachmentsBase + encodeURIComponent(file);
            const isImage = ['avif', 'jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
            const preview = isImage
                ? `<img src="${path}" alt="${file}" style="max-width:100%;max-height:100%;object-fit:contain;">`
                : (ext === 'pdf'
                    ? `<iframe src="${path}" style="width:100%;height:100%;border:none;"></iframe>`
                    : `<div class="text-center"><i class="fas fa-file fa-3x mb-3"></i><div>${file}</div></div>`);

            return `
              <div style="width:900px;height:600px;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                <div style="flex:1;width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#fff;border-radius:6px;overflow:hidden;">
                  ${preview}
                </div>
                <div style="margin-top:8px;">
                  <a href="${path}" download class="btn btn-success btn-sm"><i class="fas fa-download"></i> Download</a>
                </div>
                <div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-top:8px;">
                  <button id="swalPrev" class="swal2-confirm swal2-styled" style="min-width:80px;">Prev</button>
                  <div style="font-size:0.95rem;color:#666;">${index + 1} of ${total}</div>
                  <button id="swalNext" class="swal2-confirm swal2-styled" style="min-width:80px;">Next</button>
                </div>
              </div>
            `;
        }

        function bindButtons() {
            const prev = document.getElementById('swalPrev');
            const next = document.getElementById('swalNext');

            if (prev) prev.onclick = () => updateContent(idx - 1);
            if (next) next.onclick = () => updateContent(idx + 1);
        }

        function updateContent(newIndex) {
            idx = (newIndex + attachments.length) % attachments.length;
            Swal.update({ html: renderPreviewHtml(attachments[idx], idx, attachments.length) });
            bindButtons();
        }

        window._swalKeyHandler = function(e) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                updateContent(idx - 1);
            } else if (e.key === 'ArrowRight') {
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
            width: '51%',
            heightAuto: false,
            allowOutsideClick: true,
            didOpen: function() {
                bindButtons();
                window.addEventListener('keydown', window._swalKeyHandler, true);
            },
            willClose: function() {
                window.removeEventListener('keydown', window._swalKeyHandler, true);
                window._swalKeyHandler = null;
            }
        });
    }

    $(document).on('click', '.attachment-thumb', function() {
        const attachments = safeParseAttachments($(this).attr('data-attachments'));
        const idx = parseInt($(this).attr('data-index'), 10) || 0;
        const type = $(this).data('type') || 'contact';
        openCarousel(attachments, idx, type);
    });
})();

$(function() {
    updateMailboxSummary();
    updateRefreshLabel();
    updateDetailTitle();
    updateUnreadCount();

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
        setMobileMailboxView(lastOpenedId ? 'detail' : 'list');
    });

    const composeModal = $('#composeModal');
    const composeForm = document.getElementById('composeForm');
    const composeRecipient = document.getElementById('composeRecipient');
    const composeSubject = document.getElementById('composeSubject');
    const composeMessage = document.getElementById('composeMessage');
    const composeAttachments = document.getElementById('composeAttachments');
    const composeFilePreview = document.getElementById('composeFilePreview');
    const composeFileSummary = document.getElementById('composeFileSummary');
    const composeRecipientSummary = document.getElementById('composeRecipientSummary');
    const composeSubjectCount = document.getElementById('composeSubjectCount');
    const composeMessageCount = document.getElementById('composeMessageCount');
    const composeResetBtn = document.getElementById('composeResetBtn');
    const composeEmojiTrigger = document.getElementById('composeEmojiTrigger');
    const composeEmojiMenu = document.getElementById('composeEmojiMenu');
    const commonComposeEmojis = ['😀','😂','😊','😍','😉','👍','👏','🙏','🎉','🔥','❤️','✨','📎','📩','✅','🤝','🙌','😎','📌','💡'];

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
        if (composeEmojiMenu) {
            composeEmojiMenu.hidden = true;
        }
        if (composeEmojiTrigger) {
            composeEmojiTrigger.setAttribute('aria-expanded', 'false');
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

    function buildComposeEmojiMenu() {
        if (!composeEmojiMenu || !composeMessage || composeEmojiMenu.childElementCount > 0) {
            return;
        }
        commonComposeEmojis.forEach(function(emoji) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'emoji-item';
            button.textContent = emoji;
            button.addEventListener('click', function(e) {
                e.preventDefault();
                insertComposeTextAtCursor(composeMessage, emoji);
                closeComposeEmojiMenu();
            });
            composeEmojiMenu.appendChild(button);
        });
    }

    function clearComposeFiles() {
        if (composeFilePreview) {
            composeFilePreview.innerHTML = '';
        }
        if (composeFileSummary) {
            composeFileSummary.textContent = 'No files selected yet.';
        }
    }

    function updateComposeAttachments() {
        if (!composeAttachments || !composeFilePreview || !composeFileSummary) {
            return;
        }

        composeFilePreview.innerHTML = '';
        const files = Array.prototype.slice.call(composeAttachments.files || []);
        if (!files.length) {
            clearComposeFiles();
            return;
        }

        composeFileSummary.textContent = files.length === 1
            ? files[0].name
            : `${files.length} files selected`;

        const maxPreview = 4;
        files.slice(0, maxPreview).forEach(function(file) {
            const card = document.createElement('div');
            card.className = 'compose-file-card';
            const ext = (file.name.split('.').pop() || '').toLowerCase();

            if (file.type.startsWith('image/') || ['avif', 'webp', 'jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                card.appendChild(img);
            } else {
                const icon = document.createElement('i');
                icon.className = 'fas fa-file';
                card.appendChild(icon);
            }

            composeFilePreview.appendChild(card);
        });

        if (files.length > maxPreview) {
            const moreCard = document.createElement('div');
            moreCard.className = 'compose-file-card more';
            moreCard.textContent = `+${files.length - maxPreview}`;
            composeFilePreview.appendChild(moreCard);
        }
    }

    function renderComposeRecipientOption(option) {
        if (!option.id) {
            return option.text;
        }

        const data = option.element ? option.element.dataset : {};
        const avatar = data.avatar || '<?php echo $base_url; ?>kodus/dist/img/default.webp';
        const name = data.name || option.text;
        const meta = data.kind === 'user' ? (`&lt;${data.email || ''}&gt;`) : 'Group';
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
        const avatar = data.avatar || '<?php echo $base_url; ?>kodus/dist/img/default.webp';
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
            composeRecipientSummary.textContent = 'Choose one or more recipients';
            return;
        }

        const labels = selectedOptions.map(option => option.text);
        composeRecipientSummary.textContent = labels.length === 1
            ? labels[0]
            : `${labels.length} recipients selected`;
    }

    function updateComposePreview() {
        if (!composeSubject || !composeMessage) {
            return;
        }

        composeSubjectCount.textContent = String((composeSubject.value || '').length);
        composeMessageCount.textContent = String((composeMessage.value || '').length);
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
    }

    $(document).on('click', '.mailbox-compose-trigger', function(e) {
        e.preventDefault();
        openComposeModal();
    });

    composeRecipient?.addEventListener('change', updateComposeRecipientMeta);
    composeSubject?.addEventListener('input', updateComposePreview);
    composeMessage?.addEventListener('input', updateComposePreview);
    composeAttachments?.addEventListener('change', updateComposeAttachments);
    composeResetBtn?.addEventListener('click', resetComposeForm);
    composeEmojiTrigger?.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        buildComposeEmojiMenu();
        const shouldOpen = composeEmojiMenu.hidden;
        closeComposeEmojiMenu();
        composeEmojiMenu.hidden = !shouldOpen;
        composeEmojiTrigger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    });
    composeEmojiMenu?.addEventListener('click', function(e) {
        e.stopPropagation();
    });
    document.addEventListener('click', closeComposeEmojiMenu);
    composeForm?.addEventListener('submit', function() {
        const submitButton = this.querySelector('[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
        }
    });
    composeModal.on('hidden.bs.modal', function() {
        closeComposeEmojiMenu();
    });

    if (window.jQuery && $.fn.select2 && composeRecipient) {
        $('#composeRecipient').select2({
            theme: 'bootstrap4',
            width: '100%',
            dropdownParent: composeModal,
            placeholder: 'Choose recipients',
            templateResult: renderComposeRecipientOption,
            templateSelection: renderComposeRecipientSelection,
            escapeMarkup: function(markup) { return markup; }
        });
        $('#composeRecipient').on('change', updateComposeRecipientMeta);
    }

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
