<?php
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../base_url.php';
require_once __DIR__ . '/mailbox_helpers.php';
security_bootstrap_session();
include('../config.php');
mailboxEnsureSchema($conn);

$id = intval($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'] ?? 0;
$userType = $_SESSION['user_type'] ?? null;
$userEmail = $_SESSION['email'] ?? null;
$currentFolder = mailboxGetFolder($_GET['folder'] ?? 'inbox');
$onlyConversation = isset($_GET['only_conversation']) && ($_GET['only_conversation'] == '1' || $_GET['only_conversation'] === 'true');

if (!$id || !$userId) {
    http_response_code(400);
    echo "<p>Invalid request.</p>";
    exit;
}

$query = "
    SELECT cm.*,
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
           )) AS recipient_names
    FROM contact_messages cm
    LEFT JOIN users sender_user ON sender_user.email = cm.user_email
    WHERE cm.id = ?
";

if ($userType === 'admin') {
    $query .= "
      AND (
          cm.user_email = ?
          OR EXISTS (
              SELECT 1
              FROM users u
              WHERE FIND_IN_SET(u.email, cm.recipient) > 0
                AND u.userType = 'admin'
          )
      )
      LIMIT 1
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $id, $userEmail);
} else {
    $query .= " AND (cm.user_email = ? OR FIND_IN_SET(?, cm.recipient) > 0) LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $id, $userEmail, $userEmail);
}

$stmt->execute();
$message = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$message) {
    echo "<p>Message not found.</p>";
    exit;
}

$currentUserEmail = (string) ($userEmail ?? '');
$originalSenderEmail = (string) ($message['user_email'] ?? '');
$originalIsMine = $currentUserEmail !== '' && strcasecmp($originalSenderEmail, $currentUserEmail) === 0;
$originalReplyClass = $originalIsMine ? 'reply mine' : 'reply theirs';
$originalDisplayName = $originalIsMine
    ? (trim((string) ($_SESSION['username'] ?? '')) !== '' ? (string) $_SESSION['username'] : 'You')
    : (trim((string) ($message['user_name'] ?? '')) !== '' ? (string) $message['user_name'] : $originalSenderEmail);
$defaultAvatarUrl = $base_url . 'kodus/dist/img/default.webp';
$originalAvatarUrl = $defaultAvatarUrl;
$originalPicture = (string) ($message['sender_picture'] ?? '');
$originalAvatarPath = __DIR__ . '/../dist/img/' . $originalPicture;
if ($originalPicture !== '' && file_exists($originalAvatarPath)) {
    $originalAvatarUrl = $base_url . 'kodus/dist/img/' . rawurlencode($originalPicture);
}
$canDeleteForEveryone = $userType === 'admin'
    || ($currentUserEmail !== '' && strcasecmp($originalSenderEmail, $currentUserEmail) === 0);

