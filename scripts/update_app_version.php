<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$metaPath = $root . DIRECTORY_SEPARATOR . 'app_meta.php';

if (!is_file($metaPath)) {
    fwrite(STDERR, "app_meta.php not found.\n");
    exit(1);
}

require_once $metaPath;

if (!function_exists('app_meta')) {
    fwrite(STDERR, "app_meta() is unavailable.\n");
    exit(1);
}

$meta = app_meta();
$currentVersion = (string) ($meta['version'] ?? '0.0.0');
$releasedOn = (string) ($meta['released_on'] ?? date('Y-m-d'));
$today = date('Y-m-d');

$allowedExtensions = ['php', 'js', 'css', 'scss', 'json', 'sql', 'md'];
$ignoredPrefixes = [
    'vendor/',
    'storage/',
    'scratch/',
    '.tmp.driveupload/',
    'Knowledge Management/',
];
$ignoredFiles = [
    'composer.lock',
    'package-lock.json',
];

function normalize_git_path(string $path): string
{
    return str_replace('\\', '/', trim($path));
}

function is_relevant_change(string $path, array $allowedExtensions, array $ignoredPrefixes, array $ignoredFiles): bool
{
    if ($path === '') {
        return false;
    }

    $normalized = normalize_git_path($path);

    foreach ($ignoredPrefixes as $prefix) {
        if (stripos($normalized, $prefix) === 0) {
            return false;
        }
    }

    if (in_array($normalized, $ignoredFiles, true)) {
        return false;
    }

    $extension = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));
    if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
        return false;
    }

    return true;
}

function collect_lines(array $command, string $cwd): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, $cwd);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start git process.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException(trim($stderr) !== '' ? trim($stderr) : 'Git command failed.');
    }

    $lines = preg_split('/\r\n|\r|\n/', trim((string) $stdout));
    return array_values(array_filter(array_map('trim', $lines), static fn ($line) => $line !== ''));
}

function extract_paths_from_status_line(string $line): array
{
    if (strlen($line) < 4) {
        return [];
    }

    $payload = trim(substr($line, 3));
    if ($payload === '') {
        return [];
    }

    if (strpos($payload, '->') !== false) {
        $parts = preg_split('/\s+->\s+/', $payload);
        return array_values(array_filter(array_map('trim', $parts), static fn ($part) => $part !== ''));
    }

    return [$payload];
}

try {
    $changedPaths = [];

    foreach (collect_lines(['git', 'status', '--porcelain'], $root) as $line) {
        foreach (extract_paths_from_status_line($line) as $path) {
            $changedPaths[$path] = true;
        }
    }

    $since = $releasedOn . ' 00:00:00';
    foreach (collect_lines(['git', 'log', '--since=' . $since, '--name-only', '--pretty=format:'], $root) as $path) {
        $changedPaths[$path] = true;
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "Version update skipped: " . $exception->getMessage() . "\n");
    exit(1);
}

$relevantChanges = array_values(array_filter(array_keys($changedPaths), static function (string $path) use ($allowedExtensions, $ignoredPrefixes, $ignoredFiles): bool {
    return is_relevant_change($path, $allowedExtensions, $ignoredPrefixes, $ignoredFiles);
}));

$relevantChanges = array_values(array_unique($relevantChanges));
sort($relevantChanges);

if ($relevantChanges === []) {
    fwrite(STDOUT, "No relevant changes detected. Version remains {$currentVersion}.\n");
    exit(0);
}

if ($releasedOn === $today) {
    fwrite(STDOUT, "Relevant changes detected, but app version {$currentVersion} is already dated {$today}.\n");
    fwrite(STDOUT, 'Changed files: ' . implode(', ', $relevantChanges) . "\n");
    exit(0);
}

$parts = array_map('intval', explode('.', $currentVersion));
while (count($parts) < 3) {
    $parts[] = 0;
}
$parts[2]++;
$nextVersion = implode('.', array_slice($parts, 0, 3));

$fileContents = file_get_contents($metaPath);
if ($fileContents === false) {
    fwrite(STDERR, "Unable to read app_meta.php.\n");
    exit(1);
}

$updatedContents = preg_replace(
    ["/'version'\s*=>\s*'[^']*'/", "/'released_on'\s*=>\s*'[^']*'/"],
    ["'version' => '{$nextVersion}'", "'released_on' => '{$today}'"],
    $fileContents,
    1
);

if (!is_string($updatedContents)) {
    fwrite(STDERR, "Unable to update app_meta.php.\n");
    exit(1);
}

if (file_put_contents($metaPath, $updatedContents) === false) {
    fwrite(STDERR, "Unable to write updated app_meta.php.\n");
    exit(1);
}

fwrite(STDOUT, "Updated app version from {$currentVersion} to {$nextVersion}.\n");
fwrite(STDOUT, "Release date set to {$today}.\n");
fwrite(STDOUT, 'Relevant changes: ' . implode(', ', $relevantChanges) . "\n");
