# JeePHP 1.0.0

JeePHP is a small, Laravel-inspired PHP framework. It keeps a familiar application structure while using short, readable PHP for everyday database and page work.

The full beginner reference is available as [DOCX](docs/JeePHP-Manual-Book-v1.0.0.docx) and [PDF](docs/JeePHP-Manual-Book-v1.0.0.pdf). JeePHP includes an Artisan-style CLI entry point: use `php artisan` for the command list and run every JeePHP command through it. The older `php jeep` entry point remains available.

## Requirements

- Patched PHP 8.2.35+, 8.3.36+, 8.4.27+, or 8.5.12+ (on its respective branch); see [security release checklist](SECURITY-RELEASE.md)
- Composer
- MySQL or MariaDB only when you use database features
- PHP extensions: PDO MySQL and mbstring

## Create a project with Composer

Create a project from any folder with:

```bash
composer create-project sivasakthi/jeephp my-project-name
cd my-project-name
```

Composer downloads the published JeePHP project into `my-project-name` and installs it. The `.duck` file is copied from `.duck.example` after creation. Run `php artisan serve` to start it locally. Set `DB_NAME`, `DB_USER`, and `DB_PASS` in `.duck` and create a MySQL/MariaDB database only if your app uses database features.

The universal `composer create-project` command works only after the JeePHP repository is public and registered on Packagist. The local `php artisan create MyApp` command below works from the JeePHP source folder.

## Run locally without XAMPP

You need PHP CLI installed; XAMPP is optional. From the project folder, run:

```bash
composer install
php artisan serve
```

Open `http://127.0.0.1:8000`. Choose a different port with `php artisan serve 8080`, or bind another host with `php artisan serve 0.0.0.0:8080`.

On Windows, `artisan.bat serve` runs the same command when PHP is on `PATH`. If PHP is not on `PATH`, add your standalone PHP folder to `PATH`, or run `C:\path\to\php.exe artisan serve`. XAMPP users can run `C:\xampp\php\php.exe artisan serve` if they prefer XAMPP's PHP runtime. `jeep.bat` and `php jeep` remain compatible aliases.

This command uses PHP's built-in development web server. It is for local development, not production hosting. PHP is still required; JeePHP does not compile PHP into a standalone executable.

To keep using XAMPP Apache, place the project in `htdocs/MyApp` and open `http://localhost/MyApp/`. The root `index.php` fallback and project-root `.htaccess` send `/` to the welcome route. Use clean app URLs such as `/MyApp/contact`; never open `/MyApp/app/Views/` or a view file directly. If Apache shows a folder listing or the `app/Views` path, it is ignoring `.htaccess`: in `C:\xampp\apache\conf\httpd.conf`, enable `LoadModule rewrite_module modules/mod_rewrite.so` and set the `C:/xampp/htdocs` `<Directory>` block to `AllowOverride All`; restart Apache. The root `.htaccess` then blocks direct requests to framework source folders. For production, point the web-server document root directly to `public/` so framework source files stay outside the public web root.

## Artisan-style CLI and migrations

JeePHP includes a small CLI for common setup work. Run these from the project root:

```bash
php artisan create MyApp
cd MyApp
composer install
php artisan serve
```

`create` makes a fresh project folder in the current directory, creates its `.duck` config file, names the app, and stops if that folder already exists. `php artisan new MyApp` is an alias. After setting database details in `.duck`, run its starter migration. Then use the generated project's CLI for the usual commands:

```bash
php artisan migrate
php artisan make:model Product
php artisan make:controller Product
php artisan migrate:create products
php artisan migrate:rollback
php artisan make:job DailyReport
php artisan job:run DailyReport
```

`php artisan`, `php artisan list`, and `php artisan --help` show the available commands. On Windows, use `artisan.bat` in place of `php artisan`. To generate a table migration, pass only the table name:

```bash
php artisan migrate:create users
```

This creates a timestamped `create_users_table.php` file automatically. `php artisan make:migration users` and `php artisan create-migration users` are aliases. `composer create migrate users` is not a Composer command; Composer creates the project, and `php artisan` runs JeePHP commands. The old `php jeep` alias also works.

