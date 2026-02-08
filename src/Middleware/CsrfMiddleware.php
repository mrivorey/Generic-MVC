<?php

namespace App\Middleware;

/**
 * CSRF Protection Middleware
 *
 * Implements the Synchronizer Token Pattern to protect forms against
 * Cross-Site Request Forgery attacks. Tokens are stored in the session
 * and validated on form submission.
 *
 * Usage:
 *   1. Add the hidden field to your form:
 *      <?= \App\Middleware\CsrfMiddleware::field() ?>
 *
 *   2. Validate in your controller before processing:
 *      CsrfMiddleware::verify(); // Halts with 403 on failure
 *
 *   3. Regenerate token after login:
 *      CsrfMiddleware::regenerate();
 *
 *   4. Clear token on logout:
 *      CsrfMiddleware::clear();
 *
 * @package App\Middleware
 */
class CsrfMiddleware
{
    /**
     * Default configuration values
     */
    private static array $defaults = [
        'enabled' => true,
        'token_length' => 32,
        'session_key' => '_csrf_token',
        'form_field' => '_csrf_token',
        'safe_methods' => ['GET', 'HEAD', 'OPTIONS'],
    ];

    /**
     * Cached configuration
     */
    private static ?array $config = null;

    /**
     * Get merged configuration with defaults
     *
     * @return array The merged configuration
     */
    public static function getConfig(): array
    {
        if (self::$config === null) {
            $appConfig = [];
            $configFile = dirname(__DIR__, 2) . '/config/app.php';

            if (file_exists($configFile)) {
                $appConfig = require $configFile;
            }

            self::$config = array_merge(
                self::$defaults,
                $appConfig['csrf'] ?? []
            );
        }

        return self::$config;
    }

    /**
     * Get or generate CSRF token from session
     *
     * Creates a new token if one doesn't exist. Token is generated using
     * cryptographically secure random bytes.
     *
     * @return string The CSRF token (64 hex characters)
     */
    public static function token(): string
    {
        $config = self::getConfig();
        $sessionKey = $config['session_key'];

        if (empty($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = bin2hex(random_bytes($config['token_length']));
        }

        return $_SESSION[$sessionKey];
    }

    /**
     * Generate hidden input field HTML for forms
     *
     * @return string HTML hidden input element
     */
    public static function field(): string
    {
        $config = self::getConfig();
        $fieldName = htmlspecialchars($config['form_field'], ENT_QUOTES, 'UTF-8');
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');

        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            $fieldName,
            $token
        );
    }

    /**
     * Validate CSRF token from request
     *
     * Performs timing-safe comparison using hash_equals().
     * Safe methods (GET, HEAD, OPTIONS) always return true.
     *
     * @return bool True if token is valid or method is safe
     */
    public static function validate(): bool
    {
        $config = self::getConfig();

        // Skip validation if disabled
        if (!$config['enabled']) {
            return true;
        }

        // Skip validation for safe HTTP methods
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, $config['safe_methods'], true)) {
            return true;
        }

        $sessionKey = $config['session_key'];
        $fieldName = $config['form_field'];

        // Get tokens
        $sessionToken = $_SESSION[$sessionKey] ?? '';
        $requestToken = $_POST[$fieldName] ?? '';

        // Both tokens must exist and match
        if (empty($sessionToken) || empty($requestToken)) {
            return false;
        }

        return hash_equals($sessionToken, $requestToken);
    }

    /**
     * Validate token and halt with 403 on failure
     *
     * Sets a session error message and sends 403 Forbidden response
     * if validation fails. Use this in controllers for simple protection.
     *
     * @return void
     */
    public static function verify(): void
    {
        if (!self::validate()) {
            $_SESSION['csrf_error'] = 'Invalid or missing security token. Please try again.';

            http_response_code(403);
            header('Content-Type: text/plain');
            echo 'Forbidden: Invalid CSRF token';
            exit;
        }
    }

    /**
     * Regenerate CSRF token
     *
     * Creates a new token, invalidating the old one. Call this after
     * successful login to prevent session fixation attacks.
     *
     * @return string The new CSRF token
     */
    public static function regenerate(): string
    {
        $config = self::getConfig();
        $sessionKey = $config['session_key'];

        $_SESSION[$sessionKey] = bin2hex(random_bytes($config['token_length']));

        return $_SESSION[$sessionKey];
    }

    /**
     * Clear CSRF token from session
     *
     * Call this on logout to clean up the session.
     *
     * @return void
     */
    public static function clear(): void
    {
        $config = self::getConfig();
        unset($_SESSION[$config['session_key']]);
    }
}
