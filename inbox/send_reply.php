<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require_once __DIR__ . '/../notification_helpers.php';
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/mailbox_helpers.php';
security_bootstrap_session();
include('../config.php');
mailboxEnsureSchema($conn);

// Return JSON only
header('Content-Type: application/json; charset=utf-8');

// Don't show raw errors to the browser, but capture output for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

$senderId = $_SESSION['user_id'] ?? 0;
$senderType = $_SESSION['user_type'] ?? null;
$senderEmail = $_SESSION['email'] ?? null;
$id = intval($_POST['id'] ?? 0);
$email = trim($_POST['email'] ?? '');
$reply = trim($_POST['reply'] ?? '');
$subject = $_POST['subject'] ?? '(No Subject)';
$originalMessage = $_POST['message'] ?? '(No Message)';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method.'
    ]);
    exit;
}

if (!security_validate_csrf_token($_POST['csrf_token'] ?? null)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid CSRF token.'
    ]);
    exit;
}

if (!$id || !$email) {
    $buf = ob_get_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request (missing id or email).',
        'debug' => $buf ?: null
    ]);
    exit;
}

if ($senderId <= 0 || !$senderEmail) {
    $buf = ob_get_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'You must be logged in to reply.',
        'debug' => $buf ?: null
    ]);
    exit;
}

$messageAccessSql = "
    SELECT id, user_email, recipient, subject, message
    FROM contact_messages
    WHERE id = ?
";

if ($senderType === 'admin') {
    $messageAccessSql .= "
      AND (
          user_email = ?
          OR EXISTS (
              SELECT 1
              FROM users u
              WHERE FIND_IN_SET(u.email, recipient) > 0
                AND u.userType = 'admin'
          )
      )
      LIMIT 1
    ";
    $accessStmt = $conn->prepare($messageAccessSql);
    $accessStmt->bind_param("is", $id, $senderEmail);
} else {
    $messageAccessSql .= " AND (user_email = ? OR FIND_IN_SET(?, recipient) > 0) LIMIT 1";
    $accessStmt = $conn->prepare($messageAccessSql);
    $accessStmt->bind_param("iss", $id, $senderEmail, $senderEmail);
}

$accessStmt->execute();
$thread = $accessStmt->get_result()->fetch_assoc();
$accessStmt->close();

if (!$thread) {
    $buf = ob_get_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'You are not allowed to reply to this conversation.',
        'debug' => $buf ?: null
    ]);
    exit;
}

$threadOwnerEmail = trim((string) ($thread['user_email'] ?? ''));
$threadRecipients = array_values(array_filter(array_map('trim', explode(',', (string) ($thread['recipient'] ?? '')))));

if ($senderType === 'admin') {
    if ($threadOwnerEmail !== '' && strcasecmp($threadOwnerEmail, (string) $senderEmail) !== 0) {
        $email = $threadOwnerEmail;
    } else {
        foreach ($threadRecipients as $candidateEmail) {
            if (strcasecmp($candidateEmail, (string) $senderEmail) !== 0) {
                $email = $candidateEmail;
                break;
            }
        }
    }
} else {
    $email = $threadOwnerEmail !== '' ? $threadOwnerEmail : $email;
}

$subject = (string) ($thread['subject'] ?? $subject);
$originalMessage = (string) ($thread['message'] ?? $originalMessage);

// ---------------------------
// Handle File Upload (Project Root / inbox/uploads/reply_attachments/)
// ---------------------------
$uploadedFiles = [];
$filenamesForDB = [];

if (!empty($_FILES['replyAttachments']['name'][0])) {
    $maxSize = 25 * 1024 * 1024; // 25 MB

    // Using __DIR__ (inbox folder) so attachments are served from /inbox/uploads/...
    $uploadDir = __DIR__ . "/uploads/reply_attachments/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Support multiple files
    $names = $_FILES['replyAttachments']['name'];
    $errors = $_FILES['replyAttachments']['error'];
    $sizes = $_FILES['replyAttachments']['size'];
    $tmps = $_FILES['replyAttachments']['tmp_name'];

    for ($i = 0; $i < count($names); $i++) {
        $originalName = $names[$i];
        if ($errors[$i] === UPLOAD_ERR_OK) {
            $fileSize = $sizes[$i];
            $tmpName  = $tmps[$i];

            if ($fileSize <= $maxSize) {
                // Sanitize filename to avoid traversal and unsafe chars
                $safeName = preg_replace("/[^a-zA-Z0-9\._-]/", "_", basename($originalName));
                // Add a microtime + random to avoid collisions
                $filename = time() . "_" . mt_rand(1000,9999) . "_" . $safeName;
                $filePath = $uploadDir . $filename;

                if (move_uploaded_file($tmpName, $filePath)) {
                    $uploadedFiles[] = $filePath;        // absolute path for PHPMailer
                    $filenamesForDB[] = $filename;      // filename to store in DB
                }
            }
        }
    }
}