In the generated migration, define the table with a short builder. An `id` primary key is added automatically; `column()` creates nullable `VARCHAR(255)` fields:

```php
create_table('users')
    ->column('name', 'email', 'phone')
    ->integer('age')
    ->timestamps()
    ->create();
```

Available helpers include `stringColumn('name', 150, false)`, `integer('age')`, `text('bio')`, `decimal('price', 10, 2)`, `boolean('active', 1)`, and `timestamps()`. Remove a table in `down()` with `drop_table('users');`. Table and column identifiers are validated before SQL is executed. Review a migration before running it; rollback executes its `down()` method.

## Project folders

```text
app/
  Controllers/    Request handling
  BusinessLogic/  Reusable workflows and business rules
  Helpers/        Your application's reusable functions
  Jobs/           Background and scheduled task classes
  Mail/           Email classes and templates
  Models/         Your database models
  Providers/      Application startup registrations
  Views/          HTML pages
routes/
  web.php         Browser routes
  api.php         API routes
bootstrap/        Framework startup and .duck loading
config/           App, database, and mail settings
database/
  migrations/     Starter and application schema changes
  seeders/        Development or initial data scripts
index.php          XAMPP fallback entry point
artisan           Artisan-style CLI entry point
artisan.bat       Windows command launcher
bin/jeep          Shared CLI implementation
public/           Web root and front controller
src/JeePHP/
  Database/       PDO connection, query builder, models and eager loading
  Foundation/     Service provider base class
  Http/           Request object
  Jobs/           Job contract
  Routing/        Router
  Security/       Session, CSRF, and response-header protections
  Support/        Built-in helper functions
```

## Database helpers

The database connects automatically from `.duck` when the first helper is called.

```php
// Fetch every row from a table.
$customers = table_fetch('customers');

// Pick columns, filter, sort, and fetch.
$customers = table_load('customers')
    ->columns('id', 'name', 'mobile')
    ->where('status', 1)
    ->orderBy('name')
    ->limit(20)
    ->get();

// One row or a count.
$customer = table_load('customers')->where('id', 5)->first();
$count = table_load('customers')->where('status', 1)->count();

// Insert, update, delete.
$id = table_connect('customers')->insert(['name' => 'Kavin', 'status' => 1]);
table_connect('customers')->where('id', $id)->update(['name' => 'Kavin Raj']);
table_connect('customers')->where('id', $id)->delete();
```

`where()` values use PDO prepared statements. Table and column names are checked. `update()` and `delete()` require `where()` so a missing condition cannot change every row.

## Load table data into a view

For a page backed by one table, fetch it and load the matching view in one controller line:

```php
public function index(): void
{
    fetch_table('about_us')->where('id', 1)->load_table('about');
}
```

Use `where()` only when you want to filter the result. This loads just the row whose `id` is `1`:

```php
fetch_table('users')->where('id', 1)->load_table('users');
```

To load multiple users, remove `where()`:

```php
fetch_table('users')->load_table('users');
```

You can still filter a list when needed, for example `fetch_table('users')->where('status', 'Active')->load_table('users');`.

In `app/Views/about.php`, use a column name in braces. JeePHP HTML-escapes the value automatically:

```html
<h1>{heading}</h1>
<p>{description}</p>
```

For multiple records, use a small repeat block in `app/Views/users.php`. Its name matches the database table; JeePHP repeats the HTML once for every returned row:

```html
<ul>
    {#users}
        <li>{name} — {email}</li>
    {/users}
</ul>
```

The controller can add query conditions and sorting before loading the view:

```php
fetch_table('users')
    ->where('status', 'Active')
    ->orderBy('id', 'DESC')
    ->limit(100)
    ->load_table('users');
```

`fetch_table()` returns the same safe query builder as `table_load()`. `load_table('view-name')` at the end fetches the rows and prints that view, with a default 500-row limit; call `limit()` to choose a smaller result size. The existing global `load_table('table-name')` query-builder helper remains available for DataTables and manual rendering.

## Simple DataTables AJAX

For server-side DataTables, JeePHP reads DataTables' `draw`, `start`, `length`, `search`, and `order` request values and returns the JSON keys DataTables expects. A complete controller action can be one line:

