<?php
declare(strict_types=1);

namespace JeePHP\Http;

final class Request
{
    private static string $urlExtensionToRemove = '';

    private function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        private readonly array $routeParameters = []
    ) {}

    public static function configureUrlExtensionRemoval(string $extension): void
    {
        $extension = trim($extension);
        if (!preg_match('/^\.[A-Za-z0-9]+$/', $extension)) {
            throw new \InvalidArgumentException('URL extension must look like .php.');
        }

        self::$urlExtensionToRemove = strtolower($extension);
    }

    public static function removeConfiguredUrlExtension(string $path): string
    {
        $extension = self::$urlExtensionToRemove;
        if ($extension === '') {
            return $path;
        }

        $pathLength = strcspn($path, '?#');
        $pathname = substr($path, 0, $pathLength);
        if (str_ends_with(strtolower($pathname), $extension)) {
            $pathname = substr($pathname, 0, -strlen($extension));
        }

        return $pathname . substr($path, $pathLength);
    }

    public static function basePath(): string
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        foreach (['/public/index.php', '/index.php'] as $entryPoint) {
            if (str_ends_with($script, $entryPoint)) {
                return rtrim(substr($script, 0, -strlen($entryPoint)), '/');
            }
        }

        return '';
    }

    public static function capture(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $extensionlessPath = self::removeConfiguredUrlExtension($path);
        if ($extensionlessPath !== $path) {
            if (in_array($method, ['GET', 'HEAD'], true)) {
                $query = $_SERVER['QUERY_STRING'] ?? '';
                header('Location: ' . $extensionlessPath . ($query !== '' ? '?' . $query : ''), true, 301);
                exit;
            }
            $path = $extensionlessPath;
        }

        $basePath = self::basePath();
        if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        return new self($method, $path, $_GET, $_POST);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_replace($this->query, $this->body);
    }

    public function only(string ...$keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParameters[$key] ?? $default;
    }

    public function withRouteParameters(array $parameters): self
    {
        return new self($this->method, $this->path, $this->query, $this->body, $parameters);
    }
}
