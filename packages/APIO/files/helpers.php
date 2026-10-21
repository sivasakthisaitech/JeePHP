// JeePHP package: APIO
if (!function_exists('api_token_create')) {
    function api_token_create(int $userId, string $name, array $abilities = ['*'], ?string $expiresAt = null): string
    {
        return \JeePHP\Api\ApiTokens::create($userId, $name, $abilities, $expiresAt);
    }
}
if (!function_exists('api_user')) {
    function api_user(): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!is_string($header) || preg_match('/^Bearer\s+(.+)$/i', trim($header), $match) !== 1) {
            return null;
        }
        return \JeePHP\Api\ApiTokens::authenticate(trim($match[1]));
    }
}
if (!function_exists('api_can')) {
    function api_can(string $ability): bool
    {
        $user = api_user();
        return is_array($user) && (in_array('*', $user['abilities'] ?? [], true) || in_array($ability, $user['abilities'] ?? [], true));
    }
}
if (!function_exists('api_require')) {
    function api_require(?string $ability = null): ?array
    {
        $user = api_user();
        if ($user === null || ($ability !== null && !in_array('*', $user['abilities'] ?? [], true) && !in_array($ability, $user['abilities'] ?? [], true))) {
            http_response_code($user === null ? 401 : 403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $user === null ? 'unauthenticated' : 'forbidden'], JSON_INVALID_UTF8_SUBSTITUTE);
            return null;
        }
        return $user;
    }
}