```php
public function load_data(): string
{
    return table_ajax('pooja_bookings', 'id', 'booking_no', 'customer_name', 'booking_date', 'status');
}
```

When you need a database filter, use the query builder and let `ajax()` capture the current request:

```php
<?php
namespace App\Controllers;

use JeePHP\Http\Request;

final class BookingController
{
    public function load_data(): string
    {
        $result = load_table('pooja_bookings')
            ->where_column('id', 'booking_no', 'customer_name', 'booking_date', 'status')
            ->ajax();

        return json_data($result);
    }
}
```

Register the endpoint as a normal route:

```php
route('/pooja-bookings/load-data', 'BookingController@load_data');
```

The usual DataTables JavaScript can call that route:

```javascript
if (!$('#bookingTable').length) return;

$('#bookingTable').DataTable({
    processing: true,
    serverSide: true,
    ajax: {
        url: '<?= e(url('pooja-bookings/load-data')) ?>',
        type: 'GET'
    },
    language: {
        search: '_INPUT_',
        searchPlaceholder: 'Search bookings...'
    },
    aLengthMenu: [[5, 10, 15, -1], [5, 10, 15, 'All']],
    iDisplayLength: 10,
    scrollX: true,
    order: [[0, 'desc']]
});
```

`where_column()` is a short alias for choosing which columns can be returned, searched, and sorted. Add database filters with `where()`, for example `->where('status', 'active')`, before calling `ajax()`. JeePHP uses only explicit columns declared in PHP, not names supplied by the browser. Search values use prepared statements. Each AJAX response is limited to 500 rows maximum. Older code passing `$request` to `ajax($request)` still works.

For a normal page render, fetch rows without DataTables' AJAX response format:

```php
$bookings = data(
    load_table('pooja_bookings')
        ->where_column('id', 'booking_no', 'customer_name', 'booking_date', 'status')
        ->orderBy('id', 'DESC')
        ->limit(100)
);

load_view('pooja-bookings/index', ['bookings' => $bookings]);
```

Use `load_table('table_name')` for the short loader name; `table_load('table_name')` continues to work as before.

## Models

Create a model in `app/Models/`. Set its table name and the fields that can be filled:

```php
<?php
namespace App\Models;

use JeePHP\Database\Model;

final class Customer extends Model
{
    protected static string $table = 'customers';
    protected array $fillable = ['name', 'mobile', 'email', 'status'];
}
```

Use `insert_data()` on a model to save a new row through the model's table and `$fillable` list:

```php
<?php
namespace App\Models;

use JeePHP\Database\Model;

final class User extends Model
{
    protected static string $table = 'users';
    protected array $fillable = ['name', 'age', 'phone', 'password'];
}
```

Create the `users` table first, with matching `name`, `age`, `phone`, and `password` columns.

For example, create it in MySQL with:

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    age TINYINT UNSIGNED NOT NULL,
    phone VARCHAR(30) NOT NULL,
    password VARCHAR(255) NOT NULL
);
```

Connect a POST route and add a form in the view. `safe_form()` supplies the token required by JeePHP's automatic CSRF protection:

```php
route('POST /users', 'UserController@insert');
```

```html
<form method="post" action="/users">
    <?= safe_form() ?>
    <input name="name" required>
    <input name="age" type="number" required>
    <input name="phone" required>
    <input name="password" type="password" minlength="8" required>
    <button type="submit">Save</button>
</form>
```

Then insert form values from the controller. `insert_data()` performs the insert and returns the saved model, so a separate `insert()` call is not needed:

```php
public function insert(Request $request): string
{
    $user = User::insert_data([
        'name' => $request->input('name'),
        'age' => $request->input('age'),
        'phone' => $request->input('phone'),
        'password' => hash_password((string) $request->input('password', '')),
    ]);

    return 'Saved user ' . e($user->id);
}
```

Call `hash_password()` without quotes around it. It uses PHP's `password_hash()`; use `verify_password($inputPassword, $savedHash)` to check a password at login. Model `toArray()` hides the `password` field by default. `create()` continues to work through the same model insert path.

Validate submitted values before saving, for example with `validate($request->only('name', 'age', 'phone', 'password'), ['name' => 'required|min:2', 'password' => 'required|min:8'])`. The form's `required` and `minlength` attributes help the browser, but server-side validation is still needed.

You can also use `Customer::find()`, `Customer::all()`, and the regular model methods:

```php
$customer = Customer::find(5);
$customers = Customer::all();
$newCustomer = Customer::create([
    'name' => 'Kavin',
    'mobile' => '9876543210',
    'status' => 1,
]);

