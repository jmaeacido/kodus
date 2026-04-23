<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/theme_helpers.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    auth_redirect_to_public_landing();
}

$stmt = $conn->prepare('SELECT first_name, last_name, username, email, two_fa_enabled, two_fa_secret, theme_preference FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = db_stmt_fetch_one_assoc($stmt);
$stmt->close();

if (!$user || empty($user['two_fa_enabled']) || trim((string) ($user['two_fa_secret'] ?? '')) === '') {
    http_response_code(403);
}

$codes = $_SESSION['print_recovery_codes'] ?? null;
$hasTwoFactorReady = $user && !empty($user['two_fa_enabled']) && trim((string) ($user['two_fa_secret'] ?? '')) !== '';
$hasPrintableCodes = is_array($codes) && $codes !== [];

$displayName = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
if ($displayName === '') {
    $displayName = (string) ($user['username'] ?? 'KODUS User');
}

$themePreference = theme_normalize_preference($user['theme_preference'] ?? 'light');
$generatedAt = date('F d, Y h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KODUS Recovery Codes</title>
    <style>
        :root {
            --rc-bg: linear-gradient(180deg, #0f172a 0%, #111827 100%);
            --rc-shell: rgba(15, 23, 42, 0.88);
            --rc-panel: rgba(17, 24, 39, 0.92);
            --rc-panel-soft: rgba(30, 41, 59, 0.75);
            --rc-border: rgba(148, 163, 184, 0.2);
            --rc-text: #e5eef8;
            --rc-muted: #9fb2c8;
            --rc-accent: #60a5fa;
            --rc-accent-strong: #2563eb;
            --rc-code-bg: rgba(255, 255, 255, 0.05);
            --rc-code-border: rgba(125, 211, 252, 0.28);
            --rc-warning-bg: rgba(250, 204, 21, 0.12);
            --rc-warning-border: rgba(250, 204, 21, 0.3);
            --rc-warning-text: #fde68a;
            --rc-shadow: 0 28px 64px rgba(2, 6, 23, 0.42);
        }

        body[data-theme="light"] {
            --rc-bg: linear-gradient(180deg, #eef5ff 0%, #f8fafc 100%);
            --rc-shell: rgba(255, 255, 255, 0.92);
            --rc-panel: rgba(255, 255, 255, 0.98);
            --rc-panel-soft: rgba(248, 250, 252, 0.96);
            --rc-border: rgba(13, 110, 253, 0.12);
            --rc-text: #0f172a;
            --rc-muted: #516274;
            --rc-accent: #2563eb;
            --rc-accent-strong: #0d6efd;
            --rc-code-bg: #f8fbff;
            --rc-code-border: rgba(13, 110, 253, 0.2);
            --rc-warning-bg: #fff8db;
            --rc-warning-border: rgba(255, 193, 7, 0.28);
            --rc-warning-text: #9a6700;
            --rc-shadow: 0 28px 64px rgba(15, 23, 42, 0.12);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: var(--rc-bg);
            color: var(--rc-text);
        }

        .recovery-shell {
            max-width: 980px;
            margin: 0 auto;
            padding: 32px 20px 48px;
        }

        .recovery-card {
            background: var(--rc-shell);
            border: 1px solid var(--rc-border);
            border-radius: 28px;
            box-shadow: var(--rc-shadow);
            overflow: hidden;
            backdrop-filter: blur(12px);
        }

        .recovery-hero {
            padding: 30px 32px 26px;
            background:
                radial-gradient(circle at top right, rgba(96, 165, 250, 0.22), transparent 30%),
                linear-gradient(135deg, rgba(37, 99, 235, 0.92) 0%, rgba(15, 23, 42, 0.9) 100%);
            color: #fff;
        }

        .recovery-eyebrow {
            display: inline-block;
            margin-bottom: 10px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            opacity: 0.84;
        }

        .recovery-hero h1 {
            margin: 0 0 10px;
            font-size: 2rem;
            line-height: 1.15;
        }

        .recovery-hero p {
            margin: 0;
            max-width: 700px;
            line-height: 1.7;
            opacity: 0.94;
        }

        .recovery-body {
            padding: 28px 32px 32px;
            background: var(--rc-panel);
        }

        .recovery-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .recovery-meta {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }

        .recovery-meta-card {
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid var(--rc-border);
            background: var(--rc-panel-soft);
        }

        .recovery-meta-card span {
            display: block;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--rc-muted);
            margin-bottom: 8px;
        }

        .recovery-meta-card strong {
            font-size: 1rem;
            line-height: 1.45;
            word-break: break-word;
        }

        .recovery-warning {
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid var(--rc-warning-border);
            background: var(--rc-warning-bg);
            color: var(--rc-warning-text);
            line-height: 1.7;
            margin-bottom: 22px;
        }

        .recovery-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .recovery-code {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-height: 72px;
            padding: 16px;
            border-radius: 18px;
            border: 1px dashed var(--rc-code-border);
            background: var(--rc-code-bg);
        }

        .recovery-code-text {
            font-family: Consolas, monospace;
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-align: center;
            flex: 1 1 auto;
        }

        .recovery-copy-btn {
            appearance: none;
            border: 1px solid var(--rc-border);
            border-radius: 999px;
            background: transparent;
            color: var(--rc-text);
            font-size: 0.82rem;
            font-weight: 700;
            padding: 8px 12px;
            cursor: pointer;
            white-space: nowrap;
        }

        .recovery-copy-btn.is-copied {
            background: rgba(40, 167, 69, 0.16);
            border-color: rgba(40, 167, 69, 0.3);
            color: #8ff0b0;
        }

        .recovery-empty {
            border: 1px dashed var(--rc-border);
            border-radius: 22px;
            padding: 26px 22px;
            background: var(--rc-panel-soft);
            text-align: center;
        }

        .recovery-empty h2 {
            margin: 0 0 10px;
            font-size: 1.35rem;
        }

        .recovery-empty p {
            margin: 0 auto 14px;
            max-width: 620px;
            color: var(--rc-muted);
            line-height: 1.75;
        }

        .recovery-empty-actions {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 16px;
        }

        .recovery-footer {
            margin-top: 22px;
            color: var(--rc-muted);
            line-height: 1.7;
        }

        .btn {
            appearance: none;
            border: 0;
            border-radius: 999px;
            padding: 12px 18px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
        }

        .btn:hover { transform: translateY(-1px); opacity: 0.96; }
        .btn-primary { background: var(--rc-accent-strong); color: #fff; box-shadow: 0 12px 28px rgba(37, 99, 235, 0.28); }
        .btn-secondary { background: transparent; color: var(--rc-text); border: 1px solid var(--rc-border); }

        @media (max-width: 760px) {
            .recovery-shell { padding: 18px 14px 30px; }
            .recovery-hero, .recovery-body { padding-left: 20px; padding-right: 20px; }
            .recovery-meta { grid-template-columns: 1fr; }
            .recovery-grid { grid-template-columns: 1fr; }
        }

        @media print {
            body {
                background: #ffffff !important;
                color: #111827 !important;
            }

            .recovery-shell {
                max-width: none;
                padding: 0;
            }

            .recovery-card,
            .recovery-body,
            .recovery-meta-card,
            .recovery-warning,
            .recovery-code,
            .recovery-empty {
                background: #ffffff !important;
                color: #111827 !important;
                box-shadow: none !important;
            }

            .recovery-card,
            .recovery-meta-card,
            .recovery-warning,
            .recovery-code,
            .recovery-empty {
                border-color: #cbd5e1 !important;
            }

            .recovery-hero {
                background: #ffffff !important;
                color: #111827 !important;
                border-bottom: 1px solid #cbd5e1;
            }

            .print-hide {
                display: none !important;
            }
        }
    </style>
</head>
<body data-theme="<?= htmlspecialchars($themePreference, ENT_QUOTES, 'UTF-8') ?>">
    <div class="recovery-shell">
        <section class="recovery-card">
            <header class="recovery-hero">
                <div class="recovery-eyebrow">KODUS Security Backup</div>
                <h1>Recovery Codes</h1>
                <p>These one-time backup codes let you sign in if your authenticator app is unavailable. Store them somewhere private and offline if possible.</p>
            </header>

            <div class="recovery-body">
                <div class="recovery-toolbar print-hide">
                    <a href="settings" class="btn btn-secondary">Back To Settings</a>
                    <?php if ($hasPrintableCodes): ?>
                        <button type="button" class="btn btn-primary" onclick="window.print()">Print / Save As PDF</button>
                    <?php endif; ?>
                </div>

                <div class="recovery-meta">
                    <div class="recovery-meta-card">
                        <span>Account</span>
                        <strong><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div class="recovery-meta-card">
                        <span>Email</span>
                        <strong><?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div class="recovery-meta-card">
                        <span>Generated</span>
                        <strong><?= htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                </div>

                <?php if ($hasPrintableCodes): ?>
                    <div class="recovery-warning">
                        Each code works once. If you regenerate recovery codes later, this set stops working immediately.
                    </div>

                    <div class="recovery-grid">
                        <?php foreach ($codes as $index => $code): ?>
                            <div class="recovery-code">
                                <div class="recovery-code-text"><?= htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8') ?></div>
                                <button type="button" class="recovery-copy-btn print-hide" data-code="<?= htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8') ?>" data-default-label="Copy">Copy</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="recovery-empty">
                        <h2>No printable recovery codes are loaded right now</h2>
                        <p>
                            KODUS stores recovery codes securely as hashes after setup, so the plain-text list is only available immediately after setup or regeneration.
                            To print or download them again, generate a fresh set from Settings first.
                        </p>
                        <div class="recovery-warning" style="margin-bottom:0;">
                            <?= $hasTwoFactorReady
                                ? 'Regenerating recovery codes will replace your current backup set immediately.'
                                : 'Finish authenticator setup first before recovery codes can be generated.' ?>
                        </div>
                        <div class="recovery-empty-actions print-hide">
                            <a href="settings" class="btn btn-primary">Go To Settings</a>
                            <a href="settings#twofactor" class="btn btn-secondary">Open 2FA Section</a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="recovery-footer">
                    Keep this page away from shared screens, printers, and unsecured folders. If you think these codes were exposed, regenerate a new set from Settings right away.
                </div>
            </div>
        </section>
    </div>

    <?php if ($hasPrintableCodes): ?>
    <script>
    document.querySelectorAll('.recovery-copy-btn').forEach(function (button) {
        button.addEventListener('click', async function () {
            const code = String(button.getAttribute('data-code') || '');
            const defaultLabel = String(button.getAttribute('data-default-label') || 'Copy');

            if (!code) {
                return;
            }

            try {
                await navigator.clipboard.writeText(code);
                button.textContent = 'Copied';
                button.classList.add('is-copied');
                window.setTimeout(function () {
                    button.textContent = defaultLabel;
                    button.classList.remove('is-copied');
                }, 1400);
            } catch (error) {
                button.textContent = 'Copy Failed';
                window.setTimeout(function () {
                    button.textContent = defaultLabel;
                }, 1600);
            }
        });
    });
    </script>
    <?php endif; ?>
</body>
</html>
