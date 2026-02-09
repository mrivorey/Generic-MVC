<?php

namespace App\Core;

class Database
{
    private static ?\PDO $connection = null;

    public static function getConnection(): \PDO
    {
        if (self::$connection === null) {
            $config = require dirname(__DIR__, 2) . '/config/app.php';
            $db = $config['database'];

            $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";

            self::$connection = new \PDO($dsn, $db['username'], $db['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        return self::$connection;
    }

    public static function reset(): void
    {
        self::$connection = null;
    }

    public static function setConnection(\PDO $connection): void
    {
        self::$connection = $connection;
    }
}
