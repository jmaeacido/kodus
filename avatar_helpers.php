<?php

require_once __DIR__ . '/base_url.php';

function avatar_default_url(string $baseUrl): string
{
    return app_url('dist/img/default.webp');
}

function avatar_has_local_picture(?string $picture, string $rootDir): bool
{
    $picture = trim((string) $picture);
    if ($picture === '' || strtolower($picture) === 'default.webp') {
        return false;
    }

    return is_file(rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . $picture);
}

function avatar_resolve_url(?string $picture, ?string $ssoAvatarUrl, string $baseUrl, string $rootDir): string
{
    if (avatar_has_local_picture($picture, $rootDir)) {
        return app_url('dist/img/' . rawurlencode((string) $picture));
    }

    $ssoAvatarUrl = trim((string) $ssoAvatarUrl);
    if ($ssoAvatarUrl !== '') {
        return $ssoAvatarUrl;
    }

    return avatar_default_url($baseUrl);
}
