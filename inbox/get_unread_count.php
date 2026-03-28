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
                FROM users u
                WHERE FIND_IN_SET(u.email, cm.recipient) > 0
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
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
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
        WHERE (cm.user_email = ? OR FIND_IN_SET(?, cm.recipient) > 0)
          AND COALESCE(mr.is_trashed, 0) = 0
          AND (mr.is_read IS NULL OR mr.is_read = 0)
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("iss", $userId, $userEmail, $userEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $unreadCount = (int)$row['unread'];
        }
        $stmt->close();
    } else {
        $unreadCount = 0;
    }
}

echo json_encode(['count' => $unreadCount]);
