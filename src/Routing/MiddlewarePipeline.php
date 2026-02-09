<?php

namespace App\Routing;

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\ApiAuthMiddleware;
use App\Middleware\ApiRateLimitMiddleware;

class MiddlewarePipeline
{
    private static array $aliases = [
        'auth' => [AuthMiddleware::class, 'requireAuth'],
        'csrf' => [CsrfMiddleware::class, 'verify'],
        'role' => [AuthMiddleware::class, 'requireRole'],
        'permission' => [AuthMiddleware::class, 'requirePermission'],
        'api_auth' => [ApiAuthMiddleware::class, 'verify'],
        'api_rate_limit' => [ApiRateLimitMiddleware::class, 'check'],
    ];

    public static function register(string $alias, array $handler): void
    {
        self::$aliases[$alias] = $handler;
    }

    public static function run(array $middlewareList): void
    {
        foreach ($middlewareList as $middleware) {
            $params = [];
            if (str_contains($middleware, ':')) {
                [$middleware, $paramStr] = explode(':', $middleware, 2);
                $params = explode(',', $paramStr);
            }

            if (!isset(self::$aliases[$middleware])) {
                throw new \RuntimeException("Unknown middleware alias: {$middleware}");
            }

            $handler = self::$aliases[$middleware];
            call_user_func_array($handler, $params);
        }
    }
}
