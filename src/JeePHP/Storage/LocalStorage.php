<?php
declare(strict_types=1);

namespace JeePHP\Storage;

use RuntimeException;

final class LocalStorage
{
    public function __construct(private readonly string $projectRoot) {}

    public function put(string $path, string $contents, string $disk = 'private'): string
    {
        if ($disk === 'public' && preg_match('/\.(?:php\d*|phtml|phar|html?|svg|js)$/i', $path) === 1) {
            throw new RuntimeException('Executable and active-content files cannot be written to public storage.');
        }
        $destination = $this->resolve($path, $disk);
        $directory = dirname($destination);
        if (!is_dir($directory) && !mkdir($directory, $disk === 'public' ? 0755 : 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create storage folder.');
        }
        $temporary = $destination . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || !rename($temporary, $destination)) {
            @unlink($temporary);
            throw new RuntimeException('Could not save the storage file.');
        }
        @chmod($destination, $disk === 'public' ? 0644 : 0600);
        return $disk === 'public' ? 'storage/' . ltrim(str_replace('\\', '/', $path), '/') : $path;
    }

    public function get(string $path, string $disk = 'private'): string
    {
        $resolved = $this->resolve($path, $disk);
        if (!is_file($resolved) || !is_readable($resolved)) {
            throw new RuntimeException('Stored file not found: ' . $path);
        }
        return (string) file_get_contents($resolved);
    }

    public function exists(string $path, string $disk = 'private'): bool
    {
        return is_file($this->resolve($path, $disk));
    }

    public function delete(string $path, string $disk = 'private'): bool
    {
        $resolved = $this->resolve($path, $disk);
        return !is_file($resolved) || unlink($resolved);
    }

    public function publicPath(string $path): string
    {
        $this->resolve($path, 'public');
        return '/storage/' . implode('/', self::segments($path));
    }

    private function resolve(string $path, string $disk): string
    {
        if (!in_array($disk, ['public', 'private'], true)) {
            throw new RuntimeException('Storage disk must be public or private.');
        }
        $segments = self::segments($path);
        $base = $disk === 'public'
            ? $this->projectRoot . '/public/storage'
            : $this->projectRoot . '/storage/app/private';
        $current = $disk === 'public' ? $this->projectRoot . '/public' : $this->projectRoot . '/storage';
        $parent = $disk === 'public' ? $current : $current . '/app';
        if (is_link($current) || is_link($parent) || is_link($base)) {
            throw new RuntimeException('Storage folders cannot be symbolic links.');
        }
        foreach (array_slice($segments, 0, -1) as $segment) {
            $current .= '/' . $segment;
            if (is_link($current)) {
                throw new RuntimeException('Storage paths cannot pass through symbolic links.');
            }
        }
        $resolved = $base . '/' . implode('/', $segments);
        if (is_link($resolved)) {
            throw new RuntimeException('Stored files cannot be symbolic links.');
        }
        $parent = dirname($resolved);
        $existing = realpath($parent);
        $baseReal = realpath($base);
        if ($existing !== false && $baseReal !== false && !str_starts_with($existing . DIRECTORY_SEPARATOR, $baseReal . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Storage path escapes its configured disk.');
        }
        return $resolved;
    }

    private static function segments(string $path): array
    {
        $normalized = str_replace('\\', '/', trim($path));
        $segments = explode('/', $normalized);
        if ($normalized === '' || in_array('', $segments, true) || in_array('.', $segments, true) || in_array('..', $segments, true)) {
            throw new RuntimeException('Storage paths must be relative and cannot contain dot segments.');
        }
        foreach ($segments as $segment) {
            if (!preg_match('/^[A-Za-z0-9._-]+$/', $segment)) {
                throw new RuntimeException('Storage path contains unsupported characters.');
            }
        }
        return $segments;
    }
}
