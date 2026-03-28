<?php

use Dotenv\Dotenv;

function app_load_environment(): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    if (class_exists(Dotenv::class)) {
        $baseEnv = Dotenv::createImmutable(__DIR__, '.env');
        $baseEnv->safeLoad();

        $localEnv = Dotenv::createMutable(__DIR__, '.env.local');
        $localEnv->safeLoad();
    }

    $loaded = true;
}

function app_env(string $key, ?string $default = null): ?string
{
    app_load_environment();

    $value = getenv($key);
    if ($value === false && array_key_exists($key, $_SERVER)) {
        $value = $_SERVER[$key];
    }
    if ($value === false && array_key_exists($key, $_ENV)) {
        $value = $_ENV[$key];
    }

    if ($value === false || $value === null) {
        return $default;
    }

    $value = is_string($value) ? trim($value) : (string) $value;

    if ($value === '' || app_env_is_placeholder($key, $value)) {
        return $default;
    }

    return $value;
}

function app_env_is_placeholder(string $key, string $value): bool
{
    $normalizedKey = strtoupper($key);
    $normalizedValue = strtolower(trim($value));

    $placeholders = [
        'SMTP_HOST' => ['smtp.example.com'],
        'SMTP_USERNAME' => ['your-email@example.com'],
        'SMTP_PASSWORD' => ['your-app-password'],
        'SMTP_FROM_ADDRESS' => ['your-email@example.com'],
    ];

    return in_array($normalizedValue, $placeholders[$normalizedKey] ?? [], true);
}
