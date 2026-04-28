<?php

function mailboxEnsureSchema(mysqli $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $dbName = $conn->query('SELECT DATABASE() AS db_name');
    $dbRow = $dbName ? $dbName->fetch_assoc() : null;
    $database = (string) ($dbRow['db_name'] ?? '');

    if ($database === '') {
        throw new RuntimeException('Unable to determine active database for mailbox schema checks.');
    }

    $columns = [];
    $stmt = $conn->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = 'message_reads'
    ");
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare mailbox schema check: ' . $conn->error);
    }

    $stmt->bind_param('s', $database);
    $stmt->execute();
    foreach (db_stmt_fetch_all_assoc($stmt) as $row) {
        $columns[] = (string) $row['COLUMN_NAME'];
    }
    $stmt->close();

    if (!in_array('is_trashed', $columns, true)) {
        if (!$conn->query("ALTER TABLE message_reads ADD COLUMN is_trashed TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read")) {
            throw new RuntimeException('Unable to add mailbox trash flag: ' . $conn->error);
        }
    }

    if (!in_array('trashed_at', $columns, true)) {
        if (!$conn->query("ALTER TABLE message_reads ADD COLUMN trashed_at DATETIME NULL DEFAULT NULL AFTER read_at")) {
            throw new RuntimeException('Unable to add mailbox trash timestamp: ' . $conn->error);
        }
    }

    $replyColumns = [];
    $replyStmt = $conn->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = 'contact_replies'
    ");
    if (!$replyStmt) {
        throw new RuntimeException('Unable to prepare mailbox reply schema check: ' . $conn->error);
    }

    $replyStmt->bind_param('s', $database);
    $replyStmt->execute();
    foreach (db_stmt_fetch_all_assoc($replyStmt) as $row) {
        $replyColumns[] = (string) $row['COLUMN_NAME'];
    }
    $replyStmt->close();

    if (!in_array('deleted_for_everyone_at', $replyColumns, true)) {
        if (!$conn->query("ALTER TABLE contact_replies ADD COLUMN deleted_for_everyone_at DATETIME NULL DEFAULT NULL AFTER attachment")) {
            throw new RuntimeException('Unable to add reply deleted timestamp: ' . $conn->error);
        }
    }

    if (!in_array('deleted_by_user_id', $replyColumns, true)) {
        if (!$conn->query("ALTER TABLE contact_replies ADD COLUMN deleted_by_user_id INT NULL DEFAULT NULL AFTER deleted_for_everyone_at")) {
            throw new RuntimeException('Unable to add reply deleted-by column: ' . $conn->error);
        }
    }

    if (!in_array('updated_at', $replyColumns, true)) {
        if (!$conn->query("ALTER TABLE contact_replies ADD COLUMN updated_at DATETIME NULL DEFAULT NULL AFTER sent_at")) {
            throw new RuntimeException('Unable to add reply updated timestamp: ' . $conn->error);
        }
    }

    if (!$conn->query("
        CREATE TABLE IF NOT EXISTS contact_message_recipients (
            id BIGINT NOT NULL AUTO_INCREMENT,
            message_id INT NOT NULL,
            user_id INT DEFAULT NULL,
            recipient_email VARCHAR(255) NOT NULL,
            recipient_name VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_contact_message_recipient (message_id, recipient_email),
            KEY idx_contact_message_recipient_user (user_id),
            KEY idx_contact_message_recipient_email (recipient_email),
            CONSTRAINT fk_contact_message_recipient_message
                FOREIGN KEY (message_id) REFERENCES contact_messages (id)
                ON DELETE CASCADE,
            CONSTRAINT fk_contact_message_recipient_user
                FOREIGN KEY (user_id) REFERENCES users (id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ")) {
        throw new RuntimeException('Unable to ensure mailbox recipient table: ' . $conn->error);
    }

    if (!$conn->query("
        CREATE TABLE IF NOT EXISTS message_reactions (
            id BIGINT NOT NULL AUTO_INCREMENT,
            message_id INT NOT NULL,
            reply_id INT DEFAULT NULL,
            target_key VARCHAR(32) NOT NULL DEFAULT 'message',
            user_id INT NOT NULL,
            emoji VARCHAR(32) NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_message_reaction_target (message_id, target_key, user_id, emoji),
            KEY idx_message_reaction_message (message_id),
            KEY idx_message_reaction_reply (reply_id),
            KEY idx_message_reaction_user (user_id),
            CONSTRAINT fk_message_reaction_message
                FOREIGN KEY (message_id) REFERENCES contact_messages (id)
                ON DELETE CASCADE,
            CONSTRAINT fk_message_reaction_reply
                FOREIGN KEY (reply_id) REFERENCES contact_replies (id)
                ON DELETE CASCADE,
            CONSTRAINT fk_message_reaction_user
                FOREIGN KEY (user_id) REFERENCES users (id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ")) {
        throw new RuntimeException('Unable to ensure mailbox reactions table: ' . $conn->error);
    }

    if (!$conn->query("ALTER TABLE message_reactions MODIFY COLUMN reply_id INT NULL DEFAULT NULL")) {
        throw new RuntimeException('Unable to relax mailbox reaction reply targets: ' . $conn->error);
    }

    $reactionColumns = [];
    $reactionStmt = $conn->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = 'message_reactions'
    ");
    if (!$reactionStmt) {
        throw new RuntimeException('Unable to prepare mailbox reaction schema check: ' . $conn->error);
    }
    $reactionStmt->bind_param('s', $database);
    $reactionStmt->execute();
    foreach (db_stmt_fetch_all_assoc($reactionStmt) as $row) {
        $reactionColumns[] = (string) $row['COLUMN_NAME'];
    }
    $reactionStmt->close();

    if (!in_array('target_key', $reactionColumns, true)) {
        if (!$conn->query("ALTER TABLE message_reactions ADD COLUMN target_key VARCHAR(32) NOT NULL DEFAULT 'message' AFTER reply_id")) {
            throw new RuntimeException('Unable to add mailbox reaction target key: ' . $conn->error);
        }
    }

    if (!$conn->query("
        UPDATE message_reactions
        SET target_key = CASE
            WHEN reply_id IS NULL THEN 'message'
            ELSE CONCAT('reply:', reply_id)
        END
        WHERE target_key = '' OR target_key IS NULL OR target_key = 'message'
    ")) {
        throw new RuntimeException('Unable to normalize mailbox reaction keys: ' . $conn->error);
    }

    $reactionIndexColumns = [];
    $indexStmt = $conn->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = 'message_reactions'
          AND INDEX_NAME = 'uq_message_reaction_target'
        ORDER BY SEQ_IN_INDEX
    ");
    if (!$indexStmt) {
        throw new RuntimeException('Unable to prepare mailbox reaction index check: ' . $conn->error);
    }
    $indexStmt->bind_param('s', $database);
    $indexStmt->execute();
    foreach (db_stmt_fetch_all_assoc($indexStmt) as $row) {
        $reactionIndexColumns[] = (string) $row['COLUMN_NAME'];
    }
    $indexStmt->close();

    if ($reactionIndexColumns !== ['message_id', 'target_key', 'user_id', 'emoji']) {
        if ($reactionIndexColumns !== []) {
            if (!$conn->query("ALTER TABLE message_reactions DROP INDEX uq_message_reaction_target")) {
                throw new RuntimeException('Unable to reset mailbox reaction uniqueness: ' . $conn->error);
            }
        }

        if (!$conn->query("ALTER TABLE message_reactions ADD UNIQUE KEY uq_message_reaction_target (message_id, target_key, user_id, emoji)")) {
            throw new RuntimeException('Unable to enforce mailbox reaction uniqueness: ' . $conn->error);
        }
    }

    if (!$conn->query("
        CREATE TABLE IF NOT EXISTS mailbox_typing_status (
            id BIGINT NOT NULL AUTO_INCREMENT,
            message_id INT NOT NULL,
            user_id INT NOT NULL,
            started_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_mailbox_typing_status (message_id, user_id),
            KEY idx_mailbox_typing_message (message_id),
            KEY idx_mailbox_typing_last_seen (last_seen_at),
            CONSTRAINT fk_mailbox_typing_message
                FOREIGN KEY (message_id) REFERENCES contact_messages (id)
                ON DELETE CASCADE,
            CONSTRAINT fk_mailbox_typing_user
                FOREIGN KEY (user_id) REFERENCES users (id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ")) {
        throw new RuntimeException('Unable to ensure mailbox typing table: ' . $conn->error);
    }

    $ensured = true;
}

function mailboxFormatRelativeActivity(int $timestamp): string
{
    $seconds = max(0, time() - $timestamp);
    if ($seconds < 60) {
        return 'just now';
    }

    $minutes = (int) floor($seconds / 60);
    if ($minutes < 60) {
        return $minutes . ' min' . ($minutes === 1 ? '' : 's') . ' ago';
    }

    $hours = (int) floor($minutes / 60);
    if ($hours < 24) {
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }

    $days = (int) floor($hours / 24);
    return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
}

function mailboxClassifyPresence(?string $lastActivity, int $isOnline): array
{
    if (!$lastActivity) {
        return [
            'label' => 'Offline',
            'class' => 'offline',
            'detail' => 'No activity recorded',
        ];
    }

    $lastActivityTs = strtotime($lastActivity);
    if ($lastActivityTs === false) {
        return [
            'label' => 'Offline',
            'class' => 'offline',
            'detail' => 'Activity unavailable',
        ];
    }

    $secondsSinceActive = time() - $lastActivityTs;
    if ($isOnline === 1 && $secondsSinceActive <= 300) {
        return [
            'label' => 'Online',
            'class' => 'online',
            'detail' => 'Active just now',
        ];
    }

    if ($secondsSinceActive <= 1800) {
        return [
            'label' => 'Idle',
            'class' => 'idle',
            'detail' => 'Last active ' . mailboxFormatRelativeActivity($lastActivityTs),
        ];
    }

    return [
        'label' => 'Offline',
        'class' => 'offline',
        'detail' => 'Last active ' . mailboxFormatRelativeActivity($lastActivityTs),
    ];
}

function mailboxGetFolder(?string $rawFolder): string
{
    return strtolower(trim((string) $rawFolder)) === 'trash' ? 'trash' : 'inbox';
}

function mailboxTrashPredicate(string $folder, string $alias = 'mr'): string
{
    $qualifiedAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'mr';
    return $folder === 'trash'
        ? "COALESCE({$qualifiedAlias}.is_trashed, 0) = 1"
        : "COALESCE({$qualifiedAlias}.is_trashed, 0) = 0";
}

function mailboxOwnerMatchesCurrentUser(?string $ownerEmail, ?string $ownerUsername, ?string $currentEmail, ?string $currentUsername): bool
{
    $ownerEmail = trim((string) $ownerEmail);
    $ownerUsername = trim((string) $ownerUsername);
    $currentEmail = trim((string) $currentEmail);
    $currentUsername = trim((string) $currentUsername);

    if ($ownerEmail !== '' && $currentEmail !== '' && strcasecmp($ownerEmail, $currentEmail) === 0) {
        return true;
    }

    if ($ownerUsername !== '' && $currentUsername !== '' && strcasecmp($ownerUsername, $currentUsername) === 0) {
        return true;
    }

    return false;
}

function mailboxSyncMessageRecipients(mysqli $conn, int $messageId, array $recipients): void
{
    $deleteStmt = $conn->prepare('DELETE FROM contact_message_recipients WHERE message_id = ?');
    if ($deleteStmt) {
        $deleteStmt->bind_param('i', $messageId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }

    if ($recipients === []) {
        return;
    }

    $insertStmt = $conn->prepare("
        INSERT INTO contact_message_recipients (
            message_id,
            user_id,
            recipient_email,
            recipient_name
        ) VALUES (?, ?, ?, ?)
    ");

    if (!$insertStmt) {
        throw new RuntimeException('Unable to prepare mailbox recipient sync: ' . $conn->error);
    }

    foreach ($recipients as $recipient) {
        $messageIdParam = $messageId;
        $userId = isset($recipient['user_id']) ? (int) $recipient['user_id'] : null;
        $recipientEmail = trim((string) ($recipient['email'] ?? ''));
        $recipientName = trim((string) ($recipient['username'] ?? $recipient['name'] ?? ''));
        if ($recipientEmail === '') {
            continue;
        }

        $insertStmt->bind_param('iiss', $messageIdParam, $userId, $recipientEmail, $recipientName);
        $insertStmt->execute();
    }

    $insertStmt->close();
}

function mailboxDeleteConversationForEveryone(mysqli $conn, int $messageId): bool
{
    $attachmentsToDelete = [];

    $collectCsvFiles = static function (?string $csv, string $baseDir) use (&$attachmentsToDelete): void {
        $files = array_filter(array_map('trim', explode(',', (string) $csv)));
        foreach ($files as $file) {
            if ($file === '') {
                continue;
            }
            $attachmentsToDelete[] = $baseDir . $file;
        }
    };

    $messageStmt = $conn->prepare("SELECT attachment FROM contact_messages WHERE id = ? LIMIT 1");
    if (!$messageStmt) {
        throw new RuntimeException('Unable to prepare message attachment lookup: ' . $conn->error);
    }

    $messageStmt->bind_param('i', $messageId);
    $messageStmt->execute();
    $message = db_stmt_fetch_one_assoc($messageStmt);
    $messageStmt->close();

    if (!$message) {
        return false;
    }

    $collectCsvFiles($message['attachment'] ?? '', __DIR__ . '/uploads/contact_attachments/');

    $replyStmt = $conn->prepare("SELECT attachment FROM contact_replies WHERE message_id = ?");
    if (!$replyStmt) {
        throw new RuntimeException('Unable to prepare reply attachment lookup: ' . $conn->error);
    }

    $replyStmt->bind_param('i', $messageId);
    $replyStmt->execute();
    foreach (db_stmt_fetch_all_assoc($replyStmt) as $reply) {
        $collectCsvFiles($reply['attachment'] ?? '', __DIR__ . '/uploads/reply_attachments/');
    }
    $replyStmt->close();

    $deleteStmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ? LIMIT 1");
    if (!$deleteStmt) {
        throw new RuntimeException('Unable to prepare message delete: ' . $conn->error);
    }

    $deleteStmt->bind_param('i', $messageId);
    $deleteStmt->execute();
    $deleted = $deleteStmt->affected_rows > 0;
    $deleteStmt->close();

    if (!$deleted) {
        return false;
    }

    foreach (array_unique($attachmentsToDelete) as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    return true;
}

function mailboxFetchReactionSummary(mysqli $conn, int $messageId, ?int $replyId, int $currentUserId): array
{
    if ($messageId <= 0) {
        return [];
    }

    $sql = "
        SELECT emoji,
               COUNT(*) AS reaction_count,
               MAX(CASE WHEN user_id = ? THEN 1 ELSE 0 END) AS reacted_by_current_user
        FROM message_reactions
        WHERE message_id = ?
          AND target_key = ?
        GROUP BY emoji
        ORDER BY reaction_count DESC, emoji ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $targetKey = $replyId === null ? 'message' : 'reply:' . (int) $replyId;
    $stmt->bind_param('iis', $currentUserId, $messageId, $targetKey);

    $stmt->execute();
    $rows = db_stmt_fetch_all_assoc($stmt);
    $stmt->close();

    $summary = [];
    foreach ($rows as $row) {
        $emoji = trim((string) ($row['emoji'] ?? ''));
        if ($emoji === '') {
            continue;
        }

        $summary[] = [
            'emoji' => $emoji,
            'count' => (int) ($row['reaction_count'] ?? 0),
            'reacted' => (int) ($row['reacted_by_current_user'] ?? 0) === 1,
        ];
    }

    return $summary;
}

function mailboxPreviewText(?string $text, ?string $attachmentCsv, bool $isDeleted = false): string
{
    if ($isDeleted) {
        return 'Message removed';
    }

    $normalized = trim((string) preg_replace('/\s+/', ' ', (string) $text));
    if ($normalized !== '') {
        return $normalized;
    }

    $attachments = array_filter(array_map('trim', explode(',', (string) $attachmentCsv)));
    if (!empty($attachments)) {
        return '📎 Attachment';
    }

    return 'Message';
}

function mailboxLatestThreadPreviews(mysqli $conn, array $messageIds, int $currentUserId, string $currentUserEmail, string $currentUsername): array
{
    $messageIds = array_values(array_unique(array_filter(array_map('intval', $messageIds), static fn(int $id): bool => $id > 0)));
    if ($messageIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
    $types = str_repeat('i', count($messageIds));
    $previews = [];

    $messageStmt = $conn->prepare("
        SELECT id, user_email, user_name, message, attachment, sent_at
        FROM contact_messages
        WHERE id IN ($placeholders)
    ");
    if ($messageStmt) {
        $messageStmt->bind_param($types, ...$messageIds);
        $messageStmt->execute();
        foreach (db_stmt_fetch_all_assoc($messageStmt) as $row) {
            $messageId = (int) ($row['id'] ?? 0);
            $isMine = mailboxOwnerMatchesCurrentUser(
                (string) ($row['user_email'] ?? ''),
                (string) ($row['user_name'] ?? ''),
                $currentUserEmail,
                $currentUsername
            );
            $previews[$messageId] = [
                'text' => mailboxPreviewText($row['message'] ?? '', $row['attachment'] ?? ''),
                'is_mine' => $isMine,
                'sent_at' => (string) ($row['sent_at'] ?? ''),
                'sort_ts' => strtotime((string) ($row['sent_at'] ?? '')) ?: 0,
                'sort_id' => 0,
            ];
        }
        $messageStmt->close();
    }

    $replyStmt = $conn->prepare("
        SELECT r.id, r.message_id, r.user_id, r.reply, r.attachment, r.sent_at, r.deleted_for_everyone_at
        FROM contact_replies r
        WHERE r.message_id IN ($placeholders)
        ORDER BY r.sent_at ASC, r.id ASC
    ");
    if ($replyStmt) {
        $replyStmt->bind_param($types, ...$messageIds);
        $replyStmt->execute();
        foreach (db_stmt_fetch_all_assoc($replyStmt) as $row) {
            $messageId = (int) ($row['message_id'] ?? 0);
            $replyId = (int) ($row['id'] ?? 0);
            $sortTs = strtotime((string) ($row['sent_at'] ?? '')) ?: 0;
            $current = $previews[$messageId] ?? null;
            if ($current && ($sortTs < (int) $current['sort_ts'] || ($sortTs === (int) $current['sort_ts'] && $replyId <= (int) $current['sort_id']))) {
                continue;
            }

            $previews[$messageId] = [
                'text' => mailboxPreviewText($row['reply'] ?? '', $row['attachment'] ?? '', !empty($row['deleted_for_everyone_at'])),
                'is_mine' => (int) ($row['user_id'] ?? 0) === $currentUserId,
                'sent_at' => (string) ($row['sent_at'] ?? ''),
                'sort_ts' => $sortTs,
                'sort_id' => $replyId,
            ];
        }
        $replyStmt->close();
    }

    return $previews;
}

function mailboxFormatThreadPreview(array $preview, int $width = 54): string
{
    $text = (string) ($preview['text'] ?? 'Message');
    if (!empty($preview['is_mine'])) {
        $text = 'You: ' . $text;
    }

    return mb_strimwidth($text, 0, $width, '...');
}
