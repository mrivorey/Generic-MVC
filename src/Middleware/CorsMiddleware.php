<?php

namespace App\Middleware;

use App\Core\ExitTrap;

/**
 * CORS Middleware
 *
 * Handles Cross-Origin Resource Sharing by setting appropriate response headers
 * based on the incoming request's Origin header and the configured allowed origins.
 *
 * For preflight OPTIONS requests, responds with 204 No Content and the full set
 * of CORS headers (allowed methods, headers, max age), then exits early.
 *
 * Configuration is read from config/app.php under the 'cors' key, with sensible
 * defaults if not configured:
 *   - allowed_origins: ['*']
 *   - allowed_methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']
 *   - allowed_headers: ['Content-Type', 'Authorization', 'X-Requested-With']
 *   - max_age: 86400
 *   - allow_credentials: false
 *
 * Usage:
 *   CorsMiddleware::handle();
 *
 * @package App\Middleware
 */
class CorsMiddleware
{
    /**
     * Cached configuration (null = not yet loaded)
     */
    private static ?array $config = null;

    /**
     * Handle the CORS request
     *
     * Checks the Origin header against allowed origins, sets the appropriate
     * CORS response headers, and handles preflight OPTIONS requests by
     * responding with 204 and exiting.
     */
    public static function handle(): void
    {
        $config = self::loadConfig();
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // No Origin header means this is not a cross-origin request
        if ($origin === '') {
            return;
        }

        // Check if origin is allowed
        $allowed = false;
        $originHeader = '';

        if (in_array('*', $config['allowed_origins'], true)) {
            $allowed = true;
            $originHeader = '*';
        } elseif (in_array($origin, $config['allowed_origins'], true)) {
            $allowed = true;
            $originHeader = $origin;
        }

        if (!$allowed) {
            return;
        }

        // Cannot set headers after output has started
        if (headers_sent()) {
            return;
        }

        header("Access-Control-Allow-Origin: {$originHeader}");

        // Add Vary: Origin for non-wildcard so caches differentiate by origin
        if ($originHeader !== '*') {
            header('Vary: Origin');
        }

        // Credentials require a specific origin (not wildcard)
        if ($config['allow_credentials'] && $originHeader !== '*') {
            header('Access-Control-Allow-Credentials: true');
        }

        // Handle preflight OPTIONS request
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'OPTIONS') {
            header('Access-Control-Allow-Methods: ' . implode(', ', $config['allowed_methods']));
            header('Access-Control-Allow-Headers: ' . implode(', ', $config['allowed_headers']));
            header('Access-Control-Max-Age: ' . $config['max_age']);
            http_response_code(204);
            ExitTrap::exit();
        }
    }

    /**
     * Override configuration for testing or runtime customization
     *
     * @param array $config Configuration array with CORS settings
     */
    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Reset configuration so it will be reloaded from file on next access
     */
    public static function resetConfig(): void
    {
        self::$config = null;
    }

    /**
     * Load configuration from config file or return cached config
     *
     * Merges the cors section from config/app.php with sensible defaults.
     * Once loaded, the config is cached for the remainder of the request.
     *
     * @return array The resolved configuration
     */
    private static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        $cors = $appConfig['cors'] ?? [];

        self::$config = [
            'allowed_origins' => $cors['allowed_origins'] ?? ['*'],
            'allowed_methods' => $cors['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers' => $cors['allowed_headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With'],
            'max_age' => $cors['max_age'] ?? 86400,
            'allow_credentials' => $cors['allow_credentials'] ?? false,
        ];

        return self::$config;
    }
}
