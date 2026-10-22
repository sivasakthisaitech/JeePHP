// JeePHP package: AuthO
if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        $id = session('auth_user_id');
        return is_numeric($id)
            ? table_connect('auth_users')->columns('id', 'name', 'email')->where('id', (int) $id)->first()
            : null;
    }
}

if (!function_exists('auth_check')) {
    function auth_check(): bool
    {
        return auth_user() !== null;
    }
}

if (!function_exists('auth_logout')) {
    function auth_logout(): void
    {
        \JeePHP\Security\Security::regenerateSession();
        session('auth_user_id', null);
    }
}

if (!function_exists('auth_owns')) {
    /** Pass an explicit user ID only for a separately authenticated API identity. */
    function auth_owns(?array $record, string $ownerColumn = 'user_id', ?int $userId = null): bool
    {
        if ($userId === null) {
            $user = auth_user();
            $userId = is_array($user) ? (int) $user['id'] : 0;
        }
        return \JeePHP\Security\Authorization::owns($record, $userId, $ownerColumn);
    }
}

if (!function_exists('auth_require_owner')) {
    /** Call on every request that reads or changes a user-owned record. */
    function auth_require_owner(?array $record, string $ownerColumn = 'user_id', ?int $userId = null): void
    {
        if (auth_owns($record, $ownerColumn, $userId)) {
            return;
        }
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not found';
        exit;
    }
}
