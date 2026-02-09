<?php

namespace App\Middleware;

use App\Core\Database;

/**
 * Rate Limiting Middleware (Brute Force Protection)
 *
 * Implements IP-based rate limiting to protect against brute force login attacks.
 * Uses MySQL storage with progressive lockouts that double after each violation.
 *
 * Usage:
 *   1. Check if request is allowed before processing login:
 *      $rateCheck = RateLimitMiddleware::check();
 *      if (!$rateCheck['allowed']) { ... }
 *
 *   2. Record failed attempts:
 *      RateLimitMiddleware::recordAttempt();
 *
 *   3. Clear attempts after successful login:
 *      RateLimitMiddleware::clear();
 *
 * @package App\Middleware
 */
class RateLimitMiddleware
{
    private static array $defaults = [
        'enabled' => true,
        'max_attempts' => 3,
        'lockout_minutes' => 30,
        'progressive' => true,
        'max_lockout_minutes' => 1440,  // 24 hours
        'attempt_window' => 900,         // 15 minutes
    ];

    private static ?array $config = null;

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
                $appConfig['rate_limit'] ?? []
            );
        }

        return self::$config;
    }

    private static function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private static function load(string $ip): array
    {
        $config = self::getConfig();

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT attempts, lockout_until, lockout_count FROM rate_limits WHERE ip_address = ?');
        $stmt->execute([$ip]);
        $row = $stmt->fetch();

        $data = [
            'attempts' => [],
            'lockout_until' => null,
            'lockout_count' => 0,
        ];

        if ($row) {
            $data['attempts'] = json_decode($row['attempts'], true) ?: [];
            $data['lockout_until'] = $row['lockout_until'] ? strtotime($row['lockout_until']) : null;
            $data['lockout_count'] = (int)$row['lockout_count'];
        }

        // Filter out expired attempts (outside the attempt window)
        $cutoff = time() - $config['attempt_window'];
        $data['attempts'] = array_values(array_filter(
            $data['attempts'],
            fn($timestamp) => $timestamp > $cutoff
        ));

        return $data;
    }

    private static function save(string $ip, array $data): bool
    {
        $pdo = Database::getConnection();

        $attemptsJson = json_encode(array_values($data['attempts']));
        $lockoutUntil = $data['lockout_until'] ? date('Y-m-d H:i:s', $data['lockout_until']) : null;

        $stmt = $pdo->prepare(
            'INSERT INTO rate_limits (ip_address, attempts, lockout_until, lockout_count)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                attempts = VALUES(attempts),
                lockout_until = VALUES(lockout_until),
                lockout_count = VALUES(lockout_count)'
        );

        return $stmt->execute([$ip, $attemptsJson, $lockoutUntil, $data['lockout_count']]);
    }

    private static function calculateLockout(int $lockoutCount): int
    {
        $config = self::getConfig();
        $baseMinutes = $config['lockout_minutes'];
        $maxMinutes = $config['max_lockout_minutes'];

        if (!$config['progressive']) {
            return $baseMinutes * 60;
        }

        $minutes = $baseMinutes * pow(2, $lockoutCount);
        $minutes = min($minutes, $maxMinutes);

        return $minutes * 60;
    }

    public static function check(?string $ip = null): array
    {
        $config = self::getConfig();

        if (!$config['enabled']) {
            return [
                'allowed' => true,
                'remaining' => $config['max_attempts'],
                'retry_after' => null,
            ];
        }

        $ip = $ip ?? self::getClientIp();
        $data = self::load($ip);

        // Check if currently locked out
        if ($data['lockout_until'] !== null && $data['lockout_until'] > time()) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'retry_after' => $data['lockout_until'] - time(),
            ];
        }

        // Clear expired lockout
        if ($data['lockout_until'] !== null && $data['lockout_until'] <= time()) {
            $data['lockout_until'] = null;
            self::save($ip, $data);
        }

        $remaining = $config['max_attempts'] - count($data['attempts']);

        return [
            'allowed' => true,
            'remaining' => max(0, $remaining),
            'retry_after' => null,
        ];
    }

    public static function isLocked(?string $ip = null): bool
    {
        return !self::check($ip)['allowed'];
    }

    public static function recordAttempt(?string $ip = null): void
    {
        $config = self::getConfig();

        if (!$config['enabled']) {
            return;
        }

        $ip = $ip ?? self::getClientIp();
        $data = self::load($ip);

        $data['attempts'][] = time();

        if (count($data['attempts']) >= $config['max_attempts']) {
            $lockoutSeconds = self::calculateLockout($data['lockout_count']);
            $data['lockout_until'] = time() + $lockoutSeconds;
            $data['lockout_count']++;
            $data['attempts'] = [];
        }

        self::save($ip, $data);
    }

    public static function clear(?string $ip = null): void
    {
        $config = self::getConfig();

        if (!$config['enabled']) {
            return;
        }

        $ip = $ip ?? self::getClientIp();

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM rate_limits WHERE ip_address = ?');
        $stmt->execute([$ip]);
    }

    public static function resetConfig(): void
    {
        self::$config = null;
    }
}
