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
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
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
    $replyResult = $replyStmt->get_result();
    while ($row = $replyResult->fetch_assoc()) {
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
