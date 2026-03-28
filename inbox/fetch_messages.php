<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
include('../config.php');
require_once __DIR__ . '/mailbox_helpers.php';
mailboxEnsureSchema($conn);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'] ?? null;
$userEmail = $_SESSION['email'] ?? null;
$folder = mailboxGetFolder($_GET['folder'] ?? 'inbox');
$trashPredicate = mailboxTrashPredicate($folder, 'mr');

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
            cm.user_email = ?
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
    $stmt->bind_param("is", $userId, $userEmail);
} else {
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
$result = $stmt->get_result();

if ($result->num_rows === 0): ?>
    <div class="mailbox-empty">
        <img src="../dist/img/empty-inbox.png" alt="Empty Inbox">
        <p class="mb-1"><strong><?= $folder === 'trash' ? 'Trash is empty' : 'No messages yet' ?></strong></p>
        <p class="mb-0"><?= $folder === 'trash' ? 'Messages you delete for yourself will show up here.' : 'New conversations will show up here.' ?></p>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
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
                    $avatarUrl = '../dist/img/default.webp';
                    $avatarPath = __DIR__ . '/../dist/img/' . (string) $displayPicture;
                    if (!empty($displayPicture) && file_exists($avatarPath)) {
                        $avatarUrl = '../dist/img/' . rawurlencode((string) $displayPicture);
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
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <div class="mailbox-empty" id="mailboxNoResults" style="display:none;">
        <i class="far fa-envelope-open fa-3x mb-3"></i>
        <p class="mb-1"><strong>No matching messages</strong></p>
        <p class="mb-0">Try another search or filter.</p>
    </div>
<?php endif;

if ($stmt) {
    $stmt->close();
}
