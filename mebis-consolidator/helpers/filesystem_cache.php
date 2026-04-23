<?php

declare(strict_types=1);

namespace MebisConsolidator\Helpers;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

final class FilesystemCache implements CacheInterface
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $baseDirectory = $directory ?: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mebis-phpspreadsheet-cache';
        $this->directory = rtrim($baseDirectory, DIRECTORY_SEPARATOR);

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
    }

    public function __destruct()
    {
        $this->clear();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->pathForKey($key);
        if (!is_file($path)) {
            return $default;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return $default;
        }

        $value = @unserialize($contents);
        return $value === false && $contents !== serialize(false) ? $default : $value;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        return @file_put_contents($this->pathForKey($key), serialize($value), LOCK_EX) !== false;
    }

    public function delete(string $key): bool
    {
        $path = $this->pathForKey($key);
        return !is_file($path) || @unlink($path);
    }

    public function clear(): bool
    {
        if (!is_dir($this->directory)) {
            return true;
        }

        $success = true;
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*.cache') ?: [] as $path) {
            if (is_file($path) && !@unlink($path)) {
                $success = false;
            }
        }

        return $success;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get((string) $key, $default);
        }

        return $values;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            $success = $this->set((string) $key, $value, $ttl) && $success;
        }

        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            $success = $this->delete((string) $key) && $success;
        }

        return $success;
    }

    public function has(string $key): bool
    {
        return is_file($this->pathForKey($key));
    }

    private function pathForKey(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . sha1($key) . '.cache';
    }
}
