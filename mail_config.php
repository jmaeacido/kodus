<?php

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/env_helpers.php';

function app_get_mail_config(): array
{
    $port = (int) (app_env('SMTP_PORT', '465') ?? '465');
    $encryption = app_env('SMTP_ENCRYPTION');

    if ($encryption === null) {
        $encryption = $port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    }

    return [
        'host' => app_env('SMTP_HOST'),
        'port' => $port,
        'username' => app_env('SMTP_USERNAME'),
        'password' => app_env('SMTP_PASSWORD'),
        'from_address' => app_env('SMTP_FROM_ADDRESS'),
        'from_name' => app_env('SMTP_FROM_NAME', 'KODUS'),
        'encryption' => $encryption,
    ];
}

function app_configure_mailer(PHPMailer $mail): void
{
    $config = app_get_mail_config();
    $required = [
        'host' => 'SMTP_HOST',
        'username' => 'SMTP_USERNAME',
        'password' => 'SMTP_PASSWORD',
    ];

    foreach ($required as $field => $envName) {
        if (empty($config[$field])) {
            throw new RuntimeException("Missing SMTP configuration: {$envName}");
        }
    }

    $fromAddress = $config['from_address'] ?: $config['username'];

    $mail->isSMTP();
    $mail->Host = $config['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['username'];
    $mail->Password = $config['password'];
    $mail->SMTPSecure = $config['encryption'];
    $mail->Port = $config['port'];
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($fromAddress, $config['from_name']);
    $mail->isHTML(true);
}
