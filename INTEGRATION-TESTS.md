# JeePHP real integration tests

This suite exercises real HTTP requests and a real MySQL/MariaDB connection. It installs temporary routes and a generated CRUD module into **the project where you run it**, writes rows, rolls back a temporary migration, uploads a PNG, and restores an encrypted database backup. Use a fresh, disposable project and a dedicated test database. Do not run it against your working application or production database.

## Windows XAMPP setup

1. Use a patched PHP version accepted by `composer.json` for both **Command Prompt** and Apache. Run `php -v` and `php artisan security:check` in the test project. Check Apache's PHP version using `phpinfo()` or the health check below; the suite requires Apache and CLI to have the same PHP version. Enable `pdo_mysql`, `mbstring`, `fileinfo`, `curl`, `openssl`, and `zlib` in the PHP configuration.
2. Copy the full JeePHP release into `C:\xampp\htdocs\JeePHPIntegration`. Run `composer install` there. Start Apache and MySQL from XAMPP.
3. In phpMyAdmin, create an **empty** database named `jeephp_test_integration` with UTF-8 / `utf8mb4` collation. Use a test-only MySQL account if possible. No production data belongs here.
4. Copy `.duck.example` to `.duck` and set:

   ```ini
   APP_ENV=testing
   APP_DEBUG=false
   APP_URL=http://localhost/JeePHPIntegration
   AUTH_REQUIRE_EMAIL_VERIFICATION=false
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=jeephp_test_integration
   DB_USER=root
   DB_PASS=
   ```

5. From the same folder, run:

   ```bat
   php artisan test:integration --base-url=http://localhost/JeePHPIntegration
   ```

The runner refuses to start unless `APP_ENV=testing`, the database name begins with `jeephp_test_`, MySQL reports the same database name, the URL is loopback only, and `.duck` `APP_URL` matches the URL. It also checks an HTTP fingerprint of the served project **before any HTTP writes**. It exits `0` on all checks passing, `1` on failure, with `[PASS]`/`[FAIL]` for each group. Re-running after a fix is supported, but a new empty test database gives the cleanest result. The backup restore recreates the whole dedicated database's tables.

## Built-in local server option

For the PHP built-in server, use another disposable folder and the same test database rules. Set `APP_URL=http://127.0.0.1:8000`; run `php artisan serve` in one terminal, then `php artisan test:integration --base-url=http://127.0.0.1:8000` in a second terminal. Test Apache separately for XAMPP compatibility.

## What the command verifies

| Group | Real check |
| --- | --- |
| HTTP target | Local project fingerprint and Apache/CLI PHP version |
| Routes | Welcome, dynamic parameter, missing route, session CSRF rejection |
| Migrations | Repeat run has no pending work; new migration creates a table; rollback removes it |
| CRUD | Generated form, insert, list, show, edit, delete through HTTP and MySQL |
| AuthO | Register, account, logout, incorrect and correct login |
| RolesO | Denied before permission, granted after role link, role creation route |
| APIO | Status, missing bearer, wrong ability, valid JSON bearer, malformed JSON |
| UploadO | Uploaded PNG bytes saved under public/storage and wrong MIME rejected |
| BackupO | Encrypted SQL backup, change data, restore, verify original value |

Do not share `.duck`, API tokens, session cookies, or backup files when reporting failures. Send the `[FAIL]` line, PHP/Apache version, and relevant redacted server error log. The command deliberately does **not** upload backups to Google Drive; external OAuth and remote storage need separate, explicit testing.