$hasAttachments = !empty($filenamesForDB);

if ($reply === '' && !$hasAttachments) {
    $buf = ob_get_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Your reply needs either text or at least one attachment.',
        'debug' => $buf ?: null
    ]);
    exit;
}

// ---------------------------
// Insert reply into database
// ---------------------------
$attachmentsDB = !empty($filenamesForDB) ? implode(',', $filenamesForDB) : null;
$stmt = $conn->prepare("INSERT INTO contact_replies (message_id, user_id, reply, sent_at, updated_at, attachment) VALUES (?,?,?,NOW(),NOW(),?)");
if (!$stmt) {
    $buf = ob_get_clean();
    echo json_encode(['status'=>'error','message'=>'DB prepare failed (insert reply).','debug'=>$buf]);
    exit;
}
$stmt->bind_param("iiss", $id, $senderId, $reply, $attachmentsDB);
$stmt->execute();
$replyId = (int) $stmt->insert_id;
$stmt->close();

// ---------------------------
// Mark the thread as read for sender
// ---------------------------
$stmt = $conn->prepare("
    INSERT INTO message_reads (message_id, user_id, is_read, is_trashed, read_at, trashed_at)
    VALUES (?, ?, 1, 0, NOW(), NULL)
    ON DUPLICATE KEY UPDATE is_read=1, is_trashed=0, read_at=NOW(), trashed_at=NULL
");
if ($stmt) {
    $stmt->bind_param("ii", $id, $senderId);
    $stmt->execute();
    $stmt->close();
}

// ---------------------------
// Mark unread for recipient(s)
// ---------------------------
$participantEmails = [];
if ($threadOwnerEmail !== '') {
    $participantEmails[] = $threadOwnerEmail;
}
foreach ($threadRecipients as $recipientEmail) {
    if ($recipientEmail !== '') {
        $participantEmails[] = $recipientEmail;
    }
}
$participantEmails = array_values(array_unique(array_filter($participantEmails, static function ($value) {
    return is_string($value) && trim($value) !== '';
})));

$recipientIds = [];
if (!empty($participantEmails)) {
    $placeholders = implode(',', array_fill(0, count($participantEmails), '?'));
    $typeString = str_repeat('s', count($participantEmails));
    $recipientLookupSql = "SELECT id, email FROM users WHERE email IN ($placeholders)";
    $lookupStmt = $conn->prepare($recipientLookupSql);
    if ($lookupStmt) {
        $lookupStmt->bind_param($typeString, ...$participantEmails);
        $lookupStmt->execute();
        $lookupResult = $lookupStmt->get_result();
        while ($participant = $lookupResult->fetch_assoc()) {
            $participantId = (int) ($participant['id'] ?? 0);
            $participantEmail = (string) ($participant['email'] ?? '');
            if ($participantId > 0 && strcasecmp($participantEmail, (string) $senderEmail) !== 0) {
                $recipientIds[] = $participantId;
            }
        }
        $lookupStmt->close();
    }
}

$recipientIds = array_values(array_unique(array_filter($recipientIds, static function ($value) use ($senderId) {
    return is_int($value) && $value > 0 && $value !== $senderId;
})));

if (!empty($recipientIds)) {
    $recipientStateStmt = $conn->prepare("
        INSERT INTO message_reads (message_id, user_id, is_read, is_trashed, read_at, trashed_at)
        VALUES (?, ?, 0, 0, NULL, NULL)
        ON DUPLICATE KEY UPDATE is_read=0, is_trashed=0, read_at=NULL, trashed_at=NULL
    ");

    if ($recipientStateStmt) {
        foreach ($recipientIds as $recipientId) {
            $recipientStateStmt->bind_param("ii", $id, $recipientId);
            $recipientStateStmt->execute();
        }
        $recipientStateStmt->close();
    }
}

echo json_encode([
    'status' => 'success',
    'message' => 'Reply sent',
    'id' => $id,
    'reply_id' => $replyId,
    'attachments' => $filenamesForDB
]);
exit;
