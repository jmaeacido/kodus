<?php
require_once __DIR__ . '/env_helpers.php';

if (!function_exists('app_normalize_url_path')) {
    function app_normalize_url_path(string $path, bool $trailingSlash = true): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '' || $path === '/') {
            return '/';
        }

        $path = '/' . trim($path, '/');

        return $trailingSlash ? ($path . '/') : $path;
    }
}

if (!function_exists('app_public_directory_name')) {
    function app_public_directory_name(string $defaultDirectory): string
    {
        $configuredDirectory = app_env('APP_PUBLIC_DIRECTORY');
        if (is_string($configuredDirectory) && trim($configuredDirectory) !== '') {
            return trim(str_replace('\\', '/', $configuredDirectory), '/');
        }

        return trim(str_replace('\\', '/', $defaultDirectory), '/');
    }
}

if (!function_exists('app_detect_base_url_prefix')) {
    function app_detect_base_url_prefix(string $appDirectory): string
    {
        $configuredPrefix = app_env('APP_BASE_PATH');
        if ($configuredPrefix !== null && trim($configuredPrefix) !== '') {
            return app_normalize_url_path($configuredPrefix);
        }

        $serverPaths = [
            $_SERVER['SCRIPT_NAME'] ?? '',
            $_SERVER['PHP_SELF'] ?? '',
            $_SERVER['REQUEST_URI'] ?? '',
        ];

        $needle = '/' . trim($appDirectory, '/') . '/';

        foreach ($serverPaths as $serverPath) {
            $normalizedPath = str_replace('\\', '/', (string) $serverPath);
            $position = strpos($normalizedPath, $needle);

            if ($position === false) {
                continue;
            }

            $prefix = substr($normalizedPath, 0, $position);
            return app_normalize_url_path($prefix);
        }

        return '/';
    }
}

if (!function_exists('app_detect_public_root')) {
    function app_detect_public_root(string $defaultDirectory): string
    {
        $configuredRoot = app_env('APP_PUBLIC_ROOT');
        if (is_string($configuredRoot) && trim($configuredRoot) !== '') {
            return app_normalize_url_path($configuredRoot);
        }

        $configuredUrl = app_env('APP_URL');
        if (is_string($configuredUrl) && trim($configuredUrl) !== '') {
            $path = parse_url(trim($configuredUrl), PHP_URL_PATH);
            if (is_string($path) && trim($path) !== '') {
                return app_normalize_url_path($path);
            }
        }

        $publicDirectory = app_public_directory_name($defaultDirectory);
        $basePrefix = app_detect_base_url_prefix($publicDirectory);

        return $basePrefix . $publicDirectory . '/';
    }
}

if (!function_exists('app_detect_base_url_from_root')) {
    function app_detect_base_url_from_root(string $publicRoot, string $publicDirectory): string
    {
        $publicRoot = app_normalize_url_path($publicRoot);
        $publicDirectory = trim(str_replace('\\', '/', $publicDirectory), '/');

        if ($publicDirectory === '') {
            return '/';
        }

        $needle = '/' . $publicDirectory . '/';
        if (substr($publicRoot, -strlen($needle)) === $needle) {
            $prefix = substr($publicRoot, 0, -strlen($needle));
            return app_normalize_url_path($prefix);
        }

        return '/';
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        $appDirectory = basename(__DIR__);
        $root = app_detect_public_root($appDirectory);
        $normalizedPath = ltrim(str_replace('\\', '/', $path), '/');

        return $normalizedPath === '' ? $root : ($root . $normalizedPath);
    }
}

$filesystemDirectory = basename(__DIR__);
$publicDirectory = app_public_directory_name($filesystemDirectory);
$app_root = app_detect_public_root($filesystemDirectory);
$base_url = app_detect_base_url_from_root($app_root, $publicDirectory);

?>