$newCustomer->email = 'kavin@example.com';
$newCustomer->save();
$newCustomer->update(['name' => 'Kavin Raj']);
$newCustomer->delete();
```

`$fillable` protects model `insert_data()`, `create()`, and update calls from accepting fields you did not list. Primary keys are not mass-assigned by `fill()`. Use `Customer::query()` when you need the simple query builder, for example `Customer::query()->where('status', 1)->get()`.

### Prevent N+1 queries

Define a relationship on the model, then eager-load it with `with()`. JeePHP fetches the parent rows once and the related rows in a batched `WHERE IN` query, instead of running one related query for every row in a loop:

```php
// In Post.php
use JeePHP\Database\RelationDefinition;

public function author(): RelationDefinition
{
    return $this->belongsTo(User::class, 'user_id');
}

// Fetch posts and all their authors in two queries.
$posts = Post::with('author')->where('status', 1)->get();
foreach ($posts as $post) {
    echo e($post->author?->name);
}
```

For one-to-many, use `hasMany()` and load it the same way: `User::with('posts')->get()`. Reading a declared relationship without adding it to `with()` throws a helpful exception; this prevents accidental lazy-loading N+1 queries. Direct SQL/query-helper calls inside loops are still the developer's responsibility. When `.duck` has `APP_DEBUG=true`, JeePHP reports a possible N+1 warning if the same SELECT runs five or more times in one request. In local/development mode, the warning also appears in the Developer Console tab.

`with()` currently supports direct relationships (`belongsTo` and `hasMany`); nested paths such as `with('posts.author')` are not supported yet. Related rows are fetched in batches of up to 500 keys.

## Built-in helpers

- `load_table('customers')` starts a query builder; it is a short alias for `table_load()`.
- `table_ajax('customers', 'id', 'name')` returns DataTables JSON with a one-line controller action.
- `data($query)` fetches rows for a normal page; `json_data($result)` returns JSON for an AJAX route.
- `e($value)` safely escapes output in HTML.
- `hash_password($password)` securely hashes a password; `verify_password($plain, $hash)` checks it.
- `view('customers/list', $data)` loads a view from `app/Views/`.
- `load_view('customers/list', $data)` loads and immediately prints a view; controllers do not need `ob_start()` or a return statement.
- `url_remove('.php')` removes `.php` from generated links and redirects GET/HEAD requests to extensionless URLs.
- `url('customers')` and `asset('app.css')` use `APP_URL` when configured and detect a local project subfolder such as `/MyApp` when the default local URL does not match.
- `redirect('customers')` redirects to a JeePHP URL.
- `session('user_id')` reads a session value; `session('user_id', 5)` sets it.
- `flash('message', 'Saved')` stores a one-time message; `flash('message')` reads it and clears it.
- `safe_form()` returns the hidden security token field; `csrf_field()` remains as an alias.
- `csrf_token()` returns the token for JavaScript requests; `session_regenerate()` rotates the session and token after login.
- `validate($input, $rules)` returns field errors. Rules include `required`, `nullable`, `email`, `url`, `numeric`, `integer`, `boolean`, `phone`, `date`, `min:n`, `max:n`, `confirmed`, `same:field`, `in:a,b`, `regex:pattern`, `unique:table,column[,ignoreId]`, and `exists:table,column`.
- `old('name')` gets a value from `_old_input` in the session.
- Add your own functions in `app/Helpers/helpers.php`; Composer loads that file automatically.

The starter landing page at `/` welcomes visitors to **JeePHP Version 1.0** with a monochrome terminal look and animated `0`/`1` background. It also links into a short model example.

## Routes, controllers, and views

Add browser routes in `routes/web.php` and API routes in `routes/api.php`. When applying this update to an existing project, copy any custom routes from the old `app/routes.php` into `routes/web.php` before replacing that old file:

```php
url_remove('.php');

