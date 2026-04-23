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
$userName = trim((string) ($_SESSION['username'] ?? ''));
$folder = mailboxGetFolder($_GET['folder'] ?? 'inbox');
$trashPredicate = mailboxTrashPredicate($folder, 'mr');

if ($userType === 'admin') {
    $stmt = $conn->prepare("
        SELECT cm.*,
               COALESCE(reply_summary.latest_reply_at, cm.sent_at) AS latest_activity_at,
               COALESCE(mr.is_read, 0) AS user_read,
               sender_user.picture AS sender_picture,
               sender_user.sso_avatar_url AS sender_sso_avatar_url,
               TRIM(BOTH ', ' FROM CONCAT_WS(', ',
                   (
                       SELECT GROUP_CONCAT(DISTINCT u.username ORDER BY u.username SEPARATOR ', ')
                       FROM contact_message_recipients cmr
                       INNER JOIN users u ON u.id = cmr.user_id
                       WHERE cmr.message_id = cm.id
                   )
               )) AS recipient_names,
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
               ) AS recipient_sso_avatar_url
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
                FROM contact_message_recipients cmr
                INNER JOIN users u ON u.id = cmr.user_id
                WHERE cmr.message_id = cm.id
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
               sender_user.sso_avatar_url AS sender_sso_avatar_url,
               TRIM(BOTH ', ' FROM CONCAT_WS(', ',
                   (
                       SELECT GROUP_CONCAT(DISTINCT u.username ORDER BY u.username SEPARATOR ', ')
                       FROM contact_message_recipients cmr
                       INNER JOIN users u ON u.id = cmr.user_id
                       WHERE cmr.message_id = cm.id
                   )
               )) AS recipient_names,
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
               ) AS recipient_sso_avatar_url
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
           OR cm.user_name = ?
           OR EXISTS (
               SELECT 1
               FROM contact_message_recipients cmr
               WHERE cmr.message_id = cm.id
                 AND LOWER(cmr.recipient_email) = LOWER(?)
           ))
          AND {$trashPredicate}
        ORDER BY latest_activity_at DESC, cm.id DESC
    ");
    $stmt->bind_param("isss", $userId, $userEmail, $userName, $userEmail);
}

$stmt->execute();
$messages = db_stmt_fetch_all_assoc($stmt);

if ($messages === []): ?>
    <div class="mailbox-empty">
        <img src="../dist/img/empty-inbox.png" alt="Empty Inbox">
        <p class="mb-1"><strong><?= $folder === 'trash' ? 'Trash is empty' : 'No messages yet' ?></strong></p>
        <p class="mb-0"><?= $folder === 'trash' ? 'Messages you delete for yourself will show up here.' : 'New conversations will show up here.' ?></p>
    </div>
<?php else: ?>
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
                    $isSenderView = mailboxOwnerMatchesCurrentUser(
                        (string) ($row['user_email'] ?? ''),
                        (string) ($row['user_name'] ?? ''),
                        (string) ($_SESSION['email'] ?? ''),
                        (string) ($_SESSION['username'] ?? '')
                    );
                    $displayLabel = $isSenderView
                        ? ($recipientLabel !== '' ? $recipientLabel : 'Unknown')
                        : $senderLabel;
                    $name = htmlspecialchars($displayLabel);
                    $displayPicture = $isSenderView ? ($row['recipient_picture'] ?? '') : ($row['sender_picture'] ?? '');
                    $displaySsoAvatar = $isSenderView ? ($row['recipient_sso_avatar_url'] ?? '') : ($row['sender_sso_avatar_url'] ?? '');
                    $avatarUrl = avatar_resolve_url($displayPicture, $displaySsoAvatar, $base_url, dirname(__DIR__));
                    $subject = htmlspecialchars($row['subject'] ?? '(No Subject)');
                    $messageText = trim(preg_replace('/\s+/', ' ', (string) ($row['message'] ?? '')));
                    $snippet = htmlspecialchars(mb_strimwidth($messageText, 0, 28, '...'));
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
                    <?php if (in_array($folder, ['inbox', 'trash'], true)): ?>
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
                        <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= $name ?>" class="mailbox-avatar">
                    </td>
                    <td class="mailbox-name"><?= $name ?></td>
                    <td class="mailbox-subject">
                        <span class="mailbox-subject-line"><?= $subject ?></span><?php if ($snippet !== ''): ?><span class="mailbox-snippet"> - <?= $snippet ?></span><?php endif; ?>
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
<?php endif;

if ($stmt) {
    $stmt->close();
}
