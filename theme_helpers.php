<?php

function theme_normalize_preference(?string $theme): string
{
    return strtolower((string) $theme) === 'dark' ? 'dark' : 'light';
}

function theme_cookie_name(): string
{
    return 'kodus_theme_preference';
}

function theme_ensure_schema(mysqli $conn): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'theme_preference'");
    if ($result && $result->num_rows > 0) {
        $column = $result->fetch_assoc();
        if (($column['Default'] ?? null) !== 'light') {
            $conn->query("ALTER TABLE users MODIFY COLUMN theme_preference VARCHAR(10) NOT NULL DEFAULT 'light'");
        }
        $conn->query("UPDATE users SET theme_preference = 'light' WHERE theme_preference IS NULL OR theme_preference NOT IN ('light', 'dark')");
        return;
    }

    $conn->query("ALTER TABLE users ADD COLUMN theme_preference VARCHAR(10) NOT NULL DEFAULT 'light' AFTER is_online");
}

function theme_store_session_preference(?string $theme): void
{
    $_SESSION['theme_preference'] = theme_normalize_preference($theme);
}

function theme_store_client_preference(?string $theme): void
{
    $normalized = theme_normalize_preference($theme);
    $_COOKIE[theme_cookie_name()] = $normalized;

    if (headers_sent()) {
        return;
    }

    setcookie(theme_cookie_name(), $normalized, [
        'expires' => time() + (365 * 24 * 60 * 60),
        'path' => '/',
        'secure' => security_is_https(),
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

function theme_current_preference(): string
{
    return theme_normalize_preference($_SESSION['theme_preference'] ?? $_COOKIE[theme_cookie_name()] ?? 'light');
}