route('/', 'HomeController@index');
route('/contact', 'ContactController@index');
route('/user/:id', 'UserController@show');
route('POST /save', 'PostController@store');
```

Routes without a method use `GET`. Prefix a route with `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, or `HEAD` to choose another method. A `:name` segment captures one URL part; read it with `$request->route('id')`. Controllers live in `app/Controllers/`. Views live in `app/Views/`; escape displayed values with `e($value)`. Request data is available through `$request->input('name')`, `$request->all()`, or `$request->only('name', 'email')`.

Use `url_remove('.php')` once in `routes/web.php` to redirect GET/HEAD requests like `/contact.php` to `/contact`, normalize matching non-GET requests and route definitions, and remove the suffix from links built with `url('contact.php')`. The old `app/routes.php` file remains as a compatibility loader for `routes/web.php`.

To render a view from a controller, call `load_view()` directly. It prints the view response, so the controller method can return `void`:

```php
public function index(Request $request): void
{
    load_view('contact', ['title' => 'Contact']);
}
```

## Providers, business logic, and background jobs

`app/Providers/AppServiceProvider.php` is loaded from `config/app.php`. JeePHP calls every provider's `register()` method first, then every `boot()` method. Put reusable multi-step rules in `app/BusinessLogic/` and keep controllers focused on HTTP work.

Create a starter business-logic class with `php artisan make:logic RegisterCustomer`. Then call it from a controller with `new App\BusinessLogic\RegisterCustomer()` and run its `handle()` method. `config/app.php` loads service providers in two passes: all `register()` methods, followed by all `boot()` methods.

For one synchronous job, use:

```bash
php artisan make:job DailyReport
php artisan job:run DailyReport
```

For background queue processing, activate QueueO and apply its migration:

```bash
php artisan activate QueueO
composer dump-autoload
php artisan migrate
php artisan make:job SendReceipt
```

Queue jobs implement `JeePHP\Jobs\QueueableJob`. Dispatch with `queue_dispatch('SendReceipt', ['order_id' => 42])`, start a worker with `php artisan queue:work`, inspect exhausted jobs with `php artisan queue:failed`, and retry one with `php artisan queue:retry JOB_ID`. The worker reserves jobs, retries failures up to each job's limit, and records failed jobs. Run a worker under your hosting provider's process manager for production.

For recurring work, activate ScheduleO, edit `config/schedule.php`, then have Linux cron or Windows Task Scheduler run `php artisan schedule:run` once a minute. `every_seconds` accepts 60 seconds through one year. If QueueO is active, due tasks are queued; otherwise they run in the scheduler process. Make sure a production queue worker is running when scheduled jobs are queued.

## Built-in security

JeePHP checks every `POST`, `PUT`, `PATCH`, and `DELETE` browser request before it reaches a route. Add `<?= safe_form() ?>` inside every state-changing form. JavaScript browser requests can send `csrf_token()` in `X-CSRF-TOKEN`. The framework also uses secure session settings and response headers; `e()` escapes HTML output, and query helper values use PDO prepared statements.

When APIO is active, a valid `Authorization: Bearer ...` token can authenticate machine requests under `/api/` without a browser session token. Keep each API controller protected with `api_require('ability.name')`; the exemption does not grant authorization by itself. GET routes remain safe, and API endpoints should not change data with GET.

Before installing or deploying, use `php artisan security:check`. It checks the CLI PHP binary only; check the Apache/FPM PHP version separately. Composer and bootstrap now reject unpatched PHP branches and releases after the October 2026 PHP engine advisory. This startup guard cannot stop an engine exploit that runs before application code: upgrade the hosting runtime. The security release checklist also covers AuthO/APIO upgrades, production configuration, and release tests.

`<safe>` is not a PHP tag and cannot automatically protect arbitrary code. Security still depends on input validation, output escaping, route authorization, HTTPS, updated dependencies, and keeping secrets out of Git. The built-in protections are useful defaults, not a guarantee against every attack.

## Development Console

When `.duck` contains `APP_ENV=local` or `APP_ENV=development` and `APP_DEBUG=true`, JeePHP adds a **Console** tab at the bottom-right of HTML pages. It displays PHP exceptions, browser JavaScript errors, unhandled rejections, `console.error()` messages, and possible N+1 warnings. Exception details include a source file, line, trace, and a suggested check. Treat the suggestion as a debugging hint and verify the change. Set `APP_ENV=production` and `APP_DEBUG=false` before deployment.

