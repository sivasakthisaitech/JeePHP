<?php
declare(strict_types=1);

namespace App\Controllers;

use JeePHP\Http\Request;

/** Installed only inside a dedicated JeePHP integration test project. */
final class IntegrationProbeController
{
    private function guard(): void
    {
        if (getenv('APP_ENV') !== 'testing'
            || preg_match('/^jeephp_test_[a-z0-9_]+$/', (string) getenv('DB_NAME')) !== 1) {
            http_response_code(404);
            exit('Not found');
        }
    }

    public function health(): string
    {
        $this->guard();
        return json_data([
            'project' => hash('sha256', (string) realpath(dirname(__DIR__, 2))),
            'php' => PHP_VERSION,
        ]);
    }

    public function token(): string
    {
        $this->guard();
        return json_data(['token' => csrf_token()]);
    }

    public function dynamic(Request $request): string
    {
        $this->guard();
        return json_data(['id' => $request->route('id')]);
    }

    public function protected(): string
    {
        $this->guard();
        if (!auth_check() || !user_can('integration.view')) {
            http_response_code(403);
            return json_data(['error' => 'forbidden']);
        }
        return json_data(['status' => 'allowed']);
    }

    public function api(Request $request): string
    {
        $this->guard();
        $user = api_require('integration.read');
        return json_data(['user_id' => (int) $user['user_id'], 'name' => $request->input('name')]);
    }

    public function upload(): string
    {
        $this->guard();
        try {
            return json_data(['path' => upload_file('photo', ['image/png'], 100000, 'integration')]);
        } catch (\RuntimeException $error) {
            http_response_code(422);
            return json_data(['error' => $error->getMessage()]);
        }
    }
}
