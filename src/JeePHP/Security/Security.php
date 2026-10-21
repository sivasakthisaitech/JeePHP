<?php
declare(strict_types=1);

namespace JeePHP\Security;

use JeePHP\Http\Request;

/** Framework-wide session, response-header, and CSRF protections. */
final class Security
{
    private const TOKEN_KEY = '_jeephp_csrf_token';
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public static function headers(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Cross-Origin-Resource-Policy: same-origin');

        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        session_set_cookie_params([
            'httponly' => true,
            'secure' => self::isHttps(),
            'samesite' => 'Lax',
            'path' => '/',
        ]);

        session_start();
    }

    public static function csrfToken(): string
    {
        self::startSession();
        $token = $_SESSION[self::TOKEN_KEY] ?? null;
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::TOKEN_KEY] = $token;
        }

        return $token;
    }

    public static function verifyCsrf(Request $request): bool
    {
        if (in_array($request->method, self::SAFE_METHODS, true)) {
            return true;
        }

        // Modern browsers identify cross-site form/fetch requests here. Keep
        // the token check below as the required protection and use this only
        // as an additional early rejection signal.
        if (strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')) === 'cross-site') {
            return false;
        }

        // A valid, scoped Bearer token is the API equivalent of a browser's
        // session-bound CSRF token. Keep this exception limited to /api URLs.
        if (self::hasValidApiBearer($request)) {
            return true;
        }

        self::startSession();
        $knownToken = $_SESSION[self::TOKEN_KEY] ?? null;
        $submitted = $request->input('_token');
        if (!is_string($submitted) || $submitted === '') {
            $submitted = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }

        return is_string($knownToken)
            && preg_match('/^[a-f0-9]{64}$/', $knownToken) === 1
            && is_string($submitted)
            && $submitted !== ''
            && hash_equals($knownToken, $submitted);
    }

    public static function verifyToken(?string $submitted = null): bool
    {
        self::startSession();
        $knownToken = $_SESSION[self::TOKEN_KEY] ?? null;
        $submitted ??= $_POST['_token'] ?? '';

        return is_string($knownToken)
            && preg_match('/^[a-f0-9]{64}$/', $knownToken) === 1
            && is_string($submitted)
            && $submitted !== ''
            && hash_equals($knownToken, $submitted);
    }

    /** Call after a successful login or other privilege change. */
    public static function regenerateSession(): void
    {
        self::startSession();
        session_regenerate_id(true);
        $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
    }

    private static function isHttps(): bool
    {
        return isset($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== ''
            && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    }

    private static function hasValidApiBearer(Request $request): bool
    {
        if (($request->path !== '/api' && !str_starts_with($request->path, '/api/'))
            || !is_file(dirname(__DIR__, 3) . '/storage/packages/APIO.active')) {
            return false;
        }
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!is_string($header) || preg_match('/^Bearer\s+(.+)$/i', trim($header), $match) !== 1) {
            return false;
        }

        $class = \JeePHP\Api\ApiTokens::class;
        return class_exists($class) && $class::authenticate(trim($match[1])) !== null;
    }
}
