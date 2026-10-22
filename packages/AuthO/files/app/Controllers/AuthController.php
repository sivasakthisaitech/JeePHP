<?php
declare(strict_types=1);

namespace App\Controllers;

use JeePHP\Http\Request;
use JeePHP\Security\AuthRateLimiter;
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
        if (!AuthRateLimiter::hit('register-ip', AuthRateLimiter::clientIp(), 10, 3600)) {
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
        $requireVerification = filter_var(getenv('AUTH_REQUIRE_EMAIL_VERIFICATION') ?: 'false', FILTER_VALIDATE_BOOL);
        if ($requireVerification && !function_exists('send_mail')) {
            http_response_code(503);
            load_view('auth/register', ['error' => 'Email verification is enabled. Activate MailO and configure SMTP first.']);
            return;
        }

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
        if ($requireVerification) {
            $token = bin2hex(random_bytes(32));
            table_connect('auth_email_verifications')->where('user_id', (int) $id)->delete();
            table_connect('auth_email_verifications')->insert([
                'user_id' => (int) $id,
                'token_hash' => hash('sha256', $token),
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
            ]);
            send_mail($email, 'Verify your email address', 'emails.verify-email', [
                'name' => $name,
                'verificationUrl' => url('auth/email/verify/' . $token),
            ]);
            load_view('auth/verification-sent');
            return;
        }
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

        $rawEmail = $request->input('email', '');
        $rawPassword = $request->input('password', '');
        $email = is_string($rawEmail) ? strtolower(trim($rawEmail)) : '';
        $password = is_string($rawPassword) ? $rawPassword : '';
        $ip = AuthRateLimiter::clientIp();
        $ipAllowed = AuthRateLimiter::hit('login-ip', $ip, 30, 900);
        $accountIpAllowed = $email === '' || AuthRateLimiter::hit('login-account-ip', $ip . "\0" . $email, 5, 900);
        if (!$ipAllowed || !$accountIpAllowed) {
            http_response_code(429);
            load_view('auth/login', ['error' => 'Too many sign-in attempts. Please wait 15 minutes and try again.']);
            return;
        }

        $user = filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            ? table_connect('auth_users')->where('email', $email)->first()
            : null;
        $passwordHash = is_array($user)
            ? (string) $user['password_hash']
            : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $passwordMatches = password_verify($password, $passwordHash);
        if (is_array($user) && $passwordMatches) {
            if (filter_var(getenv('AUTH_REQUIRE_EMAIL_VERIFICATION') ?: 'false', FILTER_VALIDATE_BOOL)
                && empty($user['email_verified_at'])) {
                http_response_code(403);
                load_view('auth/login', ['error' => 'Verify your email address before signing in.']);
                return;
            }
            Security::regenerateSession();
            session('auth_user_id', (int) $user['id']);
            redirect('auth/account');
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

    public function forgotPassword(Request $request): void
    {
        if ($request->method === 'GET') {
            load_view('auth/forgot-password');
            return;
        }
        $now = time();
        if (!AuthRateLimiter::hit('reset-ip', AuthRateLimiter::clientIp(), 5, 3600)) {
            http_response_code(429);
            load_view('auth/forgot-password', ['message' => 'If the account exists, a reset link will be sent.']);
            return;
        }
        $email = $request->input('email', '');
        $email = is_string($email) ? strtolower(trim($email)) : '';
        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false && function_exists('send_mail')) {
            $user = table_connect('auth_users')->where('email', $email)->first();
            if (is_array($user)) {
                $token = bin2hex(random_bytes(32));
                table_connect('auth_password_resets')->where('email', $email)->delete();
                table_connect('auth_password_resets')->insert([
                    'email' => $email,
                    'token_hash' => hash('sha256', $token),
                    'expires_at' => gmdate('Y-m-d H:i:s', $now + 3600),
                ]);
                try {
                    send_mail($email, 'Reset your password', 'emails.password-reset', [
                        'name' => $user['name'],
                        'resetUrl' => url('auth/password/reset/' . $token),
                    ]);
                } catch (\Throwable $error) {
                    if (function_exists('app_log')) {
                        app_log('error', 'Password reset email could not be sent.', ['email' => $email, 'error' => $error->getMessage()]);
                    }
                }
            }
        }
        load_view('auth/forgot-password', ['message' => 'If the account exists, a reset link will be sent.']);
    }

    public function resetPassword(Request $request): void
    {
        $token = $request->route('token', '');
        if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            http_response_code(404);
            echo 'Reset link not found or expired.';
            return;
        }
        $record = table_connect('auth_password_resets')->where('token_hash', hash('sha256', $token))->first();
        if (!is_array($record) || strtotime((string) $record['expires_at'] . ' UTC') <= time()) {
            http_response_code(410);
            echo 'Reset link not found or expired.';
            return;
        }
        if ($request->method === 'GET') {
            load_view('auth/reset-password', ['token' => $token]);
            return;
        }
        $password = $request->input('password', '');
        $confirmation = $request->input('password_confirmation', '');
        if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72 || !is_string($confirmation) || !hash_equals($password, $confirmation)) {
            http_response_code(422);
            load_view('auth/reset-password', ['token' => $token, 'error' => 'Use a 12–72 byte password and enter it the same way twice.']);
            return;
        }
        $consume = \JeePHP\Database\Database::connection()->prepare(
            'DELETE FROM auth_password_resets WHERE id = :id AND expires_at > UTC_TIMESTAMP()'
        );
        $consume->execute(['id' => $record['id']]);
        if ($consume->rowCount() !== 1) {
            http_response_code(410);
            echo 'Reset link not found or expired.';
            return;
        }
        table_connect('auth_users')->where('email', $record['email'])->update([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'failed_attempts' => 0,
            'locked_until' => null,
        ]);
        load_view('auth/login', ['message' => 'Password changed. You can sign in now.']);
    }

    public function verifyEmail(Request $request): void
    {
        $token = $request->route('token', '');
        if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            http_response_code(404);
            echo 'Verification link not found or expired.';
            return;
        }
        $record = table_connect('auth_email_verifications')->where('token_hash', hash('sha256', $token))->first();
        if (!is_array($record) || strtotime((string) $record['expires_at'] . ' UTC') <= time()) {
            http_response_code(410);
            echo 'Verification link not found or expired.';
            return;
        }
        $consume = \JeePHP\Database\Database::connection()->prepare(
            'DELETE FROM auth_email_verifications WHERE id = :id AND expires_at > UTC_TIMESTAMP()'
        );
        $consume->execute(['id' => $record['id']]);
        if ($consume->rowCount() !== 1) {
            http_response_code(410);
            echo 'Verification link not found or expired.';
            return;
        }
        table_connect('auth_users')->where('id', $record['user_id'])->update(['email_verified_at' => gmdate('Y-m-d H:i:s')]);
        load_view('auth/login', ['message' => 'Email verified. You can sign in now.']);
    }
}