$stmt = $conn->prepare("
    SELECT r.*, u.username, u.userType, u.picture, deleter.username AS deleted_by_username
    FROM contact_replies r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN users deleter ON deleter.id = r.deleted_by_user_id
    WHERE r.message_id = ?
    ORDER BY r.sent_at ASC
");
$stmt->bind_param("i", $id);
$stmt->execute();
$replies = $stmt->get_result();
$stmt->close();
$replyCount = $replies ? $replies->num_rows : 0;

$stmt = $conn->prepare("
    INSERT INTO message_reads (message_id, user_id, is_read, read_at)
    VALUES (?, ?, 1, NOW())
    ON DUPLICATE KEY UPDATE is_read=1, read_at=NOW()
");
$stmt->bind_param("ii", $id, $userId);
$stmt->execute();
$stmt->close();

function renderAttachments($attachmentCsv, $basePath, $type = 'contact') {
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
        $path = $basePath . $file;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $html .= '<div class="attachment-thumb" data-attachments=\'' . $jsonFiles . '\' data-index="' . $i . '" data-type="' . $type . '">';

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'])) {
            $html .= '<img src="' . $path . '" alt="">';
        } else {
            $html .= '<div class="d-flex align-items-center justify-content-center" style="height:84px;"><i class="fas fa-file fa-2x text-secondary"></i></div>';
        }

        $html .= '<div class="filename">' . htmlspecialchars($file) . '</div>';
        $html .= '<a href="' . $path . '" download class="btn btn-xs btn-outline-secondary mt-2">Download</a>';
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

function renderReply($row, $userId, $userType) {
    $isMine = ((int) $row['user_id'] === (int) $userId);
    $class = $isMine ? 'reply mine' : 'reply theirs';
    global $base_url, $defaultAvatarUrl;
    $picture = (string) ($row['picture'] ?? '');
    $avatarUrl = $defaultAvatarUrl;
    $avatarPath = __DIR__ . '/../dist/img/' . $picture;
    if ($picture !== '' && file_exists($avatarPath)) {
        $avatarUrl = $base_url . 'kodus/dist/img/' . rawurlencode($picture);
    }
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
          <span><?= htmlspecialchars($row['sent_at']) ?></span>
          <?php if ($isDeleted): ?>
            <span class="badge badge-secondary">Deleted</span>
          <?php endif; ?>
        </div>
        <?php if ($canEdit || $canDelete): ?>
          <div class="reply-tools ml-auto">
            <button
              type="button"
              class="btn btn-xs btn-outline-light reply-menu-trigger"
              aria-label="Reply actions"
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
                  Delete
                </button>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <?php if ($isDeleted): ?>
        <div class="reply-deleted-copy">
          This reply was deleted for everyone<?= !empty($row['deleted_by_username']) ? ' by ' . htmlspecialchars((string) $row['deleted_by_username']) : '' ?>.
        </div>
      <?php else: ?>
        <div><?= nl2br(htmlspecialchars($row['reply'])) ?></div>
        <?php
        if (!empty($row['attachment'])) {
            echo renderAttachments($row['attachment'], 'uploads/reply_attachments/', 'reply');
        }
        ?>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function renderOriginalMessageBubble($message, $originalReplyClass, $originalAvatarUrl, $originalDisplayName) {
    ob_start();
    ?>
    <div class="<?= htmlspecialchars($originalReplyClass, ENT_QUOTES) ?>">
      <div class="reply-head">
        <img src="<?= htmlspecialchars($originalAvatarUrl) ?>" alt="<?= htmlspecialchars($originalDisplayName) ?>" class="reply-avatar">
        <div class="reply-meta">
          <strong><?= htmlspecialchars($originalDisplayName) ?></strong>
          <span>Started the conversation</span>
          <span><?= htmlspecialchars($message['sent_at']) ?></span>
        </div>
      </div>
      <div><?= nl2br(htmlspecialchars($message['message'])) ?></div>
      <?php
      if (!empty($message['attachment'])) {
          echo renderAttachments($message['attachment'], 'uploads/contact_attachments/', 'contact');
      }
      ?>
    </div>
    <?php
    return ob_get_clean();
}

if ($onlyConversation) {
    ?>
    <div id="conversationWrapper" class="mt-4">
      <div class="conversation-scroll">
        <?= renderOriginalMessageBubble($message, $originalReplyClass, $originalAvatarUrl, $originalDisplayName) ?>
        <?php if ($replies->num_rows > 0): ?>
            <?php while ($reply = $replies->fetch_assoc()): ?>
                <?= renderReply($reply, $userId, (string) $userType) ?>
            <?php endwhile; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php
    exit;
}
?>
<div class="mailbox-read-info mailbox-read-info--compact">
  <div class="mailbox-read-meta">
    <div class="mailbox-read-meta-item">
      <span>From</span>
      <strong><?= htmlspecialchars($message['user_name']) ?> &lt;<?= htmlspecialchars($message['user_email']) ?>&gt;</strong>
    </div>
    <div class="mailbox-read-meta-item">
      <span>To</span>
      <strong><?= htmlspecialchars(trim((string) ($message['recipient_names'] ?? '')) !== '' ? $message['recipient_names'] : ($message['recipient'] ?? 'Unknown')) ?></strong>
    </div>
    <div class="mailbox-read-meta-item">
      <span>Sent</span>
      <strong><?= htmlspecialchars($message['sent_at']) ?></strong>
    </div>
  </div>
</div>

<div class="mailbox-controls with-border text-right mb-3 mailbox-detail-actions">
  <div class="btn-group mailbox-tools">
    <a href="?compose=1" class="btn btn-default btn-sm mailbox-compose-trigger"><i class="fas fa-pen mr-1"></i> New Message</a>
    <?php if ($currentFolder === 'trash'): ?>
      <button type="button" class="btn btn-success btn-sm mailbox-restore-trigger" data-id="<?= (int) $id ?>">
        <i class="fas fa-undo mr-1"></i> Restore
      </button>
      <?php if ($canDeleteForEveryone): ?>
        <button type="button" class="btn btn-danger btn-sm mailbox-delete-trigger" data-id="<?= (int) $id ?>" data-can-delete-everyone="1">
          <i class="fas fa-trash-alt mr-1"></i> Delete
        </button>
      <?php endif; ?>
    <?php else: ?>
      <button type="button" class="btn btn-default btn-sm mailbox-delete-trigger" data-id="<?= (int) $id ?>" data-can-delete-everyone="<?= $canDeleteForEveryone ? '1' : '0' ?>">
        <i class="far fa-trash-alt mr-1"></i> Delete
      </button>
    <?php endif; ?>
  </div>
</div>

<div id="conversationWrapper" class="mt-4">
  <div class="mailbox-thread-heading">
    <h6 class="text-uppercase">Conversation</h6>
    <span class="mailbox-thread-count"><?= (int) ($replyCount + 1) ?> message<?= (int) ($replyCount + 1) === 1 ? '' : 's' ?></span>
  </div>
  <div class="conversation-scroll">
    <?= renderOriginalMessageBubble($message, $originalReplyClass, $originalAvatarUrl, $originalDisplayName) ?>
    <?php if ($replyCount > 0): ?>
        <?php while ($reply = $replies->fetch_assoc()): ?>
            <?= renderReply($reply, $userId, (string) $userType) ?>
        <?php endwhile; ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($currentFolder !== 'trash'): ?>
  <div class="mt-4">
    <form id="replyForm" enctype="multipart/form-data" class="reply-form-shell">
      <div class="reply-form-header">
        <h6>Reply</h6>
        <span class="reply-form-hint">Keep the thread focused and attach files only when needed.</span>
      </div>
      <div class="reply-compose-tools mb-2">
        <div class="emoji-tools">
          <button type="button" class="btn btn-default btn-sm emoji-trigger" id="replyEmojiTrigger" aria-label="Insert emoji" aria-expanded="false">
            <i class="far fa-smile mr-1"></i> Emoji
          </button>
          <div class="emoji-menu" id="replyEmojiMenu" hidden></div>
        </div>
      </div>
      <textarea id="replyText" name="reply" class="form-control" rows="4" placeholder="Write your reply..."></textarea>

      <div id="replyFilePreview" class="file-preview"></div>

      <div class="reply-actions">
        <div>
          <input type="file" name="replyAttachments[]" id="replyAttachments" multiple hidden>
          <button type="button" class="btn btn-default btn-sm" id="attachBtn">
            <i class="fas fa-paperclip mr-1"></i> Attach files
          </button>
        </div>

        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fas fa-paper-plane mr-1"></i> Send Reply
        </button>
      </div>

      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <input type="hidden" name="email" value="<?= htmlspecialchars($message['user_email'], ENT_QUOTES) ?>">
      <input type="hidden" name="subject" value="<?= htmlspecialchars($message['subject'], ENT_QUOTES) ?>">
      <input type="hidden" name="message" value="<?= htmlspecialchars($message['message'], ENT_QUOTES) ?>">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES) ?>">
    </form>
  </div>
<?php else: ?>
  <div class="alert alert-light border mt-4 mb-0">
    This conversation is in Trash. Restore it if you want to reply again.
  </div>
<?php endif; ?>

<script>
(function() {
    const attachBtn = document.getElementById('attachBtn');
    const fileInput = document.getElementById('replyAttachments');
    const preview = document.getElementById('replyFilePreview');
    const replyText = document.getElementById('replyText');
    const emojiTrigger = document.getElementById('replyEmojiTrigger');
    const emojiMenu = document.getElementById('replyEmojiMenu');
    const emojis = ['😀','😂','😊','😍','😉','👍','👏','🙏','🎉','🔥','❤️','✨','📎','📩','✅','🤝','🙌','😎','📌','💡'];

    if (!attachBtn || !fileInput || !preview || !replyText || !emojiTrigger || !emojiMenu) {
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
        emojiMenu.hidden = true;
        emojiTrigger.setAttribute('aria-expanded', 'false');
    }

    emojis.forEach(function(emoji) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'emoji-item';
        button.textContent = emoji;
        button.addEventListener('click', function(e) {
            e.preventDefault();
            insertTextAtCursor(replyText, emoji);
            closeEmojiMenu();
        });
        emojiMenu.appendChild(button);
    });

    attachBtn.addEventListener('click', function() {
        fileInput.click();
    });

    emojiTrigger.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const shouldOpen = emojiMenu.hidden;
        closeEmojiMenu();
        emojiMenu.hidden = !shouldOpen;
        emojiTrigger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    });

    emojiMenu.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    document.addEventListener('click', closeEmojiMenu);

    fileInput.addEventListener('change', function() {
        preview.innerHTML = '';
        const files = [...this.files];
        const maxPreview = 3;

        files.slice(0, maxPreview).forEach(function(file) {
            const card = document.createElement('div');
            card.classList.add('file-card');
            const ext = file.name.split('.').pop().toLowerCase();

            if (file.type.startsWith('image/') || ['avif', 'webp', 'jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                card.appendChild(img);
            } else {
                const icon = document.createElement('i');
                icon.classList.add('fas', 'fa-file');
                card.appendChild(icon);
            }

            preview.appendChild(card);
        });

        if (files.length > maxPreview) {
            const moreCard = document.createElement('div');
            moreCard.classList.add('file-card', 'more');
            moreCard.textContent = `+${files.length - maxPreview}`;
            preview.appendChild(moreCard);
        }
    });
})();
</script>
