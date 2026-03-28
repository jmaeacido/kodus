<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
include('config.php'); // Database connection
include('base_url.php');
require_once __DIR__ . '/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/notification_helpers.php';

// Load PHPMailer classes
require 'vendor/autoload.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    security_require_csrf_token();
    $email = trim((string) ($_POST['email'] ?? ''));
    $genericMessage = "If the account exists, a reset link has been sent.";
    $rateLimitKey = 'forgot-password:' . hash('sha256', strtolower($email) . '|' . security_get_client_ip());

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !security_rate_limit_check($rateLimitKey, 3, 900)) {
        $_SESSION['status'] = $genericMessage;
        $_SESSION['status_type'] = "success";
        header("Location: forgot-password");
        exit();
    }

    // Check if email exists in the database
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Generate reset token and expiry time
        $token = bin2hex(random_bytes(32));
        $hashedToken = security_hash_token($token);
        $expiry = date("Y-m-d H:i:s", strtotime("+24 hours"));

        // Store token and expiry in the database
        $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
        $stmt->bind_param("sss", $hashedToken, $expiry, $email);
        $stmt->execute();

        // Reset password link
        $resetBaseUrl = rtrim($base_url, '/');
        $resetHost = parse_url($resetBaseUrl, PHP_URL_HOST) ?: '';
        if (!security_is_local_host($resetHost)) {
            $resetBaseUrl = preg_replace('#^http://#i', 'https://', $resetBaseUrl);
        }
        $resetLink = $resetBaseUrl . "/kodus/reset-password.php?token=" . urlencode($token);

        // Send email with PHPMailer
        $mail = new PHPMailer(true);

        try {
            app_configure_mailer($mail);

            // Email headers and content
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Reset Your Password';
            $mail->Body = notification_render_email_shell(
                'Password Reset',
                'Reset Your Password',
                'We received a request to reset your KODUS password.',
                '<p>Use the secure link below to choose a new password.</p>'
                . notification_render_action_button($resetLink, 'Reset Password', '#dc3545')
                . notification_render_detail_rows([
                    'Reset Link Validity' => '24 hours',
                    'Requested For' => $email,
                ])
                . '<p>If you did not request a password reset, you can safely ignore this email.</p>',
                '#dc3545',
                'KODUS Account Support'
            );

            $mail->send();
        } catch (RuntimeException $e) {
            error_log("Password reset email configuration failed: {$e->getMessage()}");
        } catch (Exception $e) {
            error_log("Password reset email failed: {$mail->ErrorInfo}");
        }
    }

    security_rate_limit_reset($rateLimitKey);

    $_SESSION['status'] = $genericMessage;
    $_SESSION['status_type'] = "success";

    // Redirect back to forgot-password.php
    header("Location: forgot-password");
    exit();
}
?>
