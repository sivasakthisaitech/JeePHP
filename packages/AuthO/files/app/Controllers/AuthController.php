<?php
declare(strict_types=1);

namespace App\Controllers;

use JeePHP\Http\Request;
use JeePHP\Security\Security;

final class AuthController
{
    public function register(Request $request): void
    {
        if ($request->method === 'GET') {
            load_view('auth/register');
            return;
        }

        $rawName = $request->input('name', '');
        $rawEmail = $request->input('email', '');
        $rawPassword = $request->input('password', '');
        $name = is_string($rawName) ? trim($rawName) : '';
        $email = is_string($rawEmail) ? strtolower(trim($rawEmail)) : '';
        $password = is_string($rawPassword) ? $rawPassword : '';
        $registerLimit = session('auth_register_limit');
        $now = time();
        if (!is_array($registerLimit) || ($registerLimit['until'] ?? 0) <= $now) {
            $registerLimit = ['count' => 0, 'until' => $now + 3600];
        }
        if (($registerLimit['count'] ?? 0) >= 3) {
            http_response_code(429);
            load_view('auth/register', ['error' => 'Too many account creation attempts. Please try again in one hour.']);
            return;
        }
        if ($name === '' || mb_strlen($name) > 150 || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || mb_strlen($email) > 190 || strlen($password) < 12 || strlen($password) > 72) {
            http_response_code(422);
            load_view('auth/register', ['error' => 'Enter a valid name and email, and a password with 12–72 bytes.']);
            return;
        }

        $registerLimit['count']++;
        session('auth_register_limit', $registerLimit);

        if (table_connect('auth_users')->where('email', $email)->first() !== null) {
            http_response_code(422);
            load_view('auth/register', ['error' => 'Could not create that account. Check the details or try signing in.']);
            return;
        }

        $id = table_connect('auth_users')->insert([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'failed_attempts' => 0,
            'locked_until' => null,
        ]);
        Security::regenerateSession();
        session('auth_user_id', (int) $id);
        redirect('auth/account');
    }

    public function login(Request $request): void
    {
        if ($request->method === 'GET') {
            load_view('auth/login');
            return;
        }

        $limit = session('auth_login_limit');
        $now = time();
        if (!is_array($limit) || ($limit['until'] ?? 0) <= $now) {
            $limit = ['count' => 0, 'until' => $now + 900];
        }
        if (($limit['count'] ?? 0) >= 5) {
            http_response_code(429);
            load_view('auth/login', ['error' => 'Too many sign-in attempts. Please wait 15 minutes and try again.']);
            return;
        }

        $rawEmail = $request->input('email', '');
        $rawPassword = $request->input('password', '');
        $email = is_string($rawEmail) ? strtolower(trim($rawEmail)) : '';
        $password = is_string($rawPassword) ? $rawPassword : '';
        $user = filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            ? table_connect('auth_users')->where('email', $email)->first()
            : null;
        $lockedUntil = is_array($user) ? strtotime((string) ($user['locked_until'] ?? '')) : false;
        $locked = $lockedUntil !== false && $lockedUntil > $now;
        $passwordHash = is_array($user)
            ? (string) $user['password_hash']
            : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $passwordMatches = password_verify($password, $passwordHash);
        if (!$locked && is_array($user) && $passwordMatches) {
            table_connect('auth_users')->where('id', $user['id'])->update(['failed_attempts' => 0, 'locked_until' => null]);
            Security::regenerateSession();
            session('auth_user_id', (int) $user['id']);
            session('auth_login_limit', null);
            redirect('auth/account');
        }

        $limit['count']++;
        session('auth_login_limit', $limit);
        if (is_array($user) && !$locked) {
            $failures = (int) ($user['failed_attempts'] ?? 0) + 1;
            table_connect('auth_users')->where('id', $user['id'])->update([
                'failed_attempts' => $failures,
                'locked_until' => $failures >= 5 ? date('Y-m-d H:i:s', $now + 900) : null,
            ]);
        }
        http_response_code(401);
        load_view('auth/login', ['error' => 'Email or password is incorrect.']);
    }

    public function account(): void
    {
        $user = auth_user();
        if ($user === null) {
            redirect('auth/login');
        }
        load_view('auth/account', ['name' => $user['name']]);
    }

    public function logout(Request $request): void
    {
        Security::regenerateSession();
        session('auth_user_id', null);
        redirect('auth/login');
    }
}
