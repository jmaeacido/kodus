<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/base_url.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/theme_helpers.php';

$pendingUserId = $_SESSION['2fa_user_id'] ?? null;
if (!$pendingUserId) {
    auth_redirect_to_public_landing();
}

require_once __DIR__ . '/config.php';

$stmt = $conn->prepare('SELECT id, email, username, first_name, last_name, two_fa_secret FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->bind_param('i', $pendingUserId);
$stmt->execute();
$pendingUser = db_stmt_fetch_one_assoc($stmt);
$stmt->close();

if (!$pendingUser) {
    unset($_SESSION['2fa_user_id'], $_SESSION['remember_me'], $_SESSION['pending_login_password_is_weak']);
    two_factor_clear_pending_secret();
    unset($_SESSION['pending_2fa_recovery_codes']);
    auth_redirect_to_public_landing();
}

$email = (string) ($pendingUser['email'] ?? '');
$username = (string) ($pendingUser['username'] ?? '');
$maskedEmail = preg_replace('/(^.).*(@.*$)/', '$1***$2', $email) ?: $email;
$needsSetup = trim((string) ($pendingUser['two_fa_secret'] ?? '')) === '';
$setupSecret = null;
$setupQrCode = null;
$setupRecoveryCodes = [];
$setupAccountLabel = two_factor_issuer_name() . ': ' . two_factor_user_label($pendingUser);

if ($needsSetup) {
    $setupSecret = two_factor_generate_secret();
    two_factor_store_pending_secret($setupSecret);
    $setupRecoveryCodes = two_factor_generate_recovery_codes();
    $_SESSION['pending_2fa_recovery_codes'] = $setupRecoveryCodes;
    $setupQrCode = two_factor_get_qr_svg_data_uri($pendingUser, $setupSecret);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>2FA Verification</title>
    <link rel="shortcut icon" href="<?php echo $app_root; ?>favicon.ico" type="image/x-icon">
    <?php include __DIR__ . '/page_loader.php'; ?>
    <script src="plugins/jquery/jquery.min.js"></script>
    <style>
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
            width: min(100%, 920px);
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(13, 110, 253, 0.12);
            border-radius: 24px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
            overflow: hidden;
        }
        .verify-hero {
            padding: 28px 32px 22px;
            background: linear-gradient(135deg, #0d6efd 0%, #0b355d 100%);
            color: #fff;
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
            font-size: 1.85rem;
            line-height: 1.2;
        }
        .verify-hero p {
            margin: 0;
            font-size: 1rem;
            line-height: 1.65;
            opacity: 0.95;
        }
        .verify-body {
            padding: 28px 32px 32px;
        }
        .verify-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
        }
        .verify-panel {
            border: 1px solid #dbe7f5;
            border-radius: 20px;
            background: #f8fbff;
            padding: 22px;
            min-height: 100%;
        }
        .verify-panel h2 {
            margin: 0 0 10px;
            font-size: 1.2rem;
        }
        .verify-panel p {
            margin: 0 0 14px;
            color: #64748b;
            line-height: 1.6;
        }
        .verify-qr {
            display: flex;
            justify-content: center;
            margin: 10px 0 16px;
        }
        .verify-qr img {
            width: min(100%, 280px);
            height: auto;
            border-radius: 18px;
            padding: 14px;
            background: #fff;
            border: 1px solid #dbe7f5;
        }
        .verify-manual,
        .verify-code,
        .verify-recovery-list code {
            display: block;
            width: 100%;
            box-sizing: border-box;
            border-radius: 14px;
            padding: 12px 14px;
            border: 1px dashed #cbd5e1;
            background: #fff;
            font-family: Consolas, monospace;
        }
        .verify-code {
            border-style: solid;
            font-size: 1.2rem;
            letter-spacing: 0.28em;
            text-align: center;
            font-weight: 700;
        }
        .verify-actions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        .verify-btn {
            border: 0;
            border-radius: 999px;
            padding: 12px 18px;
            font-weight: 700;
            cursor: pointer;
        }
        .verify-btn-primary {
            background: #0d6efd;
            color: #fff;
        }
        .verify-btn-secondary {
            background: #e2e8f0;
            color: #1e293b;
        }
        .verify-recovery-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .verify-helper {
            font-size: 0.92rem;
            color: #64748b;
            margin-top: 10px;
        }
        .verify-status {
            margin-top: 14px;
            font-size: 0.92rem;
            color: #475569;
        }
        .verify-status.error { color: #b91c1c; }
        .verify-status.success { color: #166534; }
        @media (max-width: 840px) {
            .verify-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="verify-shell">
    <div class="verify-card">
        <div class="verify-hero">
            <div class="verify-eyebrow">KODUS Security Check</div>
            <h1><?= $needsSetup ? 'Set Up Your Authenticator' : 'Verify Your Sign-in' ?></h1>
            <p>
                <?php if ($needsSetup): ?>
                    Authenticator-based 2FA is enabled for <strong><?= htmlspecialchars($username !== '' ? $username : $maskedEmail, ENT_QUOTES, 'UTF-8') ?></strong>. Complete setup below, then enter your code to continue.
                <?php else: ?>
                    Enter the current authenticator code or a saved recovery code for <strong><?= htmlspecialchars($username !== '' ? $username : $maskedEmail, ENT_QUOTES, 'UTF-8') ?></strong>.
                <?php endif; ?>
            </p>
        </div>
        <div class="verify-body">
            <div class="verify-grid">
                <?php if ($needsSetup): ?>
                    <section class="verify-panel">
                        <h2>1. Scan The QR Code</h2>
                        <p>Use Google Authenticator, Microsoft Authenticator, or another compatible app.</p>
                        <div class="verify-qr">
                            <img src="<?= htmlspecialchars((string) $setupQrCode, ENT_QUOTES, 'UTF-8') ?>" alt="Authenticator QR code">
                        </div>
                        <p>Authenticator entry</p>
                        <code class="verify-manual"><?= htmlspecialchars((string) $setupAccountLabel, ENT_QUOTES, 'UTF-8') ?></code>
                        <p>Manual setup key</p>
                        <code class="verify-manual"><?= htmlspecialchars((string) $setupSecret, ENT_QUOTES, 'UTF-8') ?></code>
                    </section>
                    <section class="verify-panel">
                        <h2>2. Save Recovery Codes</h2>
                        <p>Each code can be used one time if you lose access to your authenticator app.</p>
                        <div class="verify-recovery-list">
                            <?php foreach ($setupRecoveryCodes as $code): ?>
                                <code><?= htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8') ?></code>
                            <?php endforeach; ?>
                        </div>
                        <div class="verify-helper">Store these somewhere safe before you continue.</div>
                    </section>
                <?php else: ?>
                    <section class="verify-panel" style="grid-column: 1 / -1;">
                        <h2>Authenticator Verification</h2>
                        <p>Enter a 6-digit authenticator code, or use one of your saved recovery codes if your app is unavailable.</p>
                    </section>
                <?php endif; ?>
            </div>

            <section class="verify-panel" style="margin-top:24px;">
                <h2><?= $needsSetup ? '3. Enter Your Authenticator Code' : 'Enter Your Code' ?></h2>
                <p><?= $needsSetup ? 'After scanning, enter the current 6-digit code from your authenticator app.' : 'Use the latest code from your authenticator app or a saved recovery code.' ?></p>
                <form id="verify2faForm">
                    <input type="text" id="verifyCode" class="verify-code" inputmode="text" autocomplete="one-time-code" maxlength="16" placeholder="------" autofocus>
                    <div class="verify-helper">Codes refresh every 30 seconds. Recovery codes can be longer.</div>
                    <div class="verify-actions">
                        <button type="submit" class="verify-btn verify-btn-primary"><?= $needsSetup ? 'Complete Setup' : 'Verify Sign-in' ?></button>
                        <a href="./" class="verify-btn verify-btn-secondary" style="text-decoration:none;">Back To Login</a>
                    </div>
                    <div id="verifyStatus" class="verify-status"></div>
                </form>
            </section>
        </div>
    </div>
</div>

<script>
window.KODUS_CSRF_TOKEN = <?= json_encode(security_get_csrf_token()) ?>;
window.KODUS_2FA_NEEDS_SETUP = <?= json_encode($needsSetup) ?>;

$(function () {
    const csrfToken = window.KODUS_CSRF_TOKEN || '';
    const needsSetup = !!window.KODUS_2FA_NEEDS_SETUP;
    const $form = $('#verify2faForm');
    const $code = $('#verifyCode');
    const $status = $('#verifyStatus');

    $form.on('submit', function (event) {
        event.preventDefault();
        const code = String($code.val() || '').trim();

        if (!code) {
            $status.text('Enter your authenticator or recovery code first.').removeClass('success').addClass('error');
            return;
        }

        $status.text(needsSetup ? 'Completing authenticator setup...' : 'Verifying your sign-in...').removeClass('error success');

        $.post('verify_2fa_code.php', {
            code,
            mode: needsSetup ? 'setup' : 'verify',
            csrf_token: csrfToken
        }, null, 'json').then(async function (data) {
            if (!data || !data.success) {
                $status.text(data?.message || 'Unable to verify the code.').removeClass('success').addClass('error');
                return;
            }

            if (Array.isArray(data.recovery_codes) && data.recovery_codes.length > 0) {
                $status
                    .html('Setup complete. Save your recovery codes in Settings or open the print view after sign-in. Redirecting...')
                    .removeClass('error')
                    .addClass('success');
            } else if (data.used_recovery_code) {
                $status
                    .text(`Recovery code accepted. You have ${data.remaining_recovery_codes ?? 0} recovery code(s) remaining. Redirecting...`)
                    .removeClass('error')
                    .addClass('success');
            } else {
                $status.text('2FA verified. Redirecting to your workspace...').removeClass('error').addClass('success');
            }

            setTimeout(function () {
                window.location.href = 'home';
            }, 900);
        }).catch(function (error) {
            const payload = error?.responseJSON || {};
            if (payload.requires_password_reset && payload.redirect) {
                window.location.href = payload.redirect;
                return;
            }
            $status.text(payload.message || 'Request failed. Please try again.').removeClass('success').addClass('error');
        });
    });
});
</script>
</body>
</html>
