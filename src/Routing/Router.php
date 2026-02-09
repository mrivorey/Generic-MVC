<?php

namespace App\Routing;

class Router
{
    private static array $routes = [];
    private static array $namedRoutes = [];
    private static array $groupStack = [];

    public static function get(string $uri, array $action): Route
    {
        return self::addRoute(['GET'], $uri, $action);
    }

    public static function post(string $uri, array $action): Route
    {
        return self::addRoute(['POST'], $uri, $action);
    }

    public static function put(string $uri, array $action): Route
    {
        return self::addRoute(['PUT'], $uri, $action);
    }

    public static function patch(string $uri, array $action): Route
    {
        return self::addRoute(['PATCH'], $uri, $action);
    }

    public static function delete(string $uri, array $action): Route
    {
        return self::addRoute(['DELETE'], $uri, $action);
    }

    public static function match(array $methods, string $uri, array $action): Route
    {
        return self::addRoute($methods, $uri, $action);
    }

    public static function resource(string $uri, string $controller): void
    {
        $name = trim($uri, '/');
        $name = str_replace('/', '.', $name);

        self::get($uri, [$controller, 'index'])->name("{$name}.index");
        self::get("{$uri}/create", [$controller, 'create'])->name("{$name}.create");
        self::post($uri, [$controller, 'store'])->name("{$name}.store");
        self::get("{$uri}/{id}", [$controller, 'show'])->name("{$name}.show")->where('id', '[0-9]+');
        self::get("{$uri}/{id}/edit", [$controller, 'edit'])->name("{$name}.edit")->where('id', '[0-9]+');
        self::put("{$uri}/{id}", [$controller, 'update'])->name("{$name}.update")->where('id', '[0-9]+');
        self::delete("{$uri}/{id}", [$controller, 'destroy'])->name("{$name}.destroy")->where('id', '[0-9]+');
    }

    public static function group(array $attributes, callable $callback): void
    {
        self::$groupStack[] = $attributes;
        $callback();
        array_pop(self::$groupStack);
    }

    public static function url(string $name, array $params = []): string
    {
        if (!isset(self::$namedRoutes[$name])) {
            throw new \RuntimeException("Route [{$name}] not defined.");
        }

        $uri = self::$namedRoutes[$name]->getUri();

        foreach ($params as $key => $value) {
            $uri = str_replace("{{$key}}", $value, $uri);
        }

        return $uri;
    }

    public static function dispatch(): mixed
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';

        // Method spoofing: POST with _method field
        if ($method === 'POST' && isset($_POST['_method'])) {
            $spoofed = strtoupper($_POST['_method']);
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $spoofed;
            }
        }

        foreach (self::$routes as $route) {
            $params = $route->matches($method, $uri);
            if ($params !== null) {
                // Run middleware pipeline
                $middleware = $route->getMiddleware();
                if (!empty($middleware)) {
                    MiddlewarePipeline::run($middleware);
                }

                [$controllerClass, $action] = $route->getAction();
                $controller = new $controllerClass();

                return call_user_func_array([$controller, $action], array_values($params));
            }
        }

        throw new \App\Exceptions\NotFoundException();
    }

    public static function getRoutes(): array
    {
        return self::$routes;
    }

    public static function reset(): void
    {
        self::$routes = [];
        self::$namedRoutes = [];
        self::$groupStack = [];
    }

    private static function addRoute(array $methods, string $uri, array $action): Route
    {
        $route = new Route($methods, $uri, $action);

        // Apply group attributes
        foreach (self::$groupStack as $group) {
            if (isset($group['prefix'])) {
                $route->setPrefix($group['prefix']);
            }
            if (isset($group['middleware'])) {
                $mw = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                $route->addMiddleware($mw);
            }
        }

        self::$routes[] = $route;

        return $route;
    }

    public static function registerNamed(Route $route): void
    {
        $name = $route->getName();
        if ($name !== null) {
            self::$namedRoutes[$name] = $route;
        }
    }
}
