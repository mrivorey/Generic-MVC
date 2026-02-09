<?php

return [
    'name' => 'App',
    'timezone' => 'America/Chicago',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    'url' => getenv('APP_URL') ?: 'http://localhost:8088',

    // Paths
    'paths' => [
        'storage' => dirname(__DIR__) . '/storage',
        'sessions' => dirname(__DIR__) . '/storage/sessions',
        'views' => dirname(__DIR__) . '/src/Views',
    ],

    // Database
    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'app',
        'username' => getenv('DB_USERNAME') ?: 'app',
        'password' => getenv('DB_PASSWORD') ?: 'app_secret',
    ],

    // Session configuration
    'session' => [
        'name' => 'app_session',
        'lifetime' => 7200, // 2 hours
        'secure' => false,  // Set to true in production with HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    // Remember Me
    'remember' => [
        'lifetime' => 7776000, // 90 days in seconds
        'cookie_name' => 'remember_token',
    ],

    // CSRF Protection
    'csrf' => [
        'enabled' => true,
        'token_length' => 32,
        'session_key' => '_csrf_token',
        'form_field' => '_csrf_token',
        'safe_methods' => ['GET', 'HEAD', 'OPTIONS'],
    ],

    // Logging
    'logging' => [
        'default_channel' => getenv('LOG_CHANNEL') ?: 'app',
        'min_level' => getenv('LOG_LEVEL') ?: 'debug',
        'channels' => ['requests' => ['min_level' => 'info']],
    ],

    // Mail
    'mail' => [
        'host'         => getenv('MAIL_HOST') ?: 'localhost',
        'port'         => (int)(getenv('MAIL_PORT') ?: 587),
        'username'     => getenv('MAIL_USERNAME') ?: '',
        'password'     => getenv('MAIL_PASSWORD') ?: '',
        'encryption'   => getenv('MAIL_ENCRYPTION') ?: 'tls',
        'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@example.com',
        'from_name'    => getenv('MAIL_FROM_NAME') ?: 'App',
    ],

    // Password Reset
    'password_reset' => [
        'token_lifetime' => 3600, // 1 hour
    ],

    // Rate Limiting (Brute Force Protection)
    'rate_limit' => [
        'enabled' => true,
        'max_attempts' => 3,
        'lockout_minutes' => 30,
        'progressive' => true,
        'max_lockout_minutes' => 1440,  // 24 hours cap
        'attempt_window' => 900,         // 15 min window for counting attempts
    ],
];
