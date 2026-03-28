<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/notification_helpers.php';
require_once __DIR__ . '/security.php';
session_start();
include('config.php');

// Start output buffering
ob_start();

security_require_csrf_token();

// Input
$userEmail = $_SESSION['email'] ?? 'anonymous@domain.com';
$userName  = $_SESSION['username'] ?? 'Anonymous';
$subject   = trim($_POST['subject'] ?? '');
$message   = trim($_POST['message'] ?? '');
$sendCopy  = isset($_POST['send_copy']);
$returnTo  = trim((string) ($_POST['return_to'] ?? 'inbox/'));
$recipientInputRaw = $_POST['recipient'] ?? [];

if (!is_array($recipientInputRaw)) {
    $recipientInputRaw = [$recipientInputRaw];
}
$recipientInputs = array_values(array_unique(array_filter(array_map(static function ($value) {
    return trim((string) $value);
}, $recipientInputRaw), static function ($value) {
    return $value !== '';
})));

if ($returnTo === '' || preg_match('/^(?:https?:)?\/\//i', $returnTo) || strpos($returnTo, '..') !== false || !preg_match('/^[A-Za-z0-9_\/\-\?\=&%.]+$/', $returnTo)) {
    $returnTo = 'inbox/';
}

// ---------------------------
// Handle File Upload
// ---------------------------
$uploadedFiles = [];
$filenamesForDB = [];

if (!empty($_FILES['attachments']['name'][0])) {
    $allowedTypes = [
        'image/jpeg','image/png','application/pdf',
        'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    $maxSize = 25 * 1024 * 1024; // 25 MB

    $uploadDir = __DIR__ . "/inbox/uploads/contact_attachments/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    foreach ($_FILES['attachments']['name'] as $i => $originalName) {
        if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
            $fileType = $_FILES['attachments']['type'][$i];
            $fileSize = $_FILES['attachments']['size'][$i];
            $tmpName  = $_FILES['attachments']['tmp_name'][$i];

            if (in_array($fileType, $allowedTypes) && $fileSize <= $maxSize) {
                $filename = time() . "_" . basename($originalName);
                $filePath = $uploadDir . $filename;
                if (move_uploaded_file($tmpName, $filePath)) {
                    $uploadedFiles[] = $filePath;
                    $filenamesForDB[] = $filename;
                }
            }
        }
    }
}

$hasAttachments = !empty($filenamesForDB);

