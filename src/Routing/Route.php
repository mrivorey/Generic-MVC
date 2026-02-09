<?php

namespace App\Routing;

class Route
{
    private array $methods;
    private string $uri;
    private array $action;
    private ?string $name = null;
    private array $middleware = [];
    private array $constraints = [];
    private ?string $prefix = null;

    public function __construct(array $methods, string $uri, array $action)
    {
        $this->methods = $methods;
        $this->uri = $uri;
        $this->action = $action;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        Router::registerNamed($this);
        return $this;
    }

    public function middleware(string|array $middleware): self
    {
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    public function where(string $param, string $pattern): self
    {
        $this->constraints[$param] = $pattern;
        return $this;
    }

    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
        $this->uri = rtrim($prefix, '/') . '/' . ltrim($this->uri, '/');
        // Normalize double slashes but keep leading slash
        $this->uri = '/' . ltrim(preg_replace('#/+#', '/', $this->uri), '/');
    }

    public function addMiddleware(array $middleware): void
    {
        $this->middleware = array_merge($middleware, $this->middleware);
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getAction(): array
    {
        return $this->action;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function matches(string $method, string $uri): ?array
    {
        if (!in_array($method, $this->methods, true)) {
            return null;
        }

        $pattern = $this->toRegex();

        if (preg_match($pattern, $uri, $matches)) {
            // Return only named captures
            return array_filter($matches, fn($key) => is_string($key), ARRAY_FILTER_USE_KEY);
        }

        return null;
    }

    private function toRegex(): string
    {
        $uri = $this->uri;

        // Escape forward slashes
        $pattern = str_replace('/', '\/', $uri);

        // Replace {param} with named capture groups using constraints if set
        $pattern = preg_replace_callback('/\{(\w+)\}/', function ($m) {
            $param = $m[1];
            $constraint = $this->constraints[$param] ?? '[^\/]+';
            return "(?P<{$param}>{$constraint})";
        }, $pattern);

        return '/^' . $pattern . '$/';
    }
}
