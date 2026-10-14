<?php
declare(strict_types=1);

use JeePHP\Database\Database;
use JeePHP\Database\TableQuery;

if (!function_exists('table_connect')) {
    function table_connect(string $table): TableQuery
    {
        return new TableQuery(Database::connection(), $table);
    }
}

if (!function_exists('table_fetch')) {
    function table_fetch(string $table): array
    {
        return table_connect($table)->get();
    }
}

if (!function_exists('table_load')) {
    function table_load(string $table): TableQuery
    {
        return table_connect($table);
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('view')) {
    function view(string $name, array $data = []): void
    {
        if (!preg_match('/^[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*$/', $name)) {
            throw new InvalidArgumentException('Invalid view name: ' . $name);
        }
        $path = dirname(__DIR__, 3) . '/app/Views/' . $name . '.php';
        if (!is_file($path)) {
            throw new RuntimeException('View not found: ' . $name);
        }
        extract($data, EXTR_SKIP);
        require $path;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim(getenv('APP_URL') ?: '', '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $status = 302): never
    {
        header('Location: ' . url($path), true, $status);
        exit;
    }
}

if (!function_exists('session')) {
    function session(string $key, mixed ...$values): mixed
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if ($values !== []) {
            $_SESSION[$key] = $values[0];
            return $values[0];
        }
        return $_SESSION[$key] ?? null;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        $token = session('_csrf_token');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            session('_csrf_token', $token);
        }
        return $token;
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(?string $token = null): bool
    {
        $knownToken = session('_csrf_token');
        $submittedToken = $token ?? ($_POST['_token'] ?? '');
        return is_string($knownToken)
            && is_string($submittedToken)
            && $knownToken !== ''
            && hash_equals($knownToken, $submittedToken);
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed ...$values): mixed
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if ($values !== []) {
            $_SESSION['_flash'][$key] = $values[0];
            return $values[0];
        }
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        $input = session('_old_input', []);
        return is_array($input) ? ($input[$key] ?? $default) : $default;
    }
}

if (!function_exists('validate')) {
    function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            foreach (explode('|', (string) $fieldRules) as $rule) {
                [$name, $argument] = array_pad(explode(':', $rule, 2), 2, null);
                $invalid = match ($name) {
                    'required' => $value === null || $value === '',
                    'email' => $value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL),
                    'min' => $value !== null && mb_strlen((string) $value) < (int) $argument,
                    'max' => $value !== null && mb_strlen((string) $value) > (int) $argument,
                    default => throw new InvalidArgumentException('Unknown validation rule: ' . $name),
                };
                if ($invalid) {
                    $errors[$field][] = $field . ' failed the ' . $name . ' rule.';
                }
            }
        }
        return $errors;
    }
}
