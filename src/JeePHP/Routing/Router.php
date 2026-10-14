<?php
declare(strict_types=1);

namespace JeePHP\Routing;

use JeePHP\Http\Request;
use RuntimeException;

final class Router
{
    private array $routes = [];

    public function get(string $path, mixed $action): void
    {
        $this->add('GET', $path, $action);
    }

    public function post(string $path, mixed $action): void
    {
        $this->add('POST', $path, $action);
    }

    public function dispatch(Request $request): void
    {
        $action = $this->routes[$request->method][$request->path] ?? null;
        if ($action === null) {
            http_response_code(404);
            echo '404 - Page not found';
            return;
        }

        if (is_array($action) && count($action) === 2 && is_string($action[0])) {
            $controllerClass = $action[0];
            $controller = new $controllerClass();
            $result = $controller->{$action[1]}($request);
        } else {
            if (!is_callable($action)) {
                throw new RuntimeException('Route action must be a callable or [Controller::class, method].');
            }
            $result = $action($request);
        }
        if (is_string($result)) {
            echo $result;
        }
    }

    private function add(string $method, string $path, mixed $action): void
    {
        if ($path === '' || $path[0] !== '/') {
            throw new RuntimeException('Route paths must start with /.');
        }
        $this->routes[$method][rtrim($path, '/') ?: '/'] = $action;
    }
}
