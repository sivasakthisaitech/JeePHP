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
