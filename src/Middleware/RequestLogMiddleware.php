<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Logger;

class RequestLogMiddleware
{
    private static ?float $startTime = null;

    public static function start(): void
    {
        self::$startTime = microtime(true);
        register_shutdown_function([self::class, 'log']);
    }

    public static function log(): void
    {
        if (self::$startTime === null) {
            return;
        }

        $duration = round((microtime(true) - self::$startTime) * 1000, 2);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $status = http_response_code() ?: 200;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $message = "{$method} {$uri} {$status} {$duration}ms";

        $context = [
            'method' => $method,
            'uri' => $uri,
            'status' => $status,
            'duration_ms' => $duration,
            'ip' => $ip,
        ];

        if (isset($_SESSION['user_id'])) {
            $context['user_id'] = $_SESSION['user_id'];
        }

        Logger::channel('requests')->info($message, $context);

        self::$startTime = null;
    }

    public static function reset(): void
    {
        self::$startTime = null;
    }
}
