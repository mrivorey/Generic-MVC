<?php

namespace App\Middleware;

/**
 * Security Headers Middleware
 *
 * Applies security-related HTTP headers to every response to protect against
 * common web vulnerabilities such as clickjacking, MIME sniffing, and XSS.
 *
 * Headers set:
 *   - X-Content-Type-Options: nosniff
 *   - X-Frame-Options: DENY (configurable)
 *   - X-XSS-Protection: 0
 *   - Referrer-Policy: strict-origin-when-cross-origin
 *   - Permissions-Policy: camera=(), microphone=(), geolocation=()
 *   - Content-Security-Policy (optional, from config)
 *
 * Usage:
 *   SecurityHeadersMiddleware::apply();
 *
 * @package App\Middleware
 */
class SecurityHeadersMiddleware
{
    /**
     * Cached configuration (null = not yet loaded)
     */
    private static ?array $config = null;

    /**
     * Override configuration for testing or runtime customization
     *
     * @param array $config Configuration array with keys: enabled, frame_options, csp
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
     * Merges the security_headers section from config/app.php with sensible
     * defaults. Once loaded, the config is cached for the remainder of the request.
     *
     * @return array The resolved configuration
     */
    private static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $appConfig = [];
        $configFile = dirname(__DIR__, 2) . '/config/app.php';

        if (file_exists($configFile)) {
            $appConfig = require $configFile;
        }

        $headers = $appConfig['security_headers'] ?? [];

        self::$config = [
            'enabled' => $headers['enabled'] ?? true,
            'frame_options' => $headers['frame_options'] ?? 'DENY',
            'csp' => $headers['csp'] ?? '',
        ];

        return self::$config;
    }

    /**
     * Apply security headers to the current response
     *
     * Checks that headers have not already been sent and that the middleware
     * is enabled before setting any headers. Each header targets a specific
     * class of browser-side vulnerability.
     */
    public static function apply(): void
    {
        $config = self::loadConfig();

        if (!$config['enabled']) {
            return;
        }

        if (headers_sent()) {
            return;
        }

        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');

        // Prevent clickjacking — configurable (DENY, SAMEORIGIN)
        header('X-Frame-Options: ' . $config['frame_options']);

        // Disable legacy XSS auditor (modern browsers ignore it; 0 is safest)
        header('X-XSS-Protection: 0');

        // Control referrer information sent with requests
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Restrict access to browser features
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        // Content Security Policy (only when configured)
        if (!empty($config['csp'])) {
            header('Content-Security-Policy: ' . $config['csp']);
        }
    }
}
