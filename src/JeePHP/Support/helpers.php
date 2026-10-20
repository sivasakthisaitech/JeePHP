<?php
declare(strict_types=1);

use JeePHP\Database\Database;
use JeePHP\Database\TableSchema;
use JeePHP\Database\TableQuery;
use JeePHP\Http\Request;
use JeePHP\Routing\Router;
use JeePHP\Security\Security;

if (!function_exists('route')) {
    function route(string $definition, string $action): void
    {
        Router::registerCurrent($definition, $action);
    }
}

if (!function_exists('url_remove')) {
    function url_remove(string $extension): void
    {
        Request::configureUrlExtensionRemoval($extension);
    }
}

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

if (!function_exists('fetch_table')) {
    function fetch_table(string $table): TableQuery
    {
        return table_connect($table);
    }
}

if (!function_exists('table_load')) {
    function table_load(string $table): TableQuery
    {
        return table_connect($table);
    }
}

if (!function_exists('create_table')) {
    function create_table(string $table): TableSchema
    {
        return new TableSchema(Database::connection(), $table);
    }
}

if (!function_exists('drop_table')) {
    function drop_table(string $table): void
    {
        TableSchema::drop(Database::connection(), $table);
    }
}

if (!function_exists('load_table')) {
    function load_table(string $table): TableQuery
    {
        return table_load($table);
    }
}

if (!function_exists('data')) {
    function data(TableQuery $query): array
    {
        return $query->data();
    }
}

if (!function_exists('json_data')) {
    function json_data(array $data): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hash_password')) {
    function hash_password(string $password): string
    {
        if ($password === '') {
            throw new InvalidArgumentException('Password cannot be empty.');
        }

        return password_hash($password, PASSWORD_DEFAULT);
    }
}

if (!function_exists('verify_password')) {
    function verify_password(string $password, string $hashedPassword): bool
    {
        return $hashedPassword !== '' && password_verify($password, $hashedPassword);
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

        ob_start();
        try {
            require $path;
            $html = (string) ob_get_clean();
        } catch (Throwable $error) {
            ob_end_clean();
            throw $error;
        }

        echo render_view_data($html, $data);
    }
}

if (!function_exists('render_view_data')) {
    function render_view_data(string $html, array $data): string
    {
        // Repeat a small HTML block for each row: {#users}...{/users}.
        $html = preg_replace_callback(
            '/\{#([A-Za-z_][A-Za-z0-9_]*)\}(.*?)\{\/\1\}/s',
            static function (array $match) use ($data): string {
                $rows = $data[$match[1]] ?? [];
                if (!is_array($rows)) {
                    return '';
                }

                $output = '';
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $output .= preg_replace_callback(
                        '/\{([A-Za-z_][A-Za-z0-9_]*)\}/',
                        static fn (array $field): string => array_key_exists($field[1], $row)
                            ? e($row[$field[1]])
                            : $field[0],
                        $match[2]
                    );
                }
                return $output;
            },
            $html
        ) ?? $html;

        return preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_]*)\}/',
            static fn (array $match): string => array_key_exists($match[1], $data)
                && is_scalar($data[$match[1]])
                    ? e($data[$match[1]])
                    : $match[0],
            $html
        ) ?? $html;
    }
}

if (!function_exists('load_view')) {
    function load_view(string $name, array $data = []): void
    {
        view($name, $data);
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim(getenv('APP_URL') ?: '', '/');
        $path = Request::removeConfiguredUrlExtension(ltrim($path, '/'));
        return $base . '/' . $path;
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
            Security::startSession();
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
        return Security::csrfToken();
    }
}

if (!function_exists('safe_form')) {
    function safe_form(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return safe_form();
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(?string $token = null): bool
    {
        return Security::verifyToken($token);
    }
}

if (!function_exists('session_regenerate')) {
    function session_regenerate(): void
    {
        Security::regenerateSession();
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed ...$values): mixed
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            Security::startSession();
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
