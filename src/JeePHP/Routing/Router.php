<?php
declare(strict_types=1);

namespace JeePHP\Routing;

use JeePHP\Http\Request;
use RuntimeException;

final class Router
{
    private static ?self $active = null;

    private array $routes = [];

    public function __construct()
    {
        self::$active = $this;
    }

    public static function registerCurrent(string $definition, string $action): void
    {
        if (self::$active === null) {
            throw new RuntimeException('Create the JeePHP router before registering routes.');
        }

        self::$active->register($definition, $action);
    }

    public function register(string $definition, string $action): void
    {
        $method = 'GET';
        $path = trim($definition);

        if (preg_match('/^(GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD)\s+(.+)$/i', $path, $parts) === 1) {
            $method = strtoupper($parts[1]);
            $path = trim($parts[2]);
        }

        $this->add($method, $path, $action);
    }

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
        foreach ($this->routes[$request->method] ?? [] as $route) {
            if (preg_match($route['pattern'], $request->path, $matches) !== 1) {
                continue;
            }

            $parameters = [];
            foreach ($route['parameters'] as $name) {
                $parameters[$name] = rawurldecode($matches[$name]);
            }

            $result = $this->runAction($route['action'], $request->withRouteParameters($parameters));
            if (is_string($result)) {
                echo $result;
            }
            return;
        }

        http_response_code(404);
        echo '404 - Page not found';
    }

    private function add(string $method, string $path, mixed $action): void
    {
        $path = Request::removeConfiguredUrlExtension(trim($path));

        if ($path === '' || $path[0] !== '/') {
            throw new RuntimeException('Route paths must start with /.');
        }

        $path = '/' . trim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        $parameterNames = [];
        if ($path === '/') {
            $pattern = '#^/?$#';
        } else {
            $segments = explode('/', trim($path, '/'));
            $compiled = [];
            foreach ($segments as $segment) {
                if (preg_match('/^:([A-Za-z_][A-Za-z0-9_]*)$/', $segment, $match) === 1) {
                    $name = $match[1];
                    if (in_array($name, $parameterNames, true)) {
                        throw new RuntimeException('Route parameter names must be unique: ' . $name);
                    }
                    $parameterNames[] = $name;
                    $compiled[] = '(?P<' . $name . '>[^/]+)';
                    continue;
                }
                $compiled[] = preg_quote($segment, '#');
            }
            $pattern = '#^/' . implode('/', $compiled) . '/?$#';
        }

        $this->routes[$method][] = [
            'pattern' => $pattern,
            'parameters' => $parameterNames,
            'action' => $action,
        ];
    }

    private function runAction(mixed $action, Request $request): mixed
    {
        if (is_string($action)) {
            if (substr_count($action, '@') !== 1) {
                throw new RuntimeException('Route action must look like Controller@method.');
            }

            [$controller, $method] = explode('@', $action, 2);
            $controllerClass = str_contains($controller, '\\')
                ? ltrim($controller, '\\')
                : 'App\\Controllers\\' . $controller;

            if (!class_exists($controllerClass)) {
                throw new RuntimeException('Controller not found: ' . $controllerClass);
            }

            $instance = new $controllerClass();
            if (!is_callable([$instance, $method])) {
                throw new RuntimeException('Controller method not found: ' . $controllerClass . '@' . $method);
            }
            return $instance->{$method}($request);
        }

        if (is_array($action) && count($action) === 2 && is_string($action[0]) && is_string($action[1])) {
            $controllerClass = $action[0];
            if (!class_exists($controllerClass)) {
                throw new RuntimeException('Controller not found: ' . $controllerClass);
            }
            $instance = new $controllerClass();
            if (!is_callable([$instance, $action[1]])) {
                throw new RuntimeException('Controller method not found: ' . $controllerClass . '@' . $action[1]);
            }
            return $instance->{$action[1]}($request);
        }

        if (is_callable($action)) {
            return $action($request);
        }

        throw new RuntimeException('Route action must be Controller@method or a callable.');
    }
}