## Optional packages

Use `php artisan package:list` to see packages, then activate only what the application uses. Activation installs scaffold files where applicable, appends routes/helpers, records a local activation marker, and never runs migrations automatically:

```bash
php artisan package:list
php artisan activate AuthO
php artisan activate RolesO
composer dump-autoload
php artisan migrate
```

Package dependencies: RolesO requires AuthO; UploadO requires StorageO; CrudO requires ValidateO. MailO adds `phpmailer/phpmailer`; BackupO requires PHP cURL, OpenSSL, and zlib. When a package changes `composer.json`, run `composer update` and enable the required PHP extensions. For packages without Composer dependencies, `composer dump-autoload` is enough. Review the CLI's printed next steps after activation.

| Package | Adds |
| --- | --- |
| AuthO | Registration, sign-in, account, logout, password reset, optional email verification |
| RolesO | Roles and permissions tables, management page, checks such as `user_can()` |
| MailO | SMTP mail through PHPMailer and `send_mail()` |
| QueueO | Database-backed queue, retries, failed jobs, `queue_dispatch()` |
| ScheduleO | `config/schedule.php` and `php artisan schedule:run` |
| TestO | Lightweight test runner and `php artisan make:test` |
| UploadO | MIME-checked upload helper with random filenames and size limit |
| CacheO | File cache helpers, including `cache_remember()` |
| APIO | Hashed, expiring API tokens with abilities and bearer authentication |
| FrogO | Local browser API tester at `/frogo`, with response details and error suggestions |
| CrudO | `php artisan make:crud` scaffold for model/controller/views/routes/migration |
| ValidateO | Detailed validation rule reference; validator itself is built in |
| LogO | JSON log files with secret-like context redaction |
| StorageO | Private-by-default local file storage helpers |
| BackupO | Local SQL backups, AES-GCM encryption, Google Drive upload/list/restore/retention CLI |
| LocaleO | English/Tamil example translations, `set_locale()` and `trans()` |

Generated package files belong to that app's source tree. If AuthO was activated **before this fix**, add `route('POST /auth/register', 'AuthController@register');` and `route('POST /auth/login', 'AuthController@login');` to the existing `routes/web.php`. New AuthO activations include both routes. If a package is already marked active, a later framework package change does not silently overwrite the app's copy; review and merge any package template update yourself.

## Database backup and Google Drive

Activate BackupO, then install the package requirements:

```bash
php artisan activate BackupO
composer update
```

Create a 256-bit encryption key and add the printed value to `.duck` as `BACKUP_ENCRYPTION_KEY=...`. Keep a separate copy outside this project; without the exact key an encrypted backup cannot be restored. Configure Google Cloud with the Drive API enabled and create an OAuth client of type **Desktop app**. Run:

```bash
php artisan drive:connect C:\path\to\oauth-client.json
```

Open the URL shown by the CLI, approve access, and wait for the local callback. JeePHP uses OAuth with PKCE and stores the client/token in the ignored `storage/private/` directory. It asks only for access to files JeePHP creates in its Drive folder.

Create a local encrypted backup and upload it to Drive:

```bash
php artisan backup:database --drive
php artisan backup:list --drive
php artisan drive:status
```

The CLI also supports local backup listing (`php artisan backup:list`), restore (`php artisan backup:restore BACKUP_FILE --force`), Drive restore (`php artisan backup:restore --drive FILE_ID --force`), retention cleanup (`php artisan backup:clean --keep=7`), Drive retention cleanup (`php artisan backup:clean --keep=7 --drive`), and disconnect (`php artisan drive:disconnect`). Restore requires typing `RESTORE`; Drive cleanup asks for `DELETE`. A backup is stored locally even after upload.

Backups contain MySQL/MariaDB base-table schemas, rows, and views. InnoDB tables use a consistent snapshot; nontransactional tables may change during the dump. Triggers, stored procedures/functions, events, user grants, and tables created after the backup are not included. Restore replaces tables and views present in the backup, so check `.duck` and `DB_NAME` before confirming. Local files are encrypted only when `BACKUP_ENCRYPTION_KEY` is set; without it, they are compressed SQL and must be protected as sensitive data. Google Drive uploads always require encryption.

