<?php

namespace App\Routing;

class Router
{
    private array $routes = [];

    public function __construct(array $routes = [])
    {
        $this->routes = $routes;
    }

    public function addRoute(string $method, string $path, array $handler): void
    {
        $this->routes[] = [$method, $path, $handler];
    }

    public function dispatch(string $method, string $uri): mixed
    {
        // Remove query string and trailing slash
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            [$routeMethod, $routePath, $handler] = $route;

            if ($routeMethod !== $method) {
                continue;
            }

            // Convert route path to regex pattern
            $pattern = $this->pathToPattern($routePath);

            if (preg_match($pattern, $uri, $matches)) {
                // Remove full match, keep only named captures
                array_shift($matches);

                [$controllerClass, $action] = $handler;
                $controller = new $controllerClass();

                return call_user_func_array([$controller, $action], $matches);
            }
        }

        // No route matched - return 404
        http_response_code(404);
        return $this->render404();
    }

    private function pathToPattern(string $path): string
    {
        // Escape forward slashes
        $pattern = str_replace('/', '\/', $path);

        // Convert {param} placeholders to named capture groups
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^\/]+)', $pattern);

        return '/^' . $pattern . '$/';
    }

    private function render404(): string
    {
        return '<!DOCTYPE html>
<html>
<head><title>404 Not Found</title></head>
<body>
<h1>404 - Page Not Found</h1>
<p>The requested page could not be found.</p>
</body>
</html>';
    }
}
