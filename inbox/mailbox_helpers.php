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

    $messageColumns = [];
    $messageStmt = $conn->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = 'contact_messages'
    ");
    if (!$messageStmt) {
        throw new RuntimeException('Unable to prepare mailbox message schema check: ' . $conn->error);
    }
    $messageStmt->bind_param('s', $database);
    $messageStmt->execute();
    foreach (db_stmt_fetch_all_assoc($messageStmt) as $row) {
        $messageColumns[] = (string) $row['COLUMN_NAME'];
    }
    $messageStmt->close();

    if (!in_array('conversation_type', $messageColumns, true)) {
        if (!$conn->query("ALTER TABLE contact_messages ADD COLUMN conversation_type VARCHAR(20) NOT NULL DEFAULT 'direct' AFTER id")) {
            throw new RuntimeException('Unable to add conversation type: ' . $conn->error);
        }
    }

    if (!in_array('group_name', $messageColumns, true)) {
        if (!$conn->query("ALTER TABLE contact_messages ADD COLUMN group_name VARCHAR(255) NULL DEFAULT NULL AFTER conversation_type")) {
            throw new RuntimeException('Unable to add group name: ' . $conn->error);
        }
    }

    if (!in_array('group_photo', $messageColumns, true)) {
        if (!$conn->query("ALTER TABLE contact_messages ADD COLUMN group_photo VARCHAR(255) NULL DEFAULT NULL AFTER group_name")) {
            throw new RuntimeException('Unable to add group photo: ' . $conn->error);
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

    if (!in_array('quote_target_type', $replyColumns, true)) {
        if (!$conn->query("ALTER TABLE contact_replies ADD COLUMN quote_target_type VARCHAR(20) NULL DEFAULT NULL AFTER attachment")) {
            throw new RuntimeException('Unable to add reply quote target type: ' . $conn->error);
        }
    }

    if (!in_array('quote_reply_id', $replyColumns, true)) {
        if (!$conn->query("ALTER TABLE contact_replies ADD COLUMN quote_reply_id INT NULL DEFAULT NULL AFTER quote_target_type")) {
            throw new RuntimeException('Unable to add reply quote reply id: ' . $conn->error);
        }
    }

    if (!in_array('quote_author', $replyColumns, true)) {
        if (!$conn->query("ALTER TABLE contact_replies ADD COLUMN quote_author VARCHAR(255) NULL DEFAULT NULL AFTER quote_reply_id")) {
            throw new RuntimeException('Unable to add reply quote author: ' . $conn->error);
        }
    }

    if (!in_array('quote_excerpt', $replyColumns, true)) {
        if (!$conn->query("ALTER TABLE contact_replies ADD COLUMN quote_excerpt TEXT NULL DEFAULT NULL AFTER quote_author")) {
            throw new RuntimeException('Unable to add reply quote excerpt: ' . $conn->error);
        }
    }

    if (!in_array('system_event_type', $replyColumns, true)) {
        if (!$conn->query("ALTER TABLE contact_replies ADD COLUMN system_event_type VARCHAR(40) NULL DEFAULT NULL AFTER quote_excerpt")) {
            throw new RuntimeException('Unable to add reply system event type: ' . $conn->error);
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

    $recipientColumns = [];
    $recipientStmt = $conn->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = 'contact_message_recipients'
    ");
    if (!$recipientStmt) {
        throw new RuntimeException('Unable to prepare mailbox recipient schema check: ' . $conn->error);
    }
    $recipientStmt->bind_param('s', $database);
    $recipientStmt->execute();
    foreach (db_stmt_fetch_all_assoc($recipientStmt) as $row) {
        $recipientColumns[] = (string) $row['COLUMN_NAME'];
    }
    $recipientStmt->close();

    if (!in_array('muted_at', $recipientColumns, true)) {
        if (!$conn->query("ALTER TABLE contact_message_recipients ADD COLUMN muted_at DATETIME NULL DEFAULT NULL AFTER created_at")) {
            throw new RuntimeException('Unable to add group mute state: ' . $conn->error);
        }
    }

    if (!in_array('left_at', $recipientColumns, true)) {
        if (!$conn->query("ALTER TABLE contact_message_recipients ADD COLUMN left_at DATETIME NULL DEFAULT NULL AFTER muted_at")) {
            throw new RuntimeException('Unable to add group leave state: ' . $conn->error);
        }
    }

    if (!in_array('hidden_at', $recipientColumns, true)) {
        if (!$conn->query("ALTER TABLE contact_message_recipients ADD COLUMN hidden_at DATETIME NULL DEFAULT NULL AFTER left_at")) {
            throw new RuntimeException('Unable to add group hidden state: ' . $conn->error);
        }
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

    $reactionUniqueIndexes = [];
    $uniqueIndexStmt = $conn->prepare("
        SELECT INDEX_NAME, COLUMN_NAME
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = 'message_reactions'
          AND NON_UNIQUE = 0
          AND INDEX_NAME <> 'PRIMARY'
        ORDER BY INDEX_NAME, SEQ_IN_INDEX
    ");
    if (!$uniqueIndexStmt) {
        throw new RuntimeException('Unable to prepare mailbox reaction unique index check: ' . $conn->error);
    }
    $uniqueIndexStmt->bind_param('s', $database);
    $uniqueIndexStmt->execute();
    foreach (db_stmt_fetch_all_assoc($uniqueIndexStmt) as $row) {
        $indexName = (string) ($row['INDEX_NAME'] ?? '');
        if ($indexName === '') {
            continue;
        }
        $reactionUniqueIndexes[$indexName][] = (string) ($row['COLUMN_NAME'] ?? '');
    }
    $uniqueIndexStmt->close();

    foreach ($reactionUniqueIndexes as $indexName => $columns) {
        if ($indexName === 'uq_message_reaction_target') {
            continue;
        }
        $hasMessage = in_array('message_id', $columns, true);
        $hasUser = in_array('user_id', $columns, true);
        $hasEmoji = in_array('emoji', $columns, true);
        if ($hasMessage && $hasUser && !$hasEmoji) {
            $safeIndexName = str_replace('`', '``', $indexName);
            if (!$conn->query("ALTER TABLE message_reactions DROP INDEX `{$safeIndexName}`")) {
                throw new RuntimeException('Unable to remove broad mailbox reaction uniqueness: ' . $conn->error);
            }
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

function mailboxFirstNameToken(?string $firstName): string
{
    $firstName = trim((string) $firstName);
    if ($firstName === '') {
        return '';
    }

    $parts = preg_split('/\s+/', $firstName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return trim((string) ($parts[0] ?? ''));
}

function mailboxDisplayName(array $row, string $fallback = 'Unknown'): string
{
    $firstToken = mailboxFirstNameToken($row['first_name'] ?? null);
    if ($firstToken !== '') {
        return $firstToken;
    }

    foreach (['name', 'display_name', 'recipient_name', 'user_name', 'username', 'email', 'user_email', 'recipient_email'] as $key) {
        $value = trim((string) ($row[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return $fallback;
}

function mailboxGroupPresence(mysqli $conn, int $messageId, int $excludeUserId = 0): array
{
    if ($messageId <= 0) {
        return ['label' => 'Offline', 'class' => 'offline', 'detail' => 'No active members'];
    }

    $stmt = $conn->prepare("
        SELECT DISTINCT u.id, u.last_activity, u.is_online
        FROM users u
        INNER JOIN (
            SELECT cmr.user_id
            FROM contact_message_recipients cmr
            WHERE cmr.message_id = ?
              AND cmr.user_id IS NOT NULL
              AND cmr.left_at IS NULL
              AND cmr.hidden_at IS NULL
            UNION
            SELECT sender.id AS user_id
            FROM contact_messages cm
            INNER JOIN users sender
                ON (
                    LOWER(sender.email) = LOWER(cm.user_email)
                    OR sender.username = cm.user_name
                )
            LEFT JOIN contact_message_recipients sender_state
                ON sender_state.message_id = cm.id
                AND sender_state.user_id = sender.id
            WHERE cm.id = ?
              AND sender.id IS NOT NULL
              AND sender_state.left_at IS NULL
              AND sender_state.hidden_at IS NULL
        ) members ON members.user_id = u.id
        WHERE (? = 0 OR u.id <> ?)
    ");
    if (!$stmt) {
        return ['label' => 'Offline', 'class' => 'offline', 'detail' => 'No active members'];
    }

    $stmt->bind_param('iiii', $messageId, $messageId, $excludeUserId, $excludeUserId);
    $stmt->execute();
    $best = ['label' => 'Offline', 'class' => 'offline', 'detail' => 'No active members'];
    foreach (db_stmt_fetch_all_assoc($stmt) as $row) {
        $presence = mailboxClassifyPresence($row['last_activity'] ?? null, (int) ($row['is_online'] ?? 0));
        if (($presence['class'] ?? '') === 'online') {
            $best = ['label' => 'Online', 'class' => 'online', 'detail' => 'Active members now'];
            break;
        }
        if (($presence['class'] ?? '') === 'idle' && ($best['class'] ?? '') !== 'online') {
            $best = ['label' => 'Idle', 'class' => 'idle', 'detail' => 'Members active recently'];
        }
    }
    $stmt->close();

    return $best;
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

function mailboxVisibilityPredicate(int $userId, string $messageAlias = 'cm', string $readAlias = 'mr'): string
{
    $messageAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $messageAlias) ?: 'cm';
    $userId = max(0, $userId);

    return "(
        COALESCE({$messageAlias}.conversation_type, 'direct') <> 'group'
        OR EXISTS (
            SELECT 1
            FROM contact_message_recipients group_vis
            WHERE group_vis.message_id = {$messageAlias}.id
              AND group_vis.user_id = {$userId}
              AND group_vis.hidden_at IS NULL
        )
    )";
}

function mailboxThreadAccessPredicate(string $messageAlias = 'cm'): string
{
    $messageAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $messageAlias) ?: 'cm';

    return "(
        COALESCE({$messageAlias}.conversation_type, 'direct') <> 'group'
        OR EXISTS (
            SELECT 1
            FROM contact_message_recipients group_member
            WHERE group_member.message_id = {$messageAlias}.id
              AND group_member.user_id = ?
              AND group_member.hidden_at IS NULL
        )
    )";
}

function mailboxCanParticipateInThread(mysqli $conn, int $messageId, int $userId): bool
{
    if ($messageId <= 0 || $userId <= 0) {
        return false;
    }

    $stmt = $conn->prepare("
        SELECT cm.id
        FROM contact_messages cm
        WHERE cm.id = ?
          AND (
              COALESCE(cm.conversation_type, 'direct') <> 'group'
              OR EXISTS (
                  SELECT 1
                  FROM contact_message_recipients cmr
                  WHERE cmr.message_id = cm.id
                    AND cmr.user_id = ?
                    AND cmr.left_at IS NULL
                    AND cmr.hidden_at IS NULL
              )
          )
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $messageId, $userId);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();

    return !empty($row);
}

function mailboxCurrentParticipantIds(mysqli $conn, int $messageId, int $excludeUserId = 0, bool $excludeMuted = false): array
{
    if ($messageId <= 0) {
        return [];
    }

    $mutedSql = $excludeMuted ? 'AND cmr.muted_at IS NULL' : '';
    $stmt = $conn->prepare("
        SELECT DISTINCT cmr.user_id
        FROM contact_message_recipients cmr
        INNER JOIN contact_messages cm ON cm.id = cmr.message_id
        WHERE cmr.message_id = ?
          AND cmr.user_id IS NOT NULL
          AND cmr.left_at IS NULL
          AND cmr.hidden_at IS NULL
          {$mutedSql}
          AND COALESCE(cm.conversation_type, 'direct') = 'group'
    ");
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $messageId);
    $stmt->execute();
    $ids = [];
    foreach (db_stmt_fetch_all_assoc($stmt) as $row) {
        $id = (int) ($row['user_id'] ?? 0);
        if ($id > 0 && $id !== $excludeUserId) {
            $ids[] = $id;
        }
    }
    $stmt->close();

    return array_values(array_unique($ids));
}

function mailboxIsGroupThread(array $row): bool
{
    return strtolower((string) ($row['conversation_type'] ?? 'direct')) === 'group';
}

function mailboxCanAccessMessage(mysqli $conn, int $messageId, ?string $userType, ?string $userEmail, ?string $userName): bool
{
    if ($messageId <= 0) {
        return false;
    }

    $userEmail = (string) ($userEmail ?? '');
    $userName = trim((string) ($userName ?? ''));

    $sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
    $sql = "SELECT id FROM contact_messages WHERE id = ? AND " . mailboxThreadAccessPredicate('contact_messages');
    if ($userType === 'admin') {
        $sql .= "
          AND (
              COALESCE(conversation_type, 'direct') = 'group'
              OR
              user_email = ?
              OR EXISTS (
                  SELECT 1
                  FROM contact_message_recipients cmr
                  INNER JOIN users u ON u.id = cmr.user_id
                  WHERE cmr.message_id = contact_messages.id
                    AND u.userType = 'admin'
              )
          )
          LIMIT 1
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('iis', $messageId, $sessionUserId, $userEmail);
    } else {
        $sql .= "
          AND (
              COALESCE(conversation_type, 'direct') = 'group'
              OR
              user_email = ?
              OR user_name = ?
              OR EXISTS (
                  SELECT 1
                  FROM contact_message_recipients cmr
                  WHERE cmr.message_id = contact_messages.id
                    AND LOWER(cmr.recipient_email) = LOWER(?)
              )
          )
          LIMIT 1
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('iisss', $messageId, $sessionUserId, $userEmail, $userName, $userEmail);
    }

    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();

    return !empty($row);
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
        SELECT mr.emoji,
               COUNT(*) AS reaction_count,
               MAX(CASE WHEN mr.user_id = ? THEN 1 ELSE 0 END) AS reacted_by_current_user,
               GROUP_CONCAT(
                   CONCAT(COALESCE(NULLIF(SUBSTRING_INDEX(TRIM(u.first_name), ' ', 1), ''), NULLIF(u.username, ''), CONCAT('User #', mr.user_id)), ' ', mr.emoji)
                   ORDER BY COALESCE(NULLIF(SUBSTRING_INDEX(TRIM(u.first_name), ' ', 1), ''), NULLIF(u.username, ''), CONCAT('User #', mr.user_id))
                   SEPARATOR '||'
               ) AS reactor_details
        FROM message_reactions mr
        LEFT JOIN users u ON u.id = mr.user_id
        WHERE mr.message_id = ?
          AND mr.target_key = ?
        GROUP BY mr.emoji
        ORDER BY reaction_count DESC, mr.emoji ASC
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
            'reactors' => array_values(array_filter(array_map('trim', explode('||', (string) ($row['reactor_details'] ?? ''))))),
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
        SELECT r.id, r.message_id, r.user_id, r.reply, r.attachment, r.sent_at, r.deleted_for_everyone_at, r.system_event_type
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
                'is_mine' => trim((string) ($row['system_event_type'] ?? '')) === '' && (int) ($row['user_id'] ?? 0) === $currentUserId,
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

function mailboxAllowedAttachmentMimeExtensions(): array
{
    return [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'image/avif' => ['avif'],
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'video/mp4' => ['mp4', 'm4v'],
        'video/webm' => ['webm'],
        'video/ogg' => ['ogg', 'ogv'],
        'video/quicktime' => ['mov'],
        'audio/mpeg' => ['mp3'],
        'audio/wav' => ['wav'],
        'audio/x-wav' => ['wav'],
        'audio/ogg' => ['ogg'],
        'audio/mp4' => ['m4a', 'aac'],
        'audio/aac' => ['aac'],
        'audio/flac' => ['flac'],
    ];
}

function mailboxAttachmentLimits(): array
{
    return [
        'max_file_size' => 67108864,
        'max_total_size' => 83886080,
        'max_file_count' => 50,
        'max_file_size_label' => '64 MB',
        'max_total_size_label' => '80 MB',
    ];
}

function mailboxNormalizeUploadedFilesArray(array $files): array
{
    $names = $files['name'] ?? [];
    if (!is_array($names)) {
        $names = [$names];
    }

    $normalized = [];
    foreach ($names as $index => $name) {
        $normalized[] = [
            'name' => (string) $name,
            'type' => (string) ($files['type'][$index] ?? ''),
            'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
            'error' => (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($files['size'][$index] ?? 0),
        ];
    }

    return $normalized;
}

function mailboxSafeAttachmentFilename(string $originalName, string $extension): string
{
    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
    $safeBaseName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $baseName);
    $safeBaseName = trim((string) $safeBaseName, '_-');
    if ($safeBaseName === '') {
        $safeBaseName = 'attachment';
    }

    $safeBaseName = substr($safeBaseName, 0, 72);
    return 'att_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '_' . $safeBaseName . '.' . $extension;
}

function mailboxIsMediaAttachmentMime(string $mime): bool
{
    return str_starts_with($mime, 'image/')
        || str_starts_with($mime, 'video/')
        || str_starts_with($mime, 'audio/');
}

function mailboxGeneratedMediaAttachmentFilename(string $extension): string
{
    return 'att_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(12)) . '.' . $extension;
}

function mailbox_is_supported_reaction_emoji(string $emoji): bool
{
    $emoji = trim($emoji);
    if ($emoji === '') {
        return false;
    }

    $length = function_exists('mb_strlen') ? mb_strlen($emoji, 'UTF-8') : strlen($emoji);
    if ($length > 32 || preg_match('/[\x00-\x1F\x7F]/u', $emoji)) {
        return false;
    }

    $emojiPattern = '/^(?=.*(?:\p{Extended_Pictographic}|[\x{1F1E6}-\x{1F1FF}]|\x{20E3}))[\p{Extended_Pictographic}\p{Emoji_Component}\x{FE0E}\x{FE0F}\x{200D}\x{20E3}\x{1F1E6}-\x{1F1FF}0-9#*]+$/u';
    return preg_match($emojiPattern, $emoji) === 1;
}

function mailbox_quote_excerpt(string $text, string $fallback = 'Attachment'): string
{
    $normalized = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if ($normalized === '') {
        $normalized = $fallback;
    }

    if (function_exists('mb_strlen') && mb_strlen($normalized, 'UTF-8') > 240) {
        return mb_substr($normalized, 0, 237, 'UTF-8') . '...';
    }

    if (!function_exists('mb_strlen') && strlen($normalized) > 240) {
        return substr($normalized, 0, 237) . '...';
    }

    return $normalized;
}

function mailboxCreateSystemReply(mysqli $conn, int $messageId, int $actorUserId, string $message, string $eventType): int
{
    $message = trim($message);
    $eventType = trim($eventType);
    if ($messageId <= 0 || $actorUserId <= 0 || $message === '' || $eventType === '') {
        return 0;
    }

    $stmt = $conn->prepare("
        INSERT INTO contact_replies (message_id, user_id, reply, sent_at, updated_at, attachment, system_event_type)
        VALUES (?, ?, ?, NOW(), NOW(), '', ?)
    ");
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('iiss', $messageId, $actorUserId, $message, $eventType);
    $stmt->execute();
    $replyId = (int) $stmt->insert_id;
    $stmt->close();

    return $replyId;
}

function mailboxSaveUploadedAttachments(array $files, string $uploadDir, ?int $maxSize = null): array
{
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $limits = mailboxAttachmentLimits();
    $maxSize = $maxSize ?? (int) $limits['max_file_size'];
    $maxTotalSize = (int) $limits['max_total_size'];
    $maxFileCount = (int) $limits['max_file_count'];
    $allowedMimeToExtensions = mailboxAllowedAttachmentMimeExtensions();
    $uploadedFiles = [];
    $filenamesForDB = [];
    $errors = [];
    $normalizedFiles = array_values(array_filter(
        mailboxNormalizeUploadedFilesArray($files),
        static fn (array $file): bool => $file['error'] !== UPLOAD_ERR_NO_FILE && $file['name'] !== ''
    ));

    if (count($normalizedFiles) > $maxFileCount) {
        return [
            'paths' => [],
            'filenames' => [],
            'errors' => array_column($normalizedFiles, 'name'),
        ];
    }

    $totalSize = array_sum(array_map(static fn (array $file): int => max(0, (int) $file['size']), $normalizedFiles));
    if ($totalSize > $maxTotalSize) {
        return [
            'paths' => [],
            'filenames' => [],
            'errors' => array_column($normalizedFiles, 'name'),
        ];
    }

    foreach ($normalizedFiles as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0 || $file['tmp_name'] === '' || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = $file['name'];
            continue;
        }

        $detectedMime = security_detect_upload_mime($file['tmp_name']);
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (
            $file['size'] > $maxSize
            || !isset($allowedMimeToExtensions[$detectedMime])
            || !in_array($extension, $allowedMimeToExtensions[$detectedMime], true)
        ) {
            $errors[] = $file['name'];
            continue;
        }

        $filename = mailboxIsMediaAttachmentMime($detectedMime)
            ? mailboxGeneratedMediaAttachmentFilename($extension)
            : mailboxSafeAttachmentFilename($file['name'], $extension);
        $filePath = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $uploadedFiles[] = $filePath;
            $filenamesForDB[] = $filename;
        } else {
            $errors[] = $file['name'];
        }
    }

    return [
        'paths' => $uploadedFiles,
        'filenames' => $filenamesForDB,
        'errors' => $errors,
    ];
}
