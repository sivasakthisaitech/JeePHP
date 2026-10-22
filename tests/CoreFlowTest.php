<?php
declare(strict_types=1);

use JeePHP\Http\Request;
use JeePHP\Packages\CrudGenerator;
use JeePHP\Routing\Router;
use JeePHP\Testing\TestCase;

return static function (TestCase $test): void {
    $server = $_SERVER;
    $get = $_GET;
    $post = $_POST;
    try {
        $_SERVER['SCRIPT_NAME'] = '/MyApp/public/index.php';
        $_SERVER['REQUEST_URI'] = '/MyApp/user/42?tab=profile';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['tab' => 'profile'];
        $_POST = [];
        $request = Request::capture();
        $test->same('/user/42', $request->path, 'XAMPP subfolder must not become part of the route.');
        $test->same('/MyApp', Request::basePath());
        $test->same('profile', $request->input('tab'));

        $router = new Router();
        $router->register('/user/:id', 'App\\Controllers\\UserController@show');
        $router->get('/echo/:id', static fn (Request $request): string => e($request->route('id')));
        $_SERVER['REQUEST_URI'] = '/MyApp/echo/%3Cscript%3E';
        ob_start();
        $router->dispatch(Request::capture());
        $test->same('&lt;script&gt;', ob_get_clean(), 'Dynamic route values must be escaped in HTML.');

        $test->same('Hi &lt;b&gt;Siva&lt;/b&gt;', render_view_data('Hi {name}', ['name' => '<b>Siva</b>']));
        $test->assertTrue(validate(['name' => ['bad']], ['name' => 'required|max:255']) !== [], 'Array-valued form input must be rejected.');
    } finally {
        $_SERVER = $server;
        $_GET = $get;
        $_POST = $post;
    }

    $root = dirname(__DIR__);
    $routes = (string) file_get_contents($root . '/packages/AuthO/files/routes.php');
    $test->contains("route('POST /auth/register'", $routes, 'Registration form must have a POST route.');
    $test->contains("route('POST /auth/login'", $routes, 'Login form must have a POST route.');

    $temporary = sys_get_temp_dir() . '/jeephp-crud-test-' . bin2hex(random_bytes(6));
    mkdir($temporary, 0700, true);
    try {
        (new CrudGenerator($temporary))->generate('Product', ['name']);
        $form = (string) file_get_contents($temporary . '/app/Views/products/form.php');
        $test->contains('value="<?= e($value(\'name\')) ?>"', $form);
        $test->contains('return is_scalar($value) ? (string) $value : \'\';', $form);
        $test->assertTrue(!str_contains($form, 'return e('), 'Generated form must escape only once.');
    } finally {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($temporary);
    }
};
