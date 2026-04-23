<?php
include('../config.php');
require_once __DIR__ . '/mailbox_helpers.php';
session_start();
header('Content-Type: application/json');
mailboxEnsureSchema($conn);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'] ?? null;
$userName = trim((string) ($_SESSION['username'] ?? ''));
$unreadCount = 0;

if ($userType === 'admin') {
    // Admin: count any accessible thread that this admin has not read yet.
    $sql = "
        SELECT COUNT(*) AS unread
        FROM contact_messages cm
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
          AND COALESCE(mr.is_trashed, 0) = 0
          AND (mr.is_read IS NULL OR mr.is_read = 0)
    ";
    $stmt = $conn->prepare($sql);
    $userEmail = $_SESSION['email'] ?? '';
    $stmt->bind_param("is", $userId, $userEmail);
    $stmt->execute();
    if ($row = db_stmt_fetch_one_assoc($stmt)) {
        $unreadCount = (int)$row['unread'];
    }
    if ($stmt) $stmt->close();
} else {
    // Non-admin: count threads belonging to this user's email that this user hasn't read.
    $userEmail = $_SESSION['email'] ?? '';

    $sql = "
        SELECT COUNT(*) AS unread
        FROM contact_messages cm
        LEFT JOIN message_reads mr 
          ON cm.id = mr.message_id AND mr.user_id = ?
        WHERE (cm.user_email = ? OR cm.user_name = ? OR EXISTS (
            SELECT 1
            FROM contact_message_recipients cmr
            WHERE cmr.message_id = cm.id
              AND LOWER(cmr.recipient_email) = LOWER(?)
        ))
          AND COALESCE(mr.is_trashed, 0) = 0
          AND (mr.is_read IS NULL OR mr.is_read = 0)
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("isss", $userId, $userEmail, $userName, $userEmail);
        $stmt->execute();
        if ($row = db_stmt_fetch_one_assoc($stmt)) {
            $unreadCount = (int)$row['unread'];
        }
        $stmt->close();
    } else {
        $unreadCount = 0;
    }
}

echo json_encode(['count' => $unreadCount]);
