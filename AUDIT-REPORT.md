# JeePHP source audit — 22 October 2026

This review covers the bundled JeePHP 1.0.0 source snapshot. It does not certify every optional package or production deployment. The application syntax stays short while validation, request parsing, and query checks live in framework code.

## Fixed in this source bundle

| Finding | User-visible result |
| --- | --- |
| AuthO registered GET but not POST for register/login | New activations include both POST routes. Existing activated projects must add the two routes to their copied `routes/web.php`. |
| Generated CRUD form prefilled values were HTML-escaped twice | Generated form keeps raw scalar values until the `e()` output boundary. |
| Array-valued input could pass `required` and reach scalar checks | The built-in validator reports an error for array or object input in scalar fields. |
| DataTables required manual request passing | `return table_ajax('customers', 'id', 'name');` returns JSON in one line; `ajax()` now captures the request when omitted. |
| Request body ignored JSON payloads | JSON object bodies work with `$request->input()`; malformed or non-object bodies return HTTP 400. |
| Missing Apache rewrite allowed direct requests to private source files | Bundled source folders have independent Apache deny rules; root configuration files are denied by name when `.htaccess` is honored. |

DataTables still validates declared columns, limits the requested length to 500, and binds search terms. Existing `ajax($request)` calls remain valid. `safe_form()` and CSRF verification are unchanged.

## Still important before a production release

1. Update CLI and web-server PHP to the patched versions listed in `SECURITY-RELEASE.md`; the PHP engine vulnerability is reachable before JeePHP bootstrap. Run lint and the included tests, then real Apache/XAMPP and MySQL/MariaDB integration tests for migration/rollback, CRUD, AuthO login and password reset, DataTables search/sort, backup restore, queue, mail, and FrogO. PHP, Composer, MySQL, and Apache were unavailable in the audit container, so these flows are **not verified** here.
2. Point the production web root to `public/`. Private-folder `.htaccess` only works if Apache processes override files, and a repository served at the root may expose other files when those rules are disabled. Do not serve a `.git` directory from the public document root.
3. Generated CRUD routes are publicly reachable by default. Add authentication, record ownership checks, role/permission checks, and authorization tests for private data before deploying a generated module. `api_require()` must be called within API handlers that need a bearer ability; it now stops unauthorized requests, but older activated APIO helper copies require manual updating.
4. Eager loading via `Model::with('relation')->get()` reduces N+1 for direct declared relationships; nested relations, aggregates, and ad hoc raw queries are not automatically optimized. Use the developer query monitor and add indexes based on actual query plans.
5. Activated package files are copied into each app and are not overwritten by a later framework source update. Compare scaffold changes against each existing app. Composer `create-project` makes a standalone project snapshot; it does not upgrade that snapshot when JeePHP's Git repository changes. A split core Composer package plus versioned upgrade/migration tooling would make future releases safer.
6. MySQL DDL can commit independently, so a failed migration may leave partial schema changes. Back up data, inspect every migration, and test rollback on a disposable database before production.
7. AuthO now uses MySQL rate counters shared across sessions and PHP workers, without an account-wide lockout; controller routes can call `auth_require_owner()` for per-record authorization. Existing active apps must merge the controller/helper and upgrade their schema before reopening traffic. A distributed botnet, proxies that hide client IPs, and omitted ownership checks still need deployment-specific defenses and security review. The source bundle has not had an external penetration test.

## Small application examples

```php
route('POST /users', 'UserController@store');

public function index(): void { load_view('users', ['users' => table_fetch('users')]); }

public function load_data(): string { return table_ajax('users', 'id', 'name', 'email'); }
```

Use `<?= safe_form() ?>` in the POST form and `e($value)` for printed user data. Keep model fillable fields and server-side validation explicit; reducing controller lines must not remove these checks.

For comparison, Laravel documents eager loading with `with()` and session-backed CSRF tokens; CodeIgniter documents verb-specific routes and validation. JeePHP follows those practical boundaries with shorter helper calls, without claiming their full ecosystem or security coverage.

- [Laravel Eloquent relationships](https://laravel.com/framework/docs/eloquent-relationships)
- [Laravel CSRF protection](https://laravel.com/framework/docs/csrf)
- [CodeIgniter routing](https://www.codeigniter.com/user_guide/incoming/routing.html)
- [CodeIgniter validation](https://www.codeigniter.com/user_guide/libraries/validation.html)
