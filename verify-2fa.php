<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/base_url.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/theme_helpers.php';

$pendingUserId = $_SESSION['2fa_user_id'] ?? null;
if (!$pendingUserId) {
    header("Location: ./");
    exit;
}

$stmt = $conn->prepare('SELECT email, username FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->bind_param('i', $pendingUserId);
$stmt->execute();
$result = $stmt->get_result();
$pendingUser = $result->fetch_assoc();
$stmt->close();

if (!$pendingUser) {
    unset($_SESSION['2fa_user_id'], $_SESSION['remember_me'], $_SESSION['2fa_code'], $_SESSION['2fa_expiry']);
    header("Location: ./");
    exit;
}

$email = (string) ($pendingUser['email'] ?? '');
$username = (string) ($pendingUser['username'] ?? '');
$maskedEmail = preg_replace('/(^.).*(@.*$)/', '$1***$2', $email) ?: $email;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>2FA Verification</title>
    <link rel="shortcut icon" href="<?php echo $base_url; ?>kodus/favicon.ico" type="image/x-icon">
    <?php include __DIR__ . '/page_loader.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        :root {
            color-scheme: light;
        }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(13, 110, 253, 0.18), transparent 32%),
                radial-gradient(circle at bottom right, rgba(25, 135, 84, 0.16), transparent 28%),
                linear-gradient(180deg, #eef4fb 0%, #f8fafc 100%);
            color: #1f2937;
        }
        .verify-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            box-sizing: border-box;
        }
        .verify-card {
            width: min(100%, 480px);
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(13, 110, 253, 0.12);
            border-radius: 24px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
            overflow: hidden;
        }
        .verify-hero {
            padding: 28px 28px 20px;
            background: linear-gradient(135deg, #0d6efd 0%, #0b355d 100%);
            color: #ffffff;
        }
        .verify-eyebrow {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            opacity: 0.82;
            margin-bottom: 10px;
        }
        .verify-hero h1 {
            margin: 0 0 10px;
            font-size: 1.75rem;
            line-height: 1.2;
        }
        .verify-hero p {
            margin: 0;
            font-size: 0.98rem;
            line-height: 1.65;
            opacity: 0.94;
        }
        .verify-body {
            padding: 24px 28px 28px;
        }
        .verify-status {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 18px;
            background: #f8fafc;
            border: 1px solid #dbe7f5;
            border-radius: 18px;
        }
        .verify-spinner {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 3px solid rgba(13, 110, 253, 0.16);
            border-top-color: #0d6efd;
            animation: spin 0.9s linear infinite;
            flex: 0 0 auto;
        }
        .verify-status strong {
            display: block;
            font-size: 0.98rem;
            margin-bottom: 2px;
            color: #0f172a;
        }
        .verify-status span {
            display: block;
            color: #64748b;
            font-size: 0.92rem;
            line-height: 1.5;
        }
        .verify-tips {
            margin: 18px 0 0;
            padding-left: 1.1rem;
            color: #64748b;
            font-size: 0.92rem;
            line-height: 1.6;
        }
        .verify-tips li + li {
            margin-top: 8px;
        }
        .swal2-popup {
            border-radius: 20px !important;
        }
        .verify-swal-popup {
            padding: 1.5rem !important;
        }
        .verify-swal-title {
            padding-bottom: 0.25rem !important;
        }
        .verify-swal-input {
            letter-spacing: 0.32em;
            text-align: center;
            font-size: 1.35rem !important;
            font-weight: 700;
        }
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>
<body>
<div class="verify-shell">
    <div class="verify-card">
        <div class="verify-hero">
            <div class="verify-eyebrow">KODUS Security Check</div>
            <h1>Verify Your Sign-in</h1>
            <p>We are preparing your verification step for <strong><?= htmlspecialchars($username !== '' ? $username : $maskedEmail, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
        </div>
        <div class="verify-body">
            <div class="verify-status">
                <div class="verify-spinner" aria-hidden="true"></div>
                <div>
                    <strong>Sending your one-time code</strong>
                    <span>Please wait while we deliver a 6-digit code to <strong><?= htmlspecialchars($maskedEmail, ENT_QUOTES, 'UTF-8') ?></strong>.</span>
                </div>
            </div>
            <ul class="verify-tips">
                <li>Keep this page open while we send the code.</li>
                <li>Check your inbox and spam folder if it does not appear right away.</li>
                <li>The verification code expires in 5 minutes.</li>
            </ul>
        </div>
    </div>
</div>

<script>
window.KODUS_CSRF_TOKEN = <?= json_encode(security_get_csrf_token()) ?>;

(async function () {
    const csrfToken = window.KODUS_CSRF_TOKEN || '';

    try {
        const sendResponse = await $.post('send_2fa_code.php', { csrf_token: csrfToken }, null, 'json');
        if (!sendResponse || !sendResponse.success) {
            throw new Error(sendResponse?.message || 'Unable to send the verification code.');
        }
    } catch (error) {
        await Swal.fire({
            icon: 'error',
            title: '2FA Code Not Sent',
            text: error?.responseJSON?.message || error?.message || 'Unable to send the verification code right now.'
        });
        window.location.href = './';
        return;
    }

    const result = await Swal.fire({
        title: 'Enter your 6-digit code',
        html: '<p style="margin:0;">We sent a verification code to <strong><?= htmlspecialchars($maskedEmail, ENT_QUOTES, 'UTF-8') ?></strong>.</p>',
        customClass: {
            popup: 'verify-swal-popup',
            title: 'verify-swal-title',
            input: 'verify-swal-input'
        },
        input: 'text',
        inputAttributes: {
            maxlength: 6,
            autocapitalize: 'off',
            autocorrect: 'off',
            inputmode: 'numeric'
        },
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        allowEscapeKey: () => !Swal.isLoading(),
        didOpen: () => {
            const input = Swal.getInput();
            if (input) {
                input.setAttribute('placeholder', '------');
                input.focus();
            }
        },
        confirmButtonText: 'Verify',
        showCancelButton: true,
        cancelButtonText: 'Back to Login',
        inputValidator: (value) => {
            if (!value) {
                return 'Enter the 6-digit code.';
            }
        },
        preConfirm: (code) => {
            const trimmedCode = String(code || '').trim();
            if (trimmedCode === '') {
                Swal.showValidationMessage('Enter the 6-digit code.');
                return false;
            }

            return $.post('verify_2fa_code.php', { code: code, csrf_token: csrfToken }, null, 'json')
                .then(data => {
                    if (!data.success) {
                        Swal.showValidationMessage(data.message || 'Invalid code.');
                    }
                    return data;
                })
                .catch(error => {
                    Swal.showValidationMessage(error?.responseJSON?.message || 'Request failed. Please try again.');
                });
        }
    });

    if (result.isConfirmed && result.value && result.value.success) {
        await Swal.fire({
            icon: 'success',
            title: '2FA Verified',
            text: 'Redirecting to your workspace...',
            timer: 1500,
            showConfirmButton: false
        });
        window.location.href = 'home';
        return;
    }

    window.location.href = './';
})();
</script>

</body>
</html>