## API tokens

After activating APIO and running `php artisan migrate`, create a scoped token:

```bash
php artisan api:token 1 mobile-app users.read,users.write
```

Send the printed token only in an HTTPS `Authorization: Bearer TOKEN` header. In an API controller, require the specific ability before accessing data:

```php
$apiUser = api_require('users.read');
if ($apiUser === null) return;
```

Store the token securely and revoke it with `php artisan api:revoke TOKEN_ID USER_ID`. Only its SHA-256 hash is stored in the database. Avoid wildcard `*` abilities unless the client truly needs every permission.

## Test APIs with FrogO

Activate FrogO to add a browser-based API workbench:

```bash
php artisan activate FrogO
composer dump-autoload
php artisan serve
```

Open `http://127.0.0.1:8000/frogo`. The request field starts at `/frogo/ping`, a local health-check endpoint you can send immediately. Then choose an HTTP method, enter your own route such as `/api/users`, add headers (including an APIO Bearer token when needed), and enter a JSON or raw request body. FrogO shows the status, duration, response size, body, and response headers. For common HTTP failures it explains the likely cause and what to check next. Request bodies and response previews are limited to 2 MB to keep the page responsive.

FrogO is deliberately limited to `APP_ENV=local` (or development) with `APP_DEBUG=true`, and only serves loopback connections from this computer. Requests run directly in the browser and are restricted to the same origin; PHP does not proxy arbitrary URLs. Request data and tokens are kept in page memory only and are not saved. Do not use FrogO on a production server.

## Package examples

```php
// MailO
send_mail('user@example.com', 'Receipt', 'emails/receipt', ['order' => $order]);

// CacheO
$product = cache_remember('product:42', 300, fn () => Product::find(42));

// LogO
app_log('info', 'Order paid', ['order_id' => 42, 'password' => 'will be redacted']);

// StorageO: private is the default disk
$path = store_file('invoices/42.txt', $contents);

// LocaleO
set_locale('ta');
$welcome = trans('welcome', ['app' => 'JeePHP']);
```

UploadO usage: `upload_file('photo', ['image/jpeg', 'image/png'], 2_000_000, 'avatars')`. It checks PHP upload status, size, and detected MIME type, then creates a random safe filename. Do not allow arbitrary HTML, SVG, or executable files in public storage.

TestO is a small no-dependency runner, not PHPUnit. Use `php artisan make:test Example` and `php artisan test`. CrudO can generate a starter module with `php artisan make:crud Product name,price,description`; review its controller, validation, routes, and migration before running `php artisan migrate`.

To check this source update on a patched PHP machine, run `php artisan security:check`, `composer validate`, `composer install`, `php artisan test`, and `php artisan serve`. Test AuthO registration/login, a generated CRUD create/edit form, and a JSON API request against a running MySQL database; this project's test runner does not replace those integration checks.

## Composer release note

The source `config/app.php` version remains `1.0.0` as requested. Composer installs published tags; pushing to Git `main` does not update projects made with `composer create-project`. The earlier `v1.0.0` and `v1.1.0` tags are already used, so publish this source update with a new immutable tag such as `v1.2.0` and update Packagist before expecting Composer to install these packages.

`config/mail.php` reads SMTP values from `.duck`. Activate MailO to install the PHPMailer transport and the `send_mail()` helper. After activation run `composer update`, set `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and sender fields in `.duck`, then test with `php artisan mail:test user@example.com`.

## Built-in security

JeePHP checks every `POST`, `PUT`, `PATCH`, and `DELETE` request in `public/index.php` before it reaches a route. A request without a valid session token gets a `419` response. `GET`, `HEAD`, and `OPTIONS` requests do not need a token; do not use them to change or delete data.

Put the helper inside each HTML form that sends a state-changing request:

```php
<form method="post" action="<?= e(url('save')) ?>">
    <?= safe_form() ?>
    <input name="name" required>
    <button type="submit">Save</button>
