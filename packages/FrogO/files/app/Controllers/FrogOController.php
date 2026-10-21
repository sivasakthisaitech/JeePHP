<?php
declare(strict_types=1);

namespace App\Controllers;

use JeePHP\Http\Request;

/** Local-only browser API tester. It never proxies requests through PHP. */
final class FrogOController
{
    public function index(Request $request): void
    {
        if (!$this->isAllowedLocally()) {
            // Don't reveal that this developer tool exists outside local dev.
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo '404 - Page not found';
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex, nofollow');

        load_view('frogo/index', ['csrfToken' => csrf_token()]);
    }

    public function ping(Request $request): string
    {
        if (!$this->isAllowedLocally()) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            return '404 - Page not found';
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        return json_encode([
            'status' => 'ok',
            'framework' => 'JeePHP',
            'package' => 'FrogO',
            'message' => 'Your local API tester is connected.',
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function isAllowedLocally(): bool
    {
        $environment = strtolower(trim((string) (getenv('APP_ENV') ?: 'production')));
        $debug = filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL);
        $address = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $hasForwardedIdentity = false;
        foreach (['HTTP_FORWARDED', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $key) {
            if (trim((string) ($_SERVER[$key] ?? '')) !== '') {
                $hasForwardedIdentity = true;
                break;
            }
        }

        return in_array($environment, ['local', 'development', 'dev'], true)
            && $debug
            && !$hasForwardedIdentity
            && in_array($address, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true);
    }
}
