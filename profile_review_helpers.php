<?php

require_once __DIR__ . '/audit_helpers.php';

function profile_review_ensure_schema(mysqli $conn): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    $column = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_review_required'");
    if ($column && $column->num_rows > 0) {
        $meta = $column->fetch_assoc() ?: [];
        if (($meta['Default'] ?? null) !== '0') {
            $conn->query("ALTER TABLE users MODIFY COLUMN profile_review_required TINYINT(1) NOT NULL DEFAULT 0");
        }
        $conn->query('UPDATE users SET profile_review_required = 0 WHERE profile_review_required IS NULL');
        return;
    }

    $conn->query("ALTER TABLE users ADD COLUMN profile_review_required TINYINT(1) NOT NULL DEFAULT 0");
}

function profile_review_is_required(array $user): bool
{
    return !empty($user['profile_review_required']);
}

function profile_review_require_after_login(mysqli $conn, array $user, bool $isFirstLogin): bool
{
    $isRequired = profile_review_is_required($user);
    $userId = isset($user['id']) ? (int) $user['id'] : 0;

    if (!$isFirstLogin || $userId <= 0) {
        return $isRequired;
    }

    if ($isRequired) {
        return true;
    }

    $stmt = $conn->prepare('UPDATE users SET profile_review_required = 1 WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }

    audit_log($conn, $userId, 'Profile Review Required', 'Marked profile review as required after the first successful login.');

    return true;
}

function profile_review_mark_completed(mysqli $conn, int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }

    $stmt = $conn->prepare('UPDATE users SET profile_review_required = 0 WHERE id = ? AND profile_review_required <> 0');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $updated = $stmt->affected_rows > 0;
    $stmt->close();

    if ($updated) {
        audit_log($conn, $userId, 'Profile Review Completed', 'Profile Information was saved and the first-login profile review requirement was cleared.');
    }

    return $updated;
}