if (empty($subject) || (empty($message) && !$hasAttachments)) {
    echo renderHTML("
        Swal.fire({ icon: 'warning', title: 'Missing Fields', text: 'Subject is required, and your message needs either text or at least one attachment.' })
        .then(() => window.location.href = '{$returnTo}');
    ");
    exit;
}

// ---------------------------
// Save to database
// ---------------------------
$attachmentsDB = !empty($filenamesForDB) ? implode(',', $filenamesForDB) : null;
$stmt = $conn->prepare("INSERT INTO contact_messages (user_email, user_name, subject, message, attachment, sent_at) VALUES (?, ?, ?, ?, ?, NOW())");
$stmt->bind_param("sssss", $userEmail, $userName, $subject, $message, $attachmentsDB);
$stmt->execute();
$messageId = $stmt->insert_id;
$stmt->close();

// Mark sender as read
if (!empty($_SESSION['user_id'])) {
    $senderId = $_SESSION['user_id'];
    $stmt = $conn->prepare("
        INSERT INTO message_reads (message_id, user_id, is_read, read_at)
        VALUES (?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE is_read = 1, read_at = NOW()
    ");
    $stmt->bind_param("ii", $messageId, $senderId);
    $stmt->execute();
    $stmt->close();
}

// ---------------------------
// Mailer setup
// ---------------------------
$mail = new PHPMailer(true);
try {
    app_configure_mailer($mail);

    // --- Recipient Selection ---
    $recipients = [];
    $userType = $_SESSION['user_type'] ?? 'user';
    $sendAutoReply = false;
    $seenEmails = [];

    $appendRecipient = static function (array $row) use (&$recipients, &$seenEmails) {
        $email = trim((string) ($row['email'] ?? ''));
        if ($email === '') {
            return;
        }
        $key = strtolower($email);
        if (isset($seenEmails[$key])) {
            return;
        }
        $seenEmails[$key] = true;
        $recipients[] = [
            'email' => $email,
            'username' => (string) ($row['username'] ?? $email),
        ];
    };

    if ($userType === 'admin') {
        foreach ($recipientInputs as $recipientInput) {
            if ($recipientInput === 'all') {
                $res = $conn->query("SELECT email, username FROM users");
                while ($row = $res->fetch_assoc()) {
                    $appendRecipient($row);
                }
            } elseif ($recipientInput === 'users') {
                $res = $conn->query("SELECT email, username FROM users WHERE userType IN ('user','aa')");
                while ($row = $res->fetch_assoc()) {
                    $appendRecipient($row);
                }
            } elseif ($recipientInput === 'admins') {
                $res = $conn->query("SELECT email, username FROM users WHERE userType='admin'");
                while ($row = $res->fetch_assoc()) {
                    $appendRecipient($row);
                }
            } elseif (strpos($recipientInput, 'user_') === 0) {
                $userId = (int) str_replace('user_', '', $recipientInput);
                $stmt = $conn->prepare("SELECT email, username FROM users WHERE id=?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $appendRecipient($row);
                }
                $stmt->close();
            }
        }
    } else {
        foreach ($recipientInputs as $recipientInput) {
            if (strpos($recipientInput, 'user_') === 0) {
                $userId = (int) str_replace('user_', '', $recipientInput);
                $stmt = $conn->prepare("SELECT email, username FROM users WHERE id=?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $appendRecipient($row);
                }
                $stmt->close();
            } elseif ($recipientInput === 'admins') {
                $appendRecipient([
                    'email' => app_env('SMTP_FROM_ADDRESS', $mail->Username),
                    'username' => app_env('SMTP_FROM_NAME', 'KODUS Admin'),
                ]);
            }
        }

        if (empty($recipients)) {
            $appendRecipient([
                'email' => app_env('SMTP_FROM_ADDRESS', $mail->Username),
                'username' => app_env('SMTP_FROM_NAME', 'KODUS Admin'),
            ]);
        }

        $sendAutoReply = true;
    }

    if (empty($recipients)) {
        echo renderHTML("
            Swal.fire({ icon: 'error', title: 'No Recipients Found', text: 'Could not determine recipients for this message.' })
            .then(() => window.location.href = '{$returnTo}');
        ");
        exit;
    }

    foreach ($recipients as $r) $mail->addAddress($r['email'], $r['username']);

    // Save recipients in DB
    $recipientEmails = implode(',', array_column($recipients, 'email'));
    $stmt = $conn->prepare("UPDATE contact_messages SET recipient=? WHERE id=?");
    $stmt->bind_param("si", $recipientEmails, $messageId);
    $stmt->execute();
    $stmt->close();

    $mail->addReplyTo($userEmail, $userName);
    if ($sendCopy) $mail->addCC($userEmail);

    // Attach files
    if (!empty($uploadedFiles)) {
        foreach ($uploadedFiles as $filePath) {
            if (file_exists($filePath)) $mail->addAttachment($filePath);
        }
    }

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = emailTemplate($userName, $userEmail, $subject, $message);
    $mail->AltBody = "From: $userName\nEmail: $userEmail\nSubject: $subject\n\n$message";

    // Auto-reply for non-admin
    if ($sendAutoReply) {
        $autoReplySubject = 'We received your message | KODUS';
        $autoReplyBody = autoReplyTemplate($userName, $subject, $message);
    } else {
        $autoReplySubject = null;
        $autoReplyBody = null;
    }

    $successHtml = renderHTML("
        Swal.fire({
            icon: 'success',
            title: 'Message Sent!',
            text: 'Your message has been delivered.\\n" . ($sendCopy ? 'A copy was sent to your email.' : 'Check your email for our confirmation.') . "',
            background: '#343a40',
            color: '#fff',
            confirmButtonColor: '#3085d6',
            customClass: { popup: 'swal-font' }
        }).then(() => window.location.href = 'contact');
    ");
    $successHtml = str_replace("window.location.href = 'contact'", "window.location.href = '{$returnTo}'", $successHtml);
    notification_finish_response($successHtml);

    $mail->send();

    if ($sendAutoReply) {
        $autoReply = new PHPMailer(true);
        app_configure_mailer($autoReply);
        $autoReply->addAddress($userEmail, $userName);
        $autoReply->isHTML(true);
        $autoReply->Subject = $autoReplySubject;
        $autoReply->Body    = $autoReplyBody;
        $autoReply->AltBody = "Thank you for contacting KODUS.\n\nWe received your message:\n\n$message";
        $autoReply->send();
    }

} catch (RuntimeException $e) {
    $error = addslashes($e->getMessage());
    echo renderHTML("
        Swal.fire({ icon: 'error', title: 'Message Failed', html: 'Mailer Config Error: <code>$error</code>' })
        .then(() => window.location.href = '{$returnTo}');
    ");
} catch (Exception $e) {
    $error = addslashes($mail->ErrorInfo);
    echo renderHTML("
        Swal.fire({ icon: 'error', title: 'Message Failed', html: 'Mailer Error: <code>$error</code>' })
        .then(() => window.location.href = '{$returnTo}');
    ");
}

// --- FUNCTIONS ---
function renderHTML($script) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>KODUS | Message Sent</title>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <style>body{background:#343a40;} .swal-font {font-family: Source Sans Pro, Arial, sans-serif !important;}</style>
    </head>
    <body><script>$script</script></body>
    </html>";
}

function emailTemplate($userName, $userEmail, $subject, $message) {
    return notification_render_email_shell(
        'New Message',
        htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'),
        'You received a new message through the KODUS contact channel.',
        '<p><strong>From:</strong> ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') . ')</p>'
        . '<div style="margin-top:16px;padding:16px 18px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;white-space:pre-line;">' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</div>',
        '#0d6efd',
        'KODUS Messaging'
    );
}

function autoReplyTemplate($userName, $subject, $message) {
    return notification_render_email_shell(
        'Message Received',
        'We Received Your Message',
        'Thanks for contacting KODUS. Your message is now in our queue.',
        '<p>Hello <strong>' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
        . '<p>We received your message and will respond as soon as possible.</p>'
        . notification_render_detail_rows([
            'Subject' => $subject,
            'Status' => 'Received',
        ])
        . '<div style="margin-top:16px;padding:16px 18px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;white-space:pre-line;">' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</div>'
        . '<p>Regards,<br><strong>KODUS Admin</strong></p>',
        '#198754',
        'KODUS Messaging'
    );
}

if (ob_get_level() > 0) {
    ob_end_flush();
}
