<?php
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../base_url.php';
require_once __DIR__ . '/mailbox_helpers.php';

security_bootstrap_session();
include('../config.php');
mailboxEnsureSchema($conn);
$attachmentLimits = mailboxAttachmentLimits();

$id = intval($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'] ?? 0;
$userType = $_SESSION['user_type'] ?? null;
$userEmail = $_SESSION['email'] ?? null;
$userName = trim((string) ($_SESSION['username'] ?? ''));
$currentFolder = mailboxGetFolder($_GET['folder'] ?? 'inbox');
$onlyConversation = isset($_GET['only_conversation']) && ($_GET['only_conversation'] == '1' || $_GET['only_conversation'] === 'true');
$isBubbleMode = isset($_GET['bubble']) && (string) $_GET['bubble'] === '1';

if (!$id || !$userId) {
    http_response_code(400);
    echo '<p>Invalid request.</p>';
    exit;
}

$query = "
    SELECT cm.*,
           sender_user.picture AS sender_picture,
           sender_user.id AS sender_user_id,
           sender_user.sso_avatar_url AS sender_sso_avatar_url,
           (
               SELECT COALESCE(NULLIF(u.username, ''), COALESCE(NULLIF(cmr.recipient_name, ''), cmr.recipient_email))
               FROM contact_message_recipients cmr
               LEFT JOIN users u ON u.id = cmr.user_id
               WHERE cmr.message_id = cm.id
               ORDER BY COALESCE(NULLIF(u.username, ''), COALESCE(NULLIF(cmr.recipient_name, ''), cmr.recipient_email))
               LIMIT 1
           ) AS primary_recipient_name,
           (
               SELECT u.id
               FROM contact_message_recipients cmr
               LEFT JOIN users u ON u.id = cmr.user_id
               WHERE cmr.message_id = cm.id
               ORDER BY COALESCE(NULLIF(u.username, ''), COALESCE(NULLIF(cmr.recipient_name, ''), cmr.recipient_email))
               LIMIT 1
           ) AS primary_recipient_user_id,
           (
               SELECT u.picture
               FROM contact_message_recipients cmr
               LEFT JOIN users u ON u.id = cmr.user_id
               WHERE cmr.message_id = cm.id
               ORDER BY COALESCE(NULLIF(u.username, ''), COALESCE(NULLIF(cmr.recipient_name, ''), cmr.recipient_email))
               LIMIT 1
           ) AS primary_recipient_picture,
           (
               SELECT u.sso_avatar_url
               FROM contact_message_recipients cmr
               LEFT JOIN users u ON u.id = cmr.user_id
               WHERE cmr.message_id = cm.id
               ORDER BY COALESCE(NULLIF(u.username, ''), COALESCE(NULLIF(cmr.recipient_name, ''), cmr.recipient_email))
               LIMIT 1
           ) AS primary_recipient_sso_avatar_url,
           (
               SELECT GROUP_CONCAT(DISTINCT u.username ORDER BY u.username SEPARATOR ', ')
               FROM contact_message_recipients cmr
               INNER JOIN users u ON u.id = cmr.user_id
               WHERE cmr.message_id = cm.id
           ) AS recipient_names,
           (
               SELECT GROUP_CONCAT(
                   DISTINCT CONCAT(
                       COALESCE(NULLIF(u.username, ''), COALESCE(NULLIF(cmr.recipient_name, ''), cmr.recipient_email)),
                       ' <',
                       cmr.recipient_email,
                       '>'
                   )
                   ORDER BY COALESCE(NULLIF(u.username, ''), COALESCE(NULLIF(cmr.recipient_name, ''), cmr.recipient_email))
                   SEPARATOR '||'
               )
               FROM contact_message_recipients cmr
               LEFT JOIN users u ON u.id = cmr.user_id
               WHERE cmr.message_id = cm.id
           ) AS recipient_directory
    FROM contact_messages cm
    LEFT JOIN users sender_user ON sender_user.email = cm.user_email
    WHERE cm.id = ?
";

if ($userType === 'admin') {
    $query .= "
      AND (
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
      AND " . mailboxThreadAccessPredicate('cm') . "
      LIMIT 1
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('isi', $id, $userEmail, $userId);
} else {
    $query .= " AND (cm.user_email = ? OR cm.user_name = ? OR EXISTS (
        SELECT 1
        FROM contact_message_recipients cmr
        WHERE cmr.message_id = cm.id
          AND LOWER(cmr.recipient_email) = LOWER(?)
    ) OR COALESCE(cm.conversation_type, 'direct') = 'group')
    AND " . mailboxThreadAccessPredicate('cm') . "
    LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('isssi', $id, $userEmail, $userName, $userEmail, $userId);
}

$stmt->execute();
$message = db_stmt_fetch_one_assoc($stmt);
$stmt->close();

if (!$message) {
    echo '<p>Message not found.</p>';
    exit;
}

$currentUserEmail = (string) ($userEmail ?? '');
$currentUserName = (string) $userName;
$originalSenderEmail = (string) ($message['user_email'] ?? '');
$originalIsMine = mailboxOwnerMatchesCurrentUser(
    $originalSenderEmail,
    (string) ($message['user_name'] ?? ''),
    $currentUserEmail,
    $currentUserName
);
$originalReplyClass = $originalIsMine ? 'reply mine' : 'reply theirs';
$originalDisplayName = $originalIsMine
    ? (trim((string) ($_SESSION['username'] ?? '')) !== '' ? (string) $_SESSION['username'] : 'You')
    : (trim((string) ($message['user_name'] ?? '')) !== '' ? (string) $message['user_name'] : $originalSenderEmail);
