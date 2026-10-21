<?php
declare(strict_types=1);

namespace JeePHP\Cache;

use RuntimeException;

final class FileCache
{
    public function __construct(private readonly string $directory) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return $default;
        }
        $record = json_decode((string) @file_get_contents($path), true);
        if (!is_array($record) || !isset($record['expires_at']) || (int) $record['expires_at'] < time()) {
            @unlink($path);
            return $default;
        }
        return $record['value'] ?? $default;
    }

    public function put(string $key, mixed $value, int $seconds = 300): void
    {
        if ($seconds < 0) {
            throw new RuntimeException('Cache duration cannot be negative.');
        }
        $this->ensureDirectory();
        $path = $this->path($key);
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(4));
        $json = json_encode([
            'expires_at' => time() + $seconds,
            'value' => $value,
        ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        if (file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Could not write the cache entry.');
        }
        @chmod($path, 0600);
    }

    public function remember(string $key, int $seconds, callable $callback): mixed
    {
        $marker = new \stdClass();
        $cached = $this->get($key, $marker);
        if ($cached !== $marker) {
            return $cached;
        }
        $value = $callback();
        $this->put($key, $value, $seconds);
        return $value;
    }

    public function forget(string $key): bool
    {
        $path = $this->path($key);
        return !is_file($path) || unlink($path);
    }

    public function clear(): int
    {
        $this->ensureDirectory();
        $removed = 0;
        foreach (glob($this->directory . '/*.json') ?: [] as $path) {
            if (is_file($path) && unlink($path)) {
                $removed++;
            }
        }
        return $removed;
    }

    private function path(string $key): string
    {
        if ($key === '') {
            throw new RuntimeException('Cache key cannot be empty.');
        }
        return $this->directory . '/' . hash('sha256', $key) . '.json';
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Could not create cache folder: ' . $this->directory);
        }
        @chmod($this->directory, 0700);
    }
}
