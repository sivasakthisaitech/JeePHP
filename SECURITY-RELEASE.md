# JeePHP security release checklist — 22 October 2026

JeePHP 1.0.0 is a source version label, not a security certification. Do not call a deployment secure merely because the framework boots or `security:check` passes.

## Patched PHP is mandatory

The October 2026 PHP engine vulnerabilities (CVE-2026-109618 and CVE-2026-109619) can allow unauthenticated code execution before JeePHP starts. Install **PHP 8.2.35+, 8.3.36+, 8.4.27+, or 8.5.12+** on its respective branch. Prefer a current supported branch; PHP 8.1 is end of life and PHP 8.2 reaches end of life on 31 December 2026. Do not deploy PHP 8.6 prereleases. JeePHP Composer requirements reject older branches and patch releases, and bootstrap refuses to run them. **This refusal cannot block an exploit that occurs before bootstrap; upgrading the web server's PHP engine is the actual fix.** Follow PHP's advisory and vendor updates for later patches.

Official references: https://www.php.net/ and https://www.php.net/supported-versions.php

```bash
php -v
php artisan security:check
composer validate
composer install
php artisan test
```

`security:check` checks the CLI executable only. Verify Apache/FPM's actual PHP version with your hosting panel or a temporary server-side `phpversion()` diagnostic, then delete the diagnostic. On Windows/XAMPP, Composer, CLI, and Apache can each point to different PHP installs. Upgrade all of them. Do not use `--ignore-platform-reqs` or fake Composer's `platform.php` to bypass this gate.

## Production deployment

1. Make a tested backup, update PHP and JeePHP on staging, and verify extensions `pdo`, `pdo_mysql`, `mbstring`, and `fileinfo` (if using uploads). Run `composer audit` and review the result. Re-run when dependencies change.
2. Point the web server's DocumentRoot to `public/`; enable HTTPS and test redirects. Never expose `.duck`, `vendor/`, `app/`, `config/`, `storage/`, database backups, or source files. Root `.htaccess` is a fallback for local XAMPP, not the preferred production boundary.
3. Set `.duck` to `APP_ENV=production` and `APP_DEBUG=false`; configure HTTPS `APP_URL`. Keep `.duck`, OAuth credentials, backup encryption keys, and dumps out of Git and outside public document paths; limit filesystem access to the service account.
4. In each browser form changing data, use `<?= safe_form() ?>`. Use `csrf_token()` in the `X-CSRF-TOKEN` header for browser JavaScript. Keep writes off GET routes. Use `e()` for dynamic HTML and prepared queries for SQL values.
5. On API routes, explicitly call `api_require('specific.ability')` before reading or changing protected data. Failed authentication/ability now terminates with JSON 401/403. Authenticate the real owner of each record as well; a permission such as `users.read` does not automatically grant access to every user.
6. Generated CrudO routes and views are starter code. They are public until you add authentication, roles, record ownership checks, and input rules appropriate for your app. Uploaded files in public storage are readable by anyone with their URL; use private storage for sensitive documents.
7. AuthO now uses MySQL counters shared across sessions and PHP workers. The old account-wide lockout is no longer enforced, and `auth_require_owner($record)` is available for per-record checks. Existing active apps must merge the AuthO controller/helper and run `php artisan auth:upgrade` before reopening traffic. Rate limiting by `REMOTE_ADDR` does not identify users separately behind a proxy and cannot defeat a distributed botnet: add trusted-edge limits, monitoring, and an incident response path for public traffic. Test sessions especially behind a proxy and after a PHP upgrade. Verify `Secure`, `HttpOnly`, `SameSite`, cookie path, login rotation, logout, and CSRF failure. Set `APP_URL=https://...` to preserve secure cookie flags when TLS ends at a trusted proxy.
8. Run `php artisan test` plus an Apache/FPM and MySQL integration pass: welcome and routing, AuthO login/register, RolesO permissions, a generated CRUD create/edit, CSRF rejection, valid/invalid API bearer tokens, upload restrictions, backup encryption/restore, and failed error responses. Review access logs and errors without logging credentials or tokens.

## Existing projects and release tags

Copy the framework changes into existing projects after backing up and reviewing diffs. Package activation does not overwrite already installed helpers or route files: if APIO is active, replace or merge its `app/Helpers` API helper with `packages/APIO/files/helpers.php` so `api_require` stops on failure. If AuthO is active, merge its new controller and appended helper block, run `php artisan auth:upgrade` while traffic is paused, and verify both `POST /auth/register` and `POST /auth/login` routes exist. The updated framework session and token classes apply after source replacement. Clear old session cookies and have users sign in again after the session cookie name/path change.

The source still displays version 1.0.0 by project preference. Git tags `v1.0.0` and `v1.1.0` already exist: never move those tags. After testing on a patched PHP runtime and the target web stack, publish a **new immutable tag** (for example `v1.2.0`) and refresh Packagist. `composer create-project` installs published tags, not the latest untagged `main` source.

## Release status

Source review and static checks alone are **not a production security audit**. The release is blocked until Composer validation, PHP lint/tests, staging integration tests on the actual server stack, a dependency audit, and a focused external security review pass. Recheck PHP patch requirements when a new supported branch or security advisory appears.