</form>
```

`<?safe>` is not a PHP tag and cannot be defined by a framework. `<?= safe_form() ?>` is valid PHP and gives you the short form markup you wanted. You can keep using `<?= csrf_field() ?>` in existing views.

For JavaScript requests, render the token once and send it in the `X-CSRF-TOKEN` header:

```php
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
```

```javascript
fetch('/save', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({ name: 'Kavin' })
});
```

The framework also uses HTTP security headers, strict session IDs, HttpOnly and SameSite cookies, and Secure cookies over HTTPS. After a successful login, call `session_regenerate()` to prevent session fixation. The built-in `e()` helper escapes HTML output, and database query values use prepared statements.

## Development Console

When `.duck` contains `APP_ENV=local` or `APP_ENV=development` and `APP_DEBUG=true`, JeePHP adds a small **Console** tab to HTML pages. Click it to see PHP exceptions, JavaScript errors, rejected promises, `console.error()` messages, and possible N+1 warnings. PHP exception details include the source file and line, stack trace, and a rule-based suggestion for what to check. Suggestions help debugging but do not replace reviewing the code and verifying the fix.

The detailed error screen and panel are disabled outside local/development debug mode. Before deployment, set `APP_ENV=production` and `APP_DEBUG=false`; stack traces can disclose application paths and implementation details.

Security is layered: a CSRF token blocks cross-site form submissions, but no single helper can stop every attack. Continue to validate input, escape values with `e()` when displaying them, use JeePHP's prepared query methods, check user permissions on protected routes, and keep PHP and server software updated.

JeePHP includes routes, request handling, database query helpers, models, views, common helpers, migrations, CLI generators, and built-in request security. Optional AuthO authentication and RolesO roles/permissions scaffolds can be activated from the CLI; see the section below.


## Optional security packages

Show the source version with `php artisan --version`. Activate built-in authentication and role/permission scaffolds from the project root:

```bash
php artisan activate AuthO
php artisan activate RolesO
composer dump-autoload
php artisan migrate
```

AuthO adds registration, login, account, and logout pages. Passwords must be 12–72 bytes and use `password_hash()`/`password_verify()`. Unsafe form requests use the framework CSRF check, sessions rotate after login, and signup/sign-in attempts are limited. RolesO adds roles, permissions, user-role and role-permission tables, helpers, and a management page protected by `roles.manage`.

AuthO now counts attempts in MySQL across sessions and PHP workers: 30 login requests per IP per 15 minutes, 5 per IP/email per 15 minutes, 10 registrations per IP per hour, and 5 reset requests per IP per hour. Requests beyond the limit return 429; if MySQL is unavailable, authentication fails instead of bypassing the limit. The old account-wide `locked_until` check was removed so a stranger cannot lock a victim out by submitting bad passwords. Behind a reverse proxy, `REMOTE_ADDR` is the proxy address: set rate limits at the trusted edge or configure a trusted client-IP layer for high-traffic deployments. Distributed attackers using many IPs still require edge protection and monitoring.

For a record with `user_id`, check its owner inside **every** read/update/delete controller action:

```php
$booking = table_connect('bookings')->where('id', $request->route('id'))->first();
auth_require_owner($booking); // Sends 404 and stops if missing or owned by another user.
```

For an existing app where AuthO is already active, back up the database and deploy in maintenance mode. Merge `packages/AuthO/files/app/Controllers/AuthController.php` into the existing `app/Controllers/AuthController.php`, and replace the appended AuthO block in `app/Helpers/helpers.php` with `packages/AuthO/files/helpers.php` (preserve the other package blocks). Then run `php artisan auth:upgrade` to create `auth_rate_limits`, `composer dump-autoload`, and check login/registration/reset and 429 behavior on the actual web server. Do the schema upgrade before allowing AuthO traffic. Fresh AuthO activations create the table in their normal migration.

After creating an AuthO account and applying both migrations, replace `1` with the account ID to enable the first roles manager:

```bash
php artisan role:assign 1 admin
php artisan permission:grant admin roles.manage
```

Use `user_can('posts.publish')` or `user_has_role($userId, 'editor')` inside server-side route/controller authorization checks. Hiding a UI control does not secure a route. These packages provide a useful baseline; review your app's authorization needs, deploy over HTTPS, and add email verification/recovery flows where needed.
