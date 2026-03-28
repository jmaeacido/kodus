<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/notification_helpers.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendLoginNotification(string $email, string $fullName, string $ip, string $time, string $username = ''): bool
{
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
    $safeAgent = htmlspecialchars($userAgent, ENT_QUOTES, 'UTF-8');
    $safeIp = htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');
    $safeTime = htmlspecialchars($time, ENT_QUOTES, 'UTF-8');

    $mail = new PHPMailer(true);

    try {
        app_configure_mailer($mail);
        $mail->addAddress($email, $fullName);
        $mail->isHTML(true);
        $mail->Subject = 'Login Notification - KODUS';
        $mail->Body = notification_render_email_shell(
            'Login Alert',
            'A New Login Was Detected',
            'We are notifying you about recent access to your KODUS account.',
            "<p>Hello <strong>{$safeName}</strong>,</p>"
            . "<p>Your account has just logged in successfully.</p>"
            . notification_render_detail_rows([
                'Date & Time' => $time,
                'IP Address' => $ip,
                'Browser / Device' => $userAgent,
            ])
            . "<p>If this was not you, please contact support immediately.</p>",
            '#2563eb',
            'KODUS Security Center'
        );

        $mail->send();
        return true;
    } catch (RuntimeException $e) {
        error_log('Login notification configuration failed for ' . ($username !== '' ? $username : $email) . ': ' . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log('Login notification email failed for ' . ($username !== '' ? $username : $email) . ': ' . $mail->ErrorInfo);
        return false;
    }
}
