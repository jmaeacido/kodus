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

    $ensured = true;
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
