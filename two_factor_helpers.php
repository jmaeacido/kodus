<?php

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

function two_factor_ensure_schema(mysqli $conn): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    $column = $conn->query("SHOW COLUMNS FROM users LIKE 'two_fa_enabled'");
    if ($column && $column->num_rows > 0) {
        $meta = $column->fetch_assoc() ?: [];
        if (($meta['Default'] ?? null) !== '1') {
            $conn->query("ALTER TABLE users MODIFY COLUMN two_fa_enabled TINYINT(1) NOT NULL DEFAULT 1");
        }
        $conn->query("UPDATE users SET two_fa_enabled = 1 WHERE two_fa_enabled IS NULL");
    }

    $columns = [
        'two_fa_secret' => "ALTER TABLE users ADD COLUMN two_fa_secret VARCHAR(64) NULL DEFAULT NULL AFTER two_fa_enabled",
        'two_fa_confirmed_at' => "ALTER TABLE users ADD COLUMN two_fa_confirmed_at DATETIME NULL DEFAULT NULL AFTER two_fa_secret",
        'two_fa_recovery_codes' => "ALTER TABLE users ADD COLUMN two_fa_recovery_codes TEXT NULL DEFAULT NULL AFTER two_fa_confirmed_at",
        'two_fa_recovery_generated_at' => "ALTER TABLE users ADD COLUMN two_fa_recovery_generated_at DATETIME NULL DEFAULT NULL AFTER two_fa_recovery_codes",
    ];

    foreach ($columns as $column => $sql) {
        $result = $conn->query("SHOW COLUMNS FROM users LIKE '{$column}'");
        if ($result && $result->num_rows > 0) {
            continue;
        }

        $conn->query($sql);
    }

}

function two_factor_service(): Google2FA
{
    static $service = null;

    if ($service instanceof Google2FA) {
        return $service;
    }

    $service = new Google2FA();

    return $service;
}

function two_factor_issuer_name(): string
{
    $issuer = function_exists('app_env') ? (string) (app_env('TWO_FACTOR_ISSUER', 'KODUS') ?? 'KODUS') : 'KODUS';
    $issuer = trim($issuer);

    return $issuer !== '' ? $issuer : 'KODUS';
}

function two_factor_user_label(array $user): string
{
    $username = trim((string) ($user['username'] ?? ''));
    if ($username !== '') {
        return $username;
    }

    $email = trim((string) ($user['email'] ?? ''));
    if ($email !== '') {
        return $email;
    }

    return 'user';
}

function two_factor_has_totp_secret(array $user): bool
{
    return trim((string) ($user['two_fa_secret'] ?? '')) !== '';
}

function two_factor_generate_secret(): string
{
    return two_factor_service()->generateSecretKey();
}

function two_factor_get_qr_code_url(array $user, string $secret): string
{
    return two_factor_service()->getQRCodeUrl(
        two_factor_issuer_name(),
        two_factor_user_label($user),
        $secret
    );
}

function two_factor_get_qr_svg_data_uri(array $user, string $secret): string
{
    $renderer = new ImageRenderer(
        new RendererStyle(280),
        new SvgImageBackEnd()
    );
    $writer = new Writer($renderer);
    $svg = $writer->writeString(two_factor_get_qr_code_url($user, $secret));

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function two_factor_normalize_code(?string $code): string
{
    return preg_replace('/\D/', '', (string) $code) ?? '';
}

function two_factor_verify_totp_code(string $secret, ?string $code, int $window = 1): bool
{
    $normalizedCode = two_factor_normalize_code($code);
    if ($normalizedCode === '') {
        return false;
    }

    return two_factor_service()->verifyKey($secret, $normalizedCode, $window);
}

function two_factor_pending_secret_session_key(): string
{
    return 'pending_2fa_secret';
}

function two_factor_store_pending_secret(string $secret): void
{
    $_SESSION[two_factor_pending_secret_session_key()] = $secret;
}

function two_factor_get_pending_secret(): ?string
{
    $secret = trim((string) ($_SESSION[two_factor_pending_secret_session_key()] ?? ''));
    return $secret !== '' ? $secret : null;
}

function two_factor_clear_pending_secret(): void
{
    unset($_SESSION[two_factor_pending_secret_session_key()]);
}

function two_factor_generate_recovery_codes(int $count = 8): array
{
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $codes[] = strtoupper(bin2hex(random_bytes(4)));
    }

    return $codes;
}

function two_factor_hash_recovery_code(string $code): string
{
    return hash('sha256', strtoupper(trim($code)));
}

function two_factor_store_recovery_codes(mysqli $conn, int $userId, array $codes): bool
{
    $payload = array_map('two_factor_hash_recovery_code', $codes);
    $json = json_encode(array_values($payload));
    $now = date('Y-m-d H:i:s');

    $stmt = $conn->prepare('UPDATE users SET two_fa_recovery_codes = ?, two_fa_recovery_generated_at = ? WHERE id = ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ssi', $json, $now, $userId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function two_factor_parse_recovery_code_hashes(array $user): array
{
    $raw = (string) ($user['two_fa_recovery_codes'] ?? '');
    if (trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    return array_values(array_filter(array_map('strval', $decoded)));
}

function two_factor_recovery_code_count(array $user): int
{
    return count(two_factor_parse_recovery_code_hashes($user));
}

function two_factor_consume_recovery_code(mysqli $conn, array $user, ?string $code): bool
{
    $normalized = strtoupper(trim((string) $code));
    if ($normalized === '') {
        return false;
    }

    $targetHash = two_factor_hash_recovery_code($normalized);
    $hashes = two_factor_parse_recovery_code_hashes($user);
    $remaining = [];
    $matched = false;

    foreach ($hashes as $hash) {
        if (!$matched && hash_equals($hash, $targetHash)) {
            $matched = true;
            continue;
        }
        $remaining[] = $hash;
    }

    if (!$matched) {
        return false;
    }

    $json = json_encode(array_values($remaining));
    $stmt = $conn->prepare('UPDATE users SET two_fa_recovery_codes = ? WHERE id = ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('si', $json, $user['id']);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}
