<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require_once __DIR__ . '/../notification_helpers.php';
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
include('../config.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

if (!security_validate_csrf_token($_POST['csrf_token'] ?? null)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token.']);
    exit;
}

$replyId = (int) ($_POST['reply_id'] ?? 0);
$senderId = (int) ($_SESSION['user_id'] ?? 0);
$senderEmail = trim((string) ($_SESSION['email'] ?? ''));

if ($replyId <= 0 || $senderId <= 0 || $senderEmail === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing reply context.']);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        r.id AS reply_id,
        r.reply,
        r.attachment,
        r.user_id,
        cm.id AS message_id,
        cm.user_email,
        cm.recipient,
        cm.subject,
        cm.message
    FROM contact_replies r
    JOIN contact_messages cm ON cm.id = r.message_id
    WHERE r.id = ? AND r.user_id = ?
    LIMIT 1
");

if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Unable to prepare mail job.']);
    exit;
}

$stmt->bind_param('ii', $replyId, $senderId);
$stmt->execute();
$replyRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$replyRow) {
    echo json_encode(['status' => 'error', 'message' => 'Reply not found.']);
    exit;
}

$threadOwnerEmail = trim((string) ($replyRow['user_email'] ?? ''));
$threadRecipients = array_values(array_filter(array_map('trim', explode(',', (string) ($replyRow['recipient'] ?? '')))));
$recipientEmail = $threadOwnerEmail;

if ($threadOwnerEmail !== '' && strcasecmp($threadOwnerEmail, $senderEmail) === 0) {
    foreach ($threadRecipients as $candidateEmail) {
        if (strcasecmp($candidateEmail, $senderEmail) !== 0) {
            $recipientEmail = $candidateEmail;
            break;
        }
    }
}

if ($recipientEmail === '') {
    echo json_encode(['status' => 'error', 'message' => 'Recipient not found.']);
    exit;
}

function reply_mail_template(string $subject, string $originalMessage, string $reply): string
{
    return notification_render_email_shell(
        'Reply Sent',
        'You Received a Reply from KODUS',
        'A response has been sent regarding your previous message.',
        '<p><strong>Subject:</strong> ' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Your original message:</strong></p>'
        . '<div style="margin:12px 0 18px 0;padding:16px 18px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;white-space:pre-line;">' . nl2br(htmlspecialchars($originalMessage, ENT_QUOTES, 'UTF-8')) . '</div>'
        . '<p><strong>Our reply:</strong></p>'
        . '<div style="margin:12px 0 18px 0;padding:16px 18px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:14px;white-space:pre-line;">' . nl2br(htmlspecialchars($reply, ENT_QUOTES, 'UTF-8')) . '</div>'
        . '<p>Best regards,<br><strong>KODUS Support Team</strong></p>',
        '#0d6efd',
        'KODUS Messaging'
    );
}

try {
    $mail = new PHPMailer(true);
    app_configure_mailer($mail);
    $mail->addAddress($recipientEmail);
    $mail->isHTML(true);
    $mail->Subject = 'Re: ' . (string) ($replyRow['subject'] ?? '(No Subject)');
    $mail->Body = reply_mail_template(
        (string) ($replyRow['subject'] ?? '(No Subject)'),
        (string) ($replyRow['message'] ?? '(No Message)'),
        (string) ($replyRow['reply'] ?? '')
    );
    $mail->AltBody = "Subject: {$replyRow['subject']}\n\nYour message:\n{$replyRow['message']}\n\nOur reply:\n{$replyRow['reply']}";

    $attachmentCsv = trim((string) ($replyRow['attachment'] ?? ''));
    if ($attachmentCsv !== '') {
        foreach (array_filter(array_map('trim', explode(',', $attachmentCsv))) as $filename) {
            $filePath = __DIR__ . '/uploads/reply_attachments/' . $filename;
            if (is_file($filePath)) {
                $mail->addAttachment($filePath);
            }
        }
    }

    $mail->send();
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    error_log('Reply mail delivery failed: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Mail delivery failed.']);
}
