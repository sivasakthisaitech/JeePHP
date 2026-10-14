# JeePHP 1.0

JeePHP is a small, Laravel-inspired PHP framework. It keeps a familiar application structure while using short, readable PHP for everyday database and page work.

## Requirements

- PHP 8.1 or later
- Composer
- MySQL or MariaDB
- PHP extensions: PDO MySQL and mbstring

## Create a project with Composer

Once JeePHP 1.0 is published on Packagist under `jeep/php-framework`, create an app from any folder with:

```bash
composer create-project jeep/php-framework my-project-name
cd my-project-name
```

Composer downloads the published JeePHP project into `my-project-name` and installs it. The `.env` file is copied from `.env.example` after creation. Then set `DB_NAME`, `DB_USER`, and `DB_PASS`, create the database in MySQL/MariaDB, and start the app.

The universal `composer create-project` command works only after the JeePHP repository is public and registered on Packagist. The local `php jeep create MyApp` command below works from the JeePHP source folder.

## Install and run a downloaded project

1. Open a terminal in this folder and run `composer install`.
2. Copy `.env.example` to `.env` and set `DB_NAME`, `DB_USER`, and `DB_PASS`.
3. Create that database in MySQL or MariaDB.
4. Run `php -S 127.0.0.1:8000 -t public`.
5. Open `http://127.0.0.1:8000`.

For Apache, point the document root to `public/` and enable `mod_rewrite`.

## CLI and migrations

JeePHP includes a small CLI for common setup work. Run these from the project root:

```bash
php jeep create MyApp
cd MyApp
composer install
```

`create` makes a fresh project folder in the current directory, names the app, and stops if that folder already exists. `php jeep new MyApp` is an alias. After setting `.env`, run its starter migration. Then use the generated project's CLI for the usual commands:

```bash
php jeep migrate
php jeep make:model Product
php jeep make:controller Product
php jeep make:migration create_products_table
php jeep migrate:rollback
```

On Windows, use `jeep.bat` in place of `php jeep`. Migration files return a `JeePHP\Database\Migration` object with `up(PDO $database)` and `down(PDO $database)` methods. The starter migration creates the `customers` table. Review a migration before running it; rollback executes its `down()` method.

## Project folders

```text
app/
  Controllers/    Request handling
  Helpers/        Your application's reusable functions
  Models/         Your database models
  Views/          HTML pages
  routes.php      URL-to-controller map
bootstrap/        Framework startup and .env loading
config/           Database configuration
database/
  migrations/     Starter and application schema changes
bin/jeep          Command line tools
public/           Web root and front controller
src/JeePHP/
  Database/       PDO connection, query builder, base Model
  Http/           Request object
  Routing/        Router
  Support/        Built-in helper functions
```

## Database helpers

The database connects automatically from `.env` when the first helper is called.

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

Then use it from a controller:

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

`$fillable` protects model create/update calls from accepting fields you did not list. Use `Customer::query()` when you need the simple query builder, for example `Customer::query()->where('status', 1)->get()`.

## Built-in helpers

- `e($value)` safely escapes output in HTML.
- `view('customers/list', $data)` loads a view from `app/Views/`.
- `url('customers')` and `asset('app.css')` build URLs from `APP_URL`.
- `redirect('customers')` redirects to a JeePHP URL.
- `session('user_id')` reads a session value; `session('user_id', 5)` sets it.
- `flash('message', 'Saved')` stores a one-time message; `flash('message')` reads it and clears it.
- `csrf_field()` prints a form token; `csrf_verify()` checks a submitted token.
- `validate($input, ['name' => 'required|min:2', 'email' => 'email'])` returns an errors array. Supported rules: `required`, `email`, `min:n`, and `max:n`.
- `old('name')` gets a value from `_old_input` in the session.
- Add your own functions in `app/Helpers/helpers.php`; Composer loads that file automatically.

The starter landing page at `/` welcomes visitors to **JeePHP Version 1.0** with a monochrome terminal look and animated `0`/`1` background. It also links into a short model example.

## Routes, controllers, and views

Add a route in `app/routes.php`:

```php
$router->get('/customers', [CustomerController::class, 'index']);
$router->post('/customers', [CustomerController::class, 'store']);
```

Controllers live in `app/Controllers/`. Views live in `app/Views/`; escape displayed values with `e($value)`. Request data is available through `$request->input('name')`, `$request->all()`, or `$request->only('name', 'email')`.

JeePHP 1.0 is the framework foundation: routes, request handling, database query helpers, models, views, and common helpers are included. More features such as migrations, authentication, and a CLI generator can be added as later modules.