$originalAvatarUrl = avatar_resolve_url($message['sender_picture'] ?? '', $message['sender_sso_avatar_url'] ?? '', $base_url, dirname(__DIR__));
$canDeleteForEveryone = $userType === 'admin'
    || mailboxOwnerMatchesCurrentUser(
        $originalSenderEmail,
        (string) ($message['user_name'] ?? ''),
        $currentUserEmail,
        $currentUserName
    );
$isGroupThread = mailboxIsGroupThread($message);
$groupMemberState = ['left_at' => null, 'muted_at' => null, 'hidden_at' => null];
if ($isGroupThread) {
    $memberStateStmt = $conn->prepare("
        SELECT muted_at, left_at, hidden_at
        FROM contact_message_recipients
        WHERE message_id = ? AND user_id = ?
        LIMIT 1
    ");
    if ($memberStateStmt) {
        $memberStateStmt->bind_param('ii', $id, $userId);
        $memberStateStmt->execute();
        $groupMemberState = db_stmt_fetch_one_assoc($memberStateStmt) ?: $groupMemberState;
        $memberStateStmt->close();
    }
}

$stmt = $conn->prepare("
    SELECT r.*, u.username, u.userType, u.picture, u.sso_avatar_url, deleter.username AS deleted_by_username
    FROM contact_replies r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN users deleter ON deleter.id = r.deleted_by_user_id
    WHERE r.message_id = ?
    ORDER BY r.sent_at ASC
");
$stmt->bind_param('i', $id);
$stmt->execute();
$replies = db_stmt_fetch_all_assoc($stmt);
$stmt->close();
$replyCount = count($replies);

$participantLabels = [];
$participantMentions = [];
$participantMentionItems = [];
$senderParticipantLabel = trim((string) ($message['user_name'] ?? '')) !== ''
    ? (string) $message['user_name']
    : (string) ($message['user_email'] ?? 'Unknown');
$participantLabels[] = $senderParticipantLabel;
$participantMentions[] = $senderParticipantLabel;
if ((int) ($message['sender_user_id'] ?? 0) > 0 && (int) ($message['sender_user_id'] ?? 0) !== (int) $userId) {
    $participantMentionItems[] = [
        'id' => (int) $message['sender_user_id'],
        'name' => $senderParticipantLabel,
        'mention' => preg_replace('/\s+/', '', $senderParticipantLabel),
    ];
}

$recipientDirectory = array_filter(array_map('trim', explode('||', (string) ($message['recipient_directory'] ?? ''))));
foreach ($recipientDirectory as $recipientEntry) {
    if ($recipientEntry === '') {
        continue;
    }

    $displayName = trim((string) preg_replace('/\s*<[^>]+>\s*$/', '', $recipientEntry));
    if ($displayName !== '') {
        $participantLabels[] = $displayName;
        $participantMentions[] = $displayName;
    }
}

if ($isGroupThread) {
    $mentionStmt = $conn->prepare("
        SELECT cmr.user_id,
               COALESCE(NULLIF(u.username, ''), COALESCE(NULLIF(cmr.recipient_name, ''), cmr.recipient_email)) AS display_name
        FROM contact_message_recipients cmr
        LEFT JOIN users u ON u.id = cmr.user_id
        WHERE cmr.message_id = ?
          AND cmr.user_id IS NOT NULL
          AND cmr.user_id <> ?
          AND cmr.left_at IS NULL
          AND cmr.hidden_at IS NULL
        ORDER BY display_name
    ");
    if ($mentionStmt) {
        $mentionStmt->bind_param('ii', $id, $userId);
        $mentionStmt->execute();
        foreach (db_stmt_fetch_all_assoc($mentionStmt) as $mentionRow) {
            $mentionName = trim((string) ($mentionRow['display_name'] ?? ''));
            $mentionUserId = (int) ($mentionRow['user_id'] ?? 0);
            if ($mentionName !== '' && $mentionUserId > 0) {
                $participantMentionItems[] = [
                    'id' => $mentionUserId,
                    'name' => $mentionName,
                    'mention' => preg_replace('/\s+/', '', $mentionName),
                ];
            }
        }
        $mentionStmt->close();
    }
}

$participantLabels = array_values(array_unique(array_filter($participantLabels)));
$participantMentions = array_values(array_unique(array_filter($participantMentions)));
$participantMentionItems = array_values(array_unique($participantMentionItems, SORT_REGULAR));
$recipientDisplay = trim((string) ($message['recipient_names'] ?? ''));
$primaryRecipientName = trim((string) ($message['primary_recipient_name'] ?? ''));
$primaryRecipientAvatarUrl = avatar_resolve_url(
    $message['primary_recipient_picture'] ?? '',
    $message['primary_recipient_sso_avatar_url'] ?? '',
    $base_url,
    dirname(__DIR__)
);
$chatTitle = $isGroupThread
    ? (trim((string) ($message['group_name'] ?? '')) !== '' ? (string) $message['group_name'] : 'Group chat')
    : ($originalIsMine
    ? ($primaryRecipientName !== '' ? $primaryRecipientName : ($recipientDisplay !== '' ? $recipientDisplay : 'Admin'))
    : $originalDisplayName);

if ($chatTitle === '' || strcasecmp($chatTitle, 'unknown') === 0) {
    $chatTitle = 'Chat';
}

$chatAvatarUrl = $isGroupThread
    ? (trim((string) ($message['group_photo'] ?? '')) !== '' ? $base_url . 'inbox/uploads/group_photos/' . rawurlencode((string) $message['group_photo']) : $base_url . 'dist/img/default.webp')
    : ($originalIsMine ? $primaryRecipientAvatarUrl : $originalAvatarUrl);
if (trim((string) $chatAvatarUrl) === '') {
    $chatAvatarUrl = $originalAvatarUrl;
}

$chatPresenceUserId = $isGroupThread ? 0 : (int) ($originalIsMine ? ($message['primary_recipient_user_id'] ?? 0) : ($message['sender_user_id'] ?? 0));
$chatPresence = $isGroupThread
    ? ['detail' => 'Group chat', 'class' => 'offline']
    : ['detail' => 'Active status unavailable', 'class' => 'offline'];
$chatStatusCopy = $currentFolder === 'trash'
    ? 'Archived chat'
    : ($isGroupThread
        ? (!empty($groupMemberState['left_at'])
            ? 'You left this group'
            : (!empty($groupMemberState['muted_at'])
                ? 'Muted - ' . count($participantLabels) . ' members'
                : count($participantLabels) . ' members'))
        : $chatPresence['detail']);
$chatMetaCopy = $currentFolder === 'trash'
    ? 'Restore this chat to send new updates.'
    : '';

$stmt = $conn->prepare("
    INSERT INTO message_reads (message_id, user_id, is_read, read_at)
    VALUES (?, ?, 1, NOW())
    ON DUPLICATE KEY UPDATE is_read=1, read_at=NOW()
");
$stmt->bind_param('ii', $id, $userId);
$stmt->execute();
$stmt->close();

function renderAttachments($attachmentCsv, $basePath, $type = 'contact')
{
    if (empty($attachmentCsv)) {
        return '';
    }

    $files = array_values(array_filter(array_map('trim', explode(',', $attachmentCsv))));
    if (!$files) {
        return '';
    }

    $jsonFiles = htmlspecialchars(json_encode($files), ENT_QUOTES, 'UTF-8');
    $html = '<div class="attachments"><div class="font-weight-bold small mb-2">Attachments</div><div class="attachments-list">';

    foreach ($files as $i => $file) {
        $path = $basePath . rawurlencode($file);
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $isMedia = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'mp4', 'webm', 'ogg', 'ogv', 'mov', 'm4v', 'mp3', 'wav', 'm4a', 'aac', 'flac'], true);
        $safePath = htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
        $safeFile = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
        $html .= '<div class="attachment-thumb" data-attachments=\'' . $jsonFiles . '\' data-index="' . $i . '" data-type="' . $type . '">';

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true)) {
            $html .= '<img src="' . $safePath . '" alt="">';
        } elseif (in_array($ext, ['mp4', 'webm', 'ogv', 'mov', 'm4v'], true)) {
            $html .= '<video src="' . $safePath . '" controls preload="metadata"></video>';
        } elseif (in_array($ext, ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'], true)) {
            $html .= '<audio src="' . $safePath . '" controls preload="metadata"></audio>';
        } elseif ($ext === 'pdf') {
            $html .= '<div class="d-flex align-items-center justify-content-center" style="height:84px;"><i class="fas fa-file-pdf fa-2x text-danger"></i></div>';
        } else {
            $html .= '<div class="d-flex align-items-center justify-content-center" style="height:84px;"><i class="fas fa-file fa-2x text-secondary"></i></div>';
        }

        if (!$isMedia) {
            $html .= '<div class="filename">' . $safeFile . '</div>';
        }

        $html .= '<a href="' . $safePath . '" download class="btn btn-xs btn-outline-secondary mt-2">Download</a>';
        $html .= '</div>';
    }

    $html .= '</div>';

    if (count($files) > 1) {
        $html .= '<div class="mt-2">';
        $html .= '<form method="post" action="download_all.php" target="_blank">';
        $html .= '<input type="hidden" name="files" value=\'' . json_encode($files) . '\' />';
        $html .= '<button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-download"></i> Download All</button>';
        $html .= '</form>';
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}

function messengerFriendlyTime(?string $value): string
{
    $timestamp = strtotime((string) $value);
    if (!$timestamp) {
        return '';
    }

    return date('M j, g:i A', $timestamp);
}

function renderReactionBar(array $summary, int $messageId, ?int $replyId): string
{
    $pickerEmojis = ["\u{1F44D}", "\u{2764}\u{FE0F}", "\u{1F602}", "\u{1F389}", "\u{1F525}", "\u{1F44F}", "\u{1F64F}", "\u{2705}", "\u{1F440}", "\u{1F4A1}"];
    ob_start();
    ?>
    <div class="chat-reactions" data-message-id="<?= $messageId ?>"<?= $replyId !== null ? ' data-reply-id="' . (int) $replyId . '"' : '' ?>>
      <div class="chat-reaction-summary">
        <?php foreach ($summary as $reaction): ?>
          <?php
            $emoji = (string) ($reaction['emoji'] ?? '');
            $reactors = $reaction['reactors'] ?? [];
            $reactorCopy = is_array($reactors) && $reactors !== []
                ? implode(', ', array_map('strval', $reactors))
                : 'No reactions yet';
            $reactionTitle = $emoji . ' ' . $reactorCopy;
          ?>
          <button
            type="button"
            class="chat-reaction-chip<?= !empty($reaction['reacted']) ? ' is-active' : '' ?>"
            data-emoji="<?= htmlspecialchars($emoji, ENT_QUOTES) ?>"
            data-reaction-details="<?= htmlspecialchars($reactionTitle, ENT_QUOTES) ?>"
            aria-label="<?= htmlspecialchars($reactionTitle, ENT_QUOTES) ?>"
          >
            <span class="chat-reaction-emoji"><?= htmlspecialchars($emoji) ?></span>
            <span class="chat-reaction-count"><?= (int) ($reaction['count'] ?? 0) ?></span>
          </button>
        <?php endforeach; ?>
        <div class="chat-reaction-picker-wrap">
          <button type="button" class="chat-reaction-trigger" aria-label="Add reaction" aria-expanded="false">
            <i class="far fa-smile"></i>
          </button>
          <div class="chat-reaction-picker" hidden>
            <?php foreach ($pickerEmojis as $emoji): ?>
              <button type="button" class="chat-reaction-add" data-emoji="<?= htmlspecialchars($emoji, ENT_QUOTES) ?>"><?= htmlspecialchars($emoji) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

function renderReply($row, $userId, $userType, array $reactionSummary, int $messageId)
{
    $isMine = ((int) $row['user_id'] === (int) $userId);
    $class = $isMine ? 'reply mine' : 'reply theirs';
    global $base_url;
    $avatarUrl = avatar_resolve_url($row['picture'] ?? '', $row['sso_avatar_url'] ?? '', $base_url, dirname(__DIR__));
    $isDeleted = !empty($row['deleted_for_everyone_at']);
    $canEdit = $isMine && !$isDeleted;
    $canDelete = ($isMine || $userType === 'admin') && !$isDeleted;
    ob_start();
    ?>
    <div
      class="<?= $class ?><?= $isDeleted ? ' reply-deleted' : '' ?>"
      data-reply-id="<?= (int) $row['id'] ?>"
      data-can-edit="<?= $canEdit ? '1' : '0' ?>"
      data-can-delete="<?= $canDelete ? '1' : '0' ?>"
      data-reply-text="<?= htmlspecialchars((string) $row['reply'], ENT_QUOTES) ?>"
    >
      <div class="reply-head">
        <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= htmlspecialchars($row['username']) ?>" class="reply-avatar">
        <div class="reply-meta">
          <strong><?= htmlspecialchars($row['username']) ?></strong>
          <span><?= htmlspecialchars($row['userType']) ?></span>
          <span><?= htmlspecialchars(messengerFriendlyTime($row['sent_at'] ?? '')) ?></span>
          <?php if ($isDeleted): ?>
            <span class="badge badge-secondary">Removed</span>
          <?php endif; ?>
        </div>
        <?php if ($canEdit || $canDelete): ?>
          <div class="reply-tools ml-auto">
            <button
              type="button"
              class="btn btn-xs btn-outline-light reply-menu-trigger"
              aria-label="Message actions"
              aria-expanded="false"
            >
              <i class="fas fa-ellipsis-h"></i>
            </button>
            <div class="reply-menu-dropdown" hidden>
              <?php if ($canEdit): ?>
                <button
                  type="button"
                  class="reply-menu-item reply-edit-trigger"
                  data-reply-id="<?= (int) $row['id'] ?>"
                  data-reply-text="<?= htmlspecialchars((string) $row['reply'], ENT_QUOTES) ?>"
                >
                  Edit
                </button>
              <?php endif; ?>
              <?php if ($canDelete): ?>
                <button type="button" class="reply-menu-item reply-menu-item-danger reply-delete-trigger" data-reply-id="<?= (int) $row['id'] ?>">
                  Remove
                </button>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <?php if ($isDeleted): ?>
        <div class="reply-deleted-copy">
          This message was removed for everyone<?= !empty($row['deleted_by_username']) ? ' by ' . htmlspecialchars((string) $row['deleted_by_username']) : '' ?>.
        </div>
      <?php else: ?>
        <div class="chat-bubble-body"><?= nl2br(htmlspecialchars($row['reply'])) ?></div>
        <?php
        if (!empty($row['attachment'])) {
            echo renderAttachments($row['attachment'], app_url('inbox/uploads/reply_attachments/'), 'reply');
        }
        echo renderReactionBar($reactionSummary, $messageId, (int) ($row['id'] ?? 0));
        ?>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function renderOriginalMessageBubble($message, $originalReplyClass, $originalAvatarUrl, $originalDisplayName, array $reactionSummary)
{
    ob_start();
    ?>
    <div class="<?= htmlspecialchars($originalReplyClass, ENT_QUOTES) ?>">
      <div class="reply-head">
        <img src="<?= htmlspecialchars($originalAvatarUrl) ?>" alt="<?= htmlspecialchars($originalDisplayName) ?>" class="reply-avatar">
        <div class="reply-meta">
          <strong><?= htmlspecialchars($originalDisplayName) ?></strong>
          <span>Started the conversation</span>
          <span><?= htmlspecialchars(messengerFriendlyTime($message['sent_at'] ?? '')) ?></span>
        </div>
      </div>
      <div class="chat-bubble-body"><?= nl2br(htmlspecialchars($message['message'])) ?></div>
      <?php
      if (!empty($message['attachment'])) {
          echo renderAttachments($message['attachment'], app_url('inbox/uploads/contact_attachments/'), 'contact');
      }
      echo renderReactionBar($reactionSummary, (int) ($message['id'] ?? 0), null);
      ?>
    </div>
    <?php
    return ob_get_clean();
}

$messageReactionSummary = mailboxFetchReactionSummary($conn, (int) $message['id'], null, $userId);
$replyReactionSummary = [];
foreach ($replies as $reply) {
    $replyReactionSummary[(int) ($reply['id'] ?? 0)] = mailboxFetchReactionSummary(
        $conn,
        (int) $message['id'],
        (int) ($reply['id'] ?? 0),
        $userId
    );
}

if ($onlyConversation) {
    ?>
    <div id="conversationWrapper" class="mt-4">
      <div class="conversation-scroll">
        <?= renderOriginalMessageBubble($message, $originalReplyClass, $originalAvatarUrl, $originalDisplayName, $messageReactionSummary) ?>
        <?php foreach ($replies as $reply): ?>
            <?= renderReply($reply, $userId, (string) $userType, $replyReactionSummary[(int) ($reply['id'] ?? 0)] ?? [], (int) $message['id']) ?>
        <?php endforeach; ?>
        <div class="chat-typing-indicator reply theirs" id="threadTypingIndicator" hidden>
          <span class="chat-typing-dots"><span></span><span></span><span></span></span>
          <span class="chat-typing-copy">Someone is typing...</span>
        </div>
      </div>
    </div>
    <?php
    exit;
}
?>
<div class="chat-shell" data-message-id="<?= (int) $id ?>" data-presence-user-id="<?= (int) $chatPresenceUserId ?>" data-participants="<?= htmlspecialchars(json_encode($participantMentionItems ?: $participantMentions), ENT_QUOTES, 'UTF-8') ?>">
  <div class="mailbox-read-info mailbox-read-info--compact chat-thread-header">
    <div class="chat-thread-hero">
      <div class="chat-thread-primary">
        <div class="chat-thread-avatar-wrap">
          <img src="<?= htmlspecialchars($chatAvatarUrl) ?>" alt="<?= htmlspecialchars($chatTitle) ?>" class="chat-thread-avatar">
          <span class="chat-thread-status-dot is-<?= htmlspecialchars($currentFolder === 'trash' ? 'offline' : $chatPresence['class']) ?>" aria-hidden="true"></span>
        </div>
        <div>
          <h2 class="mailbox-read-subject"><?= htmlspecialchars($chatTitle) ?></h2>
          <div class="chat-thread-presence"><?= htmlspecialchars($chatStatusCopy) ?></div>
          <?php if ($chatMetaCopy !== ''): ?>
            <div class="chat-thread-meta-line">
              <span><?= htmlspecialchars($chatMetaCopy) ?></span>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="chat-thread-options">
        <div class="dropdown">
          <button
            type="button"
            class="btn btn-sm chat-thread-options-btn conversation-options-trigger"
            aria-label="Conversation options"
            aria-expanded="false"
            data-toggle="dropdown"
            data-message-id="<?= (int) $id ?>"
            data-is-group="<?= $isGroupThread ? '1' : '0' ?>"
            data-group-name="<?= htmlspecialchars($chatTitle, ENT_QUOTES) ?>"
            data-group-muted="<?= !empty($groupMemberState['muted_at']) ? '1' : '0' ?>"
            data-group-left="<?= !empty($groupMemberState['left_at']) ? '1' : '0' ?>"
            data-can-send="<?= $currentFolder !== 'trash' && (!$isGroupThread || empty($groupMemberState['left_at'])) ? '1' : '0' ?>"
          >
            <i class="fas fa-ellipsis-h"></i>
          </button>
          <div class="dropdown-menu dropdown-menu-right chat-thread-options-menu">
            <?php if ($isBubbleMode): ?>
              <button type="button" class="dropdown-item conversation-open-messenger-trigger"><i class="fas fa-external-link-alt mr-2"></i>Open in Messenger</button>
            <?php else: ?>
              <button type="button" class="dropdown-item conversation-open-bubble-trigger"><i class="far fa-comment-dots mr-2"></i>Open Bubble</button>
            <?php endif; ?>
            <div class="dropdown-divider"></div>
            <?php if ($isGroupThread && empty($groupMemberState['left_at'])): ?>
              <button type="button" class="dropdown-item conversation-see-members-trigger"><i class="fas fa-address-book mr-2"></i>See Members</button>
              <button type="button" class="dropdown-item conversation-add-member-trigger"><i class="fas fa-user-plus mr-2"></i>Add member</button>
              <button type="button" class="dropdown-item conversation-edit-group-trigger"><i class="fas fa-users-cog mr-2"></i>Edit group</button>
              <button type="button" class="dropdown-item conversation-mute-trigger"><i class="fas fa-bell-slash mr-2"></i><?= !empty($groupMemberState['muted_at']) ? 'Unmute' : 'Mute' ?></button>
              <div class="dropdown-divider"></div>
              <button type="button" class="dropdown-item text-danger conversation-leave-trigger"><i class="fas fa-sign-out-alt mr-2"></i>Leave group</button>
            <?php elseif ($isGroupThread): ?>
              <button type="button" class="dropdown-item conversation-see-members-trigger"><i class="fas fa-address-book mr-2"></i>See Members</button>
              <button type="button" class="dropdown-item text-danger conversation-delete-trigger"><i class="far fa-trash-alt mr-2"></i>Delete/Hide</button>
            <?php else: ?>
              <button type="button" class="dropdown-item text-danger conversation-delete-trigger"><i class="far fa-trash-alt mr-2"></i>Delete conversation</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="mailbox-read-meta chat-thread-participants">
      <div class="mailbox-read-meta-item">
        <span>Started by</span>
        <strong><?= htmlspecialchars($message['user_name']) ?></strong>
      </div>
      <div class="mailbox-read-meta-item">
        <span>Conversation with</span>
        <strong><?= htmlspecialchars(trim((string) ($message['recipient_names'] ?? '')) !== '' ? $message['recipient_names'] : 'Admin') ?></strong>
      </div>
      <div class="mailbox-read-meta-item">
        <span>Members</span>
        <strong><?= htmlspecialchars(implode(', ', $participantLabels)) ?></strong>
      </div>
    </div>
  </div>

  <div class="mailbox-controls with-border text-right mb-3 mailbox-detail-actions">
    <div class="btn-group mailbox-tools">
      <?php if ($currentFolder === 'trash'): ?>
        <button type="button" class="btn btn-success btn-sm mailbox-restore-trigger" data-id="<?= (int) $id ?>">
          <i class="fas fa-undo mr-1"></i> Restore
        </button>
        <?php if ($canDeleteForEveryone): ?>
          <button type="button" class="btn btn-danger btn-sm mailbox-delete-trigger" data-id="<?= (int) $id ?>" data-can-delete-everyone="1">
            <i class="fas fa-trash-alt mr-1"></i> Delete forever
          </button>
        <?php endif; ?>
      <?php else: ?>
        <button type="button" class="btn btn-default btn-sm mailbox-delete-trigger" data-id="<?= (int) $id ?>" data-can-delete-everyone="<?= $canDeleteForEveryone ? '1' : '0' ?>">
          <i class="far fa-trash-alt mr-1"></i> Archive
        </button>
      <?php endif; ?>
    </div>
  </div>

  <div class="chat-status-row">
    <div class="mailbox-thread-heading">
      <h6 class="text-uppercase">Chat flow</h6>
    </div>
  </div>

  <div id="conversationWrapper" class="mt-4">
    <div class="conversation-scroll">
      <?= renderOriginalMessageBubble($message, $originalReplyClass, $originalAvatarUrl, $originalDisplayName, $messageReactionSummary) ?>
      <?php foreach ($replies as $reply): ?>
          <?= renderReply($reply, $userId, (string) $userType, $replyReactionSummary[(int) ($reply['id'] ?? 0)] ?? [], (int) $message['id']) ?>
      <?php endforeach; ?>
      <div class="chat-typing-indicator reply theirs" id="threadTypingIndicator" hidden>
        <span class="chat-typing-dots"><span></span><span></span><span></span></span>
        <span class="chat-typing-copy">Someone is typing...</span>
      </div>
    </div>
  </div>

  <?php if ($currentFolder !== 'trash' && (!$isGroupThread || empty($groupMemberState['left_at']))): ?>
    <div class="mt-3">
      <form id="replyForm" enctype="multipart/form-data" class="reply-form-shell chat-composer-shell" data-no-loader="true">
        <div class="chat-mentions-shell">
          <textarea id="replyText" name="reply" class="form-control chat-reply-textarea" rows="2" placeholder="Write a message, use @ to mention someone..."></textarea>
          <div class="chat-composer-tool-stack">
            <button type="submit" class="chat-composer-tool-btn chat-composer-send-btn" id="replySendBtn" aria-label="Send message" title="Send">
              <i class="fas fa-paper-plane"></i>
            </button>
            <button type="button" class="chat-composer-tool-btn" id="attachBtn" aria-label="Attach files" title="Attach files">
              <i class="fas fa-paperclip"></i>
            </button>
            <button type="button" class="chat-composer-tool-btn emoji-trigger" id="replyEmojiTrigger" aria-label="Open emoji picker" aria-expanded="false" aria-controls="replyEmojiMenu" title="Emoji">
              <i class="far fa-smile"></i>
            </button>
            <div class="emoji-menu" id="replyEmojiMenu" hidden></div>
          </div>
          <div class="chat-mention-menu" id="replyMentionMenu" hidden></div>
          <div id="replyFilePreview" class="file-preview" aria-live="polite"></div>
        </div>

        <input type="file" name="replyAttachments[]" id="replyAttachments" multiple hidden>
        <div class="attachment-limit-hint mt-2" id="replyAttachmentHint">
          Up to <?= htmlspecialchars((string) $attachmentLimits['max_file_count'], ENT_QUOTES) ?> files, <?= htmlspecialchars((string) $attachmentLimits['max_file_size_label'], ENT_QUOTES) ?> each, <?= htmlspecialchars((string) $attachmentLimits['max_total_size_label'], ENT_QUOTES) ?> total.
        </div>
        <div class="attachment-validation-message mt-1" id="replyAttachmentError" role="alert" aria-live="polite" hidden></div>

        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($message['user_email'], ENT_QUOTES) ?>">
        <input type="hidden" name="subject" value="<?= htmlspecialchars($message['subject'], ENT_QUOTES) ?>">
        <input type="hidden" name="message" value="<?= htmlspecialchars($message['message'], ENT_QUOTES) ?>">
        <input type="hidden" name="mentioned_user_ids" id="mentionedUserIds" value="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES) ?>">
      </form>
    </div>
  <?php else: ?>
    <div class="alert alert-light border mt-4 mb-0">
      <?= $isGroupThread && !empty($groupMemberState['left_at']) ? 'You left this group. You can delete it from your chat list.' : 'This chat is archived. Restore it if you want to jump back in.' ?>
    </div>
  <?php endif; ?>
</div>

<script>
(function() {
    window.KODUSMessengerAttachmentLimits = <?= json_encode($attachmentLimits, JSON_UNESCAPED_SLASHES) ?>;
    const replyForm = document.getElementById('replyForm');
    const attachBtn = document.getElementById('attachBtn');
    const fileInput = document.getElementById('replyAttachments');
    const preview = document.getElementById('replyFilePreview');
    const replyText = document.getElementById('replyText');
    const emojiTrigger = document.getElementById('replyEmojiTrigger');
    const emojiMenu = document.getElementById('replyEmojiMenu');
    const mentionMenu = document.getElementById('replyMentionMenu');
    const attachmentError = document.getElementById('replyAttachmentError');
    const shell = document.querySelector('.chat-shell');
    const participantData = (() => {
        if (!shell) {
            return [];
        }
        try {
            return JSON.parse(shell.getAttribute('data-participants') || '[]');
        } catch (error) {
            return [];
        }
    })().map(function(item) {
        if (typeof item === 'string') {
            return { id: 0, name: item, mention: item.replace(/\s+/g, '') };
        }
        return {
            id: Number(item.id || 0),
            name: String(item.name || item.mention || '').trim(),
            mention: String(item.mention || item.name || '').replace(/\s+/g, '')
        };
    }).filter(function(item) {
        return item.name && item.mention;
    });
    const composerEmojis = ['😀', '😂', '😊', '😍', '😉', '👍', '👏', '🙏', '🎉', '🔥', '❤️', '✨', '📎', '📩', '✅', '🤝', '🙌', '😎', '📌', '💡'];
    let activeMentionIndex = 0;
    let selectedReplyFiles = [];
    const replyPreviewUrls = new Map();

    if (!replyForm || !attachBtn || !fileInput || !preview || !replyText || !emojiTrigger || !emojiMenu || !mentionMenu) {
        return;
    }

    function insertTextAtCursor(field, text) {
        const start = field.selectionStart ?? field.value.length;
        const end = field.selectionEnd ?? field.value.length;
        field.value = field.value.slice(0, start) + text + field.value.slice(end);
        field.focus();
        const cursor = start + text.length;
        field.setSelectionRange(cursor, cursor);
        field.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function closeEmojiMenu() {
        window.KODUSEmojiPicker?.close(emojiTrigger, emojiMenu);
    }

    function positionReplyEmojiMenu() {
        window.KODUSEmojiPicker?.position(emojiTrigger, emojiMenu);
    }

    function closeMentionMenu() {
        mentionMenu.hidden = true;
        mentionMenu.innerHTML = '';
        activeMentionIndex = 0;
    }

    function replaceMentionToken(field, mentionValue, mentionUserId) {
        const caret = field.selectionStart ?? field.value.length;
        const before = field.value.slice(0, caret);
        const after = field.value.slice(caret);
        const match = before.match(/(^|\s)@([^\s@]*)$/);
        if (!match) {
            return;
        }
        const startIndex = before.length - match[0].length + match[1].length;
        field.value = before.slice(0, startIndex) + '@' + mentionValue + ' ' + after;
        const nextCaret = startIndex + mentionValue.length + 2;
        field.focus();
        field.setSelectionRange(nextCaret, nextCaret);
        field.dispatchEvent(new Event('input', { bubbles: true }));
        if (mentionUserId) {
            const mentioned = new Set((replyForm.dataset.mentionedUserIds || '').split(',').filter(Boolean));
            mentioned.add(String(mentionUserId));
            replyForm.dataset.mentionedUserIds = Array.from(mentioned).join(',');
            const mentionedField = document.getElementById('mentionedUserIds');
            if (mentionedField) {
                mentionedField.value = replyForm.dataset.mentionedUserIds;
            }
        }
    }

    function setActiveMention(index) {
        const items = Array.from(mentionMenu.querySelectorAll('.chat-mention-item'));
        if (!items.length) {
            activeMentionIndex = 0;
            return;
        }
        activeMentionIndex = (index + items.length) % items.length;
        items.forEach(function(item, itemIndex) {
            item.classList.toggle('is-active', itemIndex === activeMentionIndex);
            item.setAttribute('aria-selected', itemIndex === activeMentionIndex ? 'true' : 'false');
        });
        items[activeMentionIndex].scrollIntoView({ block: 'nearest' });
    }

    function chooseActiveMention() {
        const items = Array.from(mentionMenu.querySelectorAll('.chat-mention-item'));
        if (!items.length || mentionMenu.hidden) {
            return false;
        }
        items[activeMentionIndex]?.click();
        return true;
    }

    function renderMentionMenu(query) {
        const normalizedQuery = String(query || '').toLowerCase();
        const matches = participantData.filter(function(member) {
            return member.name.toLowerCase().includes(normalizedQuery)
                || member.mention.toLowerCase().includes(normalizedQuery);
        }).slice(0, 6);

        if (!matches.length) {
            closeMentionMenu();
            return;
        }

        mentionMenu.innerHTML = '';
        matches.forEach(function(member, index) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'chat-mention-item';
            button.setAttribute('role', 'option');
            button.dataset.userId = String(member.id || 0);
            button.dataset.mention = member.mention;
            button.textContent = '@' + member.mention;
            if (member.name !== member.mention) {
                button.title = member.name;
            }
            button.addEventListener('mouseenter', function() {
                setActiveMention(index);
            });
            button.addEventListener('click', function(event) {
                event.preventDefault();
                replaceMentionToken(replyText, member.mention, member.id);
                closeMentionMenu();
            });
            mentionMenu.appendChild(button);
        });
        mentionMenu.hidden = false;
        setActiveMention(0);
    }

    composerEmojis.forEach(function(emoji) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'emoji-item';
        button.textContent = emoji;
        button.addEventListener('click', function(event) {
            event.preventDefault();
            insertTextAtCursor(replyText, emoji);
            closeEmojiMenu();
        });
        emojiMenu.appendChild(button);
    });
    window.KODUSEmojiPicker?.enhance(emojiMenu);

    attachBtn.addEventListener('click', function() {
        fileInput.click();
    });

    function syncReplyFileInput() {
        const transfer = new DataTransfer();
        selectedReplyFiles.forEach(function(file) {
            transfer.items.add(file);
        });
        fileInput.files = transfer.files;
    }

    function clearReplyPreviewUrls() {
        replyPreviewUrls.forEach(function(url) {
            URL.revokeObjectURL(url);
        });
        replyPreviewUrls.clear();
    }

    function getReplyPreviewUrl(file) {
        const key = file.name + ':' + file.size + ':' + file.lastModified;
        if (!replyPreviewUrls.has(key)) {
            replyPreviewUrls.set(key, URL.createObjectURL(file));
        }
        return replyPreviewUrls.get(key);
    }

    function isReplyMediaFile(file, ext) {
        return file.type.startsWith('image/')
            || file.type.startsWith('video/')
            || file.type.startsWith('audio/')
            || ['avif', 'webp', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm', 'ogv', 'mov', 'm4v', 'mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'].includes(ext);
    }

    function setReplyPreviewState() {
        preview.parentElement?.classList.toggle('has-file-preview', selectedReplyFiles.length > 0);
    }

    function renderReplyFilePreview() {
        preview.innerHTML = '';
        const isStacked = selectedReplyFiles.length > 4;
        const visibleFiles = isStacked ? selectedReplyFiles.slice(0, 4) : selectedReplyFiles;
        preview.classList.toggle('is-stacked', isStacked);
        setReplyPreviewState();

        visibleFiles.forEach(function(file, index) {
            const card = document.createElement('div');
            card.className = 'file-card';
            card.style.zIndex = String(visibleFiles.length - index + 1);
            const ext = (file.name.split('.').pop() || '').toLowerCase();

            if (file.type.startsWith('image/') || ['avif', 'webp', 'jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
                const img = document.createElement('img');
                img.src = getReplyPreviewUrl(file);
                img.alt = '';
                card.appendChild(img);
            } else if (file.type.startsWith('video/') || ['mp4', 'webm', 'ogv', 'mov', 'm4v'].includes(ext)) {
                const video = document.createElement('video');
                video.src = getReplyPreviewUrl(file);
                video.controls = true;
                video.preload = 'metadata';
                card.appendChild(video);
            } else if (file.type.startsWith('audio/') || ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'].includes(ext)) {
                const audio = document.createElement('audio');
                audio.src = getReplyPreviewUrl(file);
                audio.controls = true;
                audio.preload = 'metadata';
                card.appendChild(audio);
            } else {
                const iconWrap = document.createElement('span');
                iconWrap.className = 'file-card-icon';
                const icon = document.createElement('i');
                icon.className = file.type.startsWith('audio/')
                    ? 'fas fa-file-audio'
                    : (file.type.startsWith('video/') ? 'fas fa-file-video' : 'fas fa-file');
                iconWrap.appendChild(icon);
                card.appendChild(iconWrap);
            }

            if (!isReplyMediaFile(file, ext)) {
                const label = document.createElement('span');
                label.className = 'file-card-name';
                label.textContent = file.name;
                card.appendChild(label);
            }

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'file-card-remove';
            remove.setAttribute('aria-label', 'Remove ' + file.name);
            remove.textContent = '×';
            remove.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                selectedReplyFiles.splice(index, 1);
                clearReplyPreviewUrls();
                syncReplyFileInput();
                renderReplyFilePreview();
                window.setAttachmentValidationMessage?.(attachmentError, '');
                replyText.focus();
            });
            card.appendChild(remove);

            preview.appendChild(card);
        });

        if (isStacked) {
            const count = document.createElement('div');
            count.className = 'file-card-count';
            count.textContent = '+' + (selectedReplyFiles.length - visibleFiles.length);
            count.title = selectedReplyFiles.length + ' attachments selected';
            preview.appendChild(count);
        }
    }

    replyText.addEventListener('input', function() {
        const caret = replyText.selectionStart ?? replyText.value.length;
        const before = replyText.value.slice(0, caret);
        const match = before.match(/(^|\s)@([^\s@]*)$/);
        if (!match) {
            closeMentionMenu();
            return;
        }
        renderMentionMenu(match[2] || '');
    });

    replyText.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeEmojiMenu();
            closeMentionMenu();
            return;
        }

        if (!mentionMenu.hidden && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            event.preventDefault();
            setActiveMention(activeMentionIndex + (event.key === 'ArrowDown' ? 1 : -1));
            return;
        }

        if (!mentionMenu.hidden && (event.key === 'Tab' || event.key === 'Enter')) {
            event.preventDefault();
            chooseActiveMention();
            return;
        }

        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            closeMentionMenu();
            replyForm.requestSubmit();
        }
    });

    emojiTrigger.addEventListener('click', function(event) {
        event.preventDefault();
        event.stopPropagation();
        closeMentionMenu();
        window.KODUSEmojiPicker?.toggle(emojiTrigger, emojiMenu);
    });

    window.addEventListener('resize', function() {
        if (!emojiMenu.hidden) {
            positionReplyEmojiMenu();
        }
    }, { passive: true });

    document.addEventListener('click', function(event) {
        if (!emojiMenu.contains(event.target) && event.target !== emojiTrigger && !emojiTrigger.contains(event.target)) {
            closeEmojiMenu();
        }
        if (!mentionMenu.contains(event.target) && event.target !== replyText) {
            closeMentionMenu();
        }
    });

    fileInput.addEventListener('change', function() {
        const nextFiles = selectedReplyFiles.concat(Array.from(fileInput.files || []));
        const validation = window.validateMessengerAttachments
            ? window.validateMessengerAttachments(nextFiles)
            : { valid: true, message: '' };
        if (!validation.valid) {
            fileInput.value = '';
            window.setAttachmentValidationMessage?.(attachmentError, validation.message);
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Attachment too large',
                    text: validation.message
                });
            }
            return;
        }

        selectedReplyFiles = nextFiles;
        window.setAttachmentValidationMessage?.(attachmentError, '');
        syncReplyFileInput();
        renderReplyFilePreview();
        replyText.focus();
    });

    replyForm.addEventListener('reset', function() {
        window.setTimeout(function() {
            selectedReplyFiles = [];
            clearReplyPreviewUrls();
            syncReplyFileInput();
            renderReplyFilePreview();
            setReplyPreviewState();
            window.setAttachmentValidationMessage?.(attachmentError, '');
        }, 0);
    });
})();
</script>
