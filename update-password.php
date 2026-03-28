<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
include('config.php');

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/notification_helpers.php';
require 'vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_submitted'])) {
    security_require_csrf_token();
    $token = $_SESSION['pending_reset_token'] ?? '';
    $password = (string) ($_POST['password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');

    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: reset-password.php");
        exit();
    }

    if (!security_validate_password_strength($password)) {
        $_SESSION['error'] = "Password must be at least 12 characters and include uppercase, lowercase, number, and symbol.";
        header("Location: reset-password.php");
        exit();
    }

    $user = $token !== '' ? security_find_user_by_token($conn, 'reset_token', $token, "reset_token_expiry > NOW()") : null;
    if ($user) {
        $userId = $user['id'];
        $userEmail = $user['email'];

        // Hash the new password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Update password and clear token
        $update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL, remember_token = NULL WHERE id = ?");
        $update->bind_param("si", $hashedPassword, $userId);
        $update->execute();
        $_SESSION['pending_reset_token'] = null;
        security_clear_cookie('remember_token');

        // Send confirmation email
        $mail = new PHPMailer(true);
        try {
            app_configure_mailer($mail);
            $mail->addAddress($userEmail);

            $mail->isHTML(true);
            $mail->Subject = 'Your password has been reset';
            $mail->Body = notification_render_email_shell(
                'Password Updated',
                'Your Password Was Changed',
                'This confirms that your KODUS password has been reset successfully.',
                '<p>Hello,</p>'
                . '<p>Your password has been updated and your account is ready to use with the new credentials.</p>'
                . notification_render_detail_rows([
                    'Account Email' => $userEmail,
                    'Status' => 'Password reset successful',
                ])
                . '<p>If you did not request this change, contact support immediately.</p>',
                '#198754',
                'KODUS Account Support'
            );

            $mail->send();
        } catch (Exception $e) {
            error_log("Password reset email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }

        $_SESSION['success'] = "Your password has been reset successfully.";
        header("Location: ./");
        exit();
    } else {
        $_SESSION['error'] = "Invalid or expired token.";
        header("Location: ./");
        exit();
    }
}
?>
