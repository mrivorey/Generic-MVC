<?php

namespace App\Core;

class Cache
{
    private static ?CacheDriver $driver = null;
    private static ?int $defaultTtl = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::driver()->get($key);
        return $value !== null ? $value : $default;
    }

    public static function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $ttl ??= self::getDefaultTtl();
        self::driver()->set($key, $value, $ttl);
    }

    public static function has(string $key): bool
    {
        return self::driver()->has($key);
    }

    public static function delete(string $key): void
    {
        self::driver()->delete($key);
    }

    public static function clear(): void
    {
        self::driver()->clear();
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = self::driver()->get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        self::driver()->set($key, $value, $ttl);
        return $value;
    }

    public static function setDriver(CacheDriver $driver): void
    {
        self::$driver = $driver;
    }

    public static function reset(): void
    {
        self::$driver = null;
        self::$defaultTtl = null;
    }

    private static function driver(): CacheDriver
    {
        if (self::$driver === null) {
            self::$driver = new FileCacheDriver();
        }
        return self::$driver;
    }

    private static function getDefaultTtl(): int
    {
        if (self::$defaultTtl === null) {
            $config = require dirname(__DIR__, 2) . '/config/app.php';
            self::$defaultTtl = $config['cache']['ttl'] ?? 3600;
        }
        return self::$defaultTtl;
    }
}
