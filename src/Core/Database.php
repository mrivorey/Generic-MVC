<?php

namespace App\Core;

class Database
{
    private static ?\PDO $connection = null;
    private static int $transactionDepth = 0;
    private static bool $ownsRealTransaction = false;

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

    /**
     * Execute a callback within a database transaction.
     *
     * Begins a transaction, executes the callback, and commits on success.
     * Rolls back and rethrows on any Throwable.
     */
    public static function transaction(callable $callback): mixed
    {
        self::beginTransaction();

        try {
            $result = $callback(self::getConnection());
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    /**
     * Begin a transaction or create a savepoint for nested transactions.
     *
     * At depth 0 with no existing PDO transaction: starts a real transaction.
     * Otherwise: creates a SAVEPOINT for nested transaction support.
     */
    public static function beginTransaction(): void
    {
        $pdo = self::getConnection();
        self::$transactionDepth++;

        if (self::$transactionDepth === 1 && !$pdo->inTransaction()) {
            $pdo->beginTransaction();
            self::$ownsRealTransaction = true;
        } else {
            $pdo->exec('SAVEPOINT sp_' . self::$transactionDepth);
        }
    }

    /**
     * Commit the current transaction or release a savepoint.
     *
     * @throws \RuntimeException if no active transaction
     */
    public static function commit(): void
    {
        if (self::$transactionDepth === 0) {
            throw new \RuntimeException('No active transaction to commit.');
        }

        $pdo = self::getConnection();

        if (self::$transactionDepth === 1 && self::$ownsRealTransaction) {
            $pdo->commit();
            self::$ownsRealTransaction = false;
        } else {
            $pdo->exec('RELEASE SAVEPOINT sp_' . self::$transactionDepth);
        }

        self::$transactionDepth--;
    }

    /**
     * Roll back the current transaction or roll back to a savepoint.
     *
     * @throws \RuntimeException if no active transaction
     */
    public static function rollBack(): void
    {
        if (self::$transactionDepth === 0) {
            throw new \RuntimeException('No active transaction to roll back.');
        }

        $pdo = self::getConnection();

        if (self::$transactionDepth === 1 && self::$ownsRealTransaction) {
            $pdo->rollBack();
            self::$ownsRealTransaction = false;
        } else {
            $pdo->exec('ROLLBACK TO SAVEPOINT sp_' . self::$transactionDepth);
        }

        self::$transactionDepth--;
    }

    /**
     * Get the current transaction nesting depth.
     */
    public static function getTransactionDepth(): int
    {
        return self::$transactionDepth;
    }

    public static function reset(): void
    {
        self::$connection = null;
        self::$transactionDepth = 0;
        self::$ownsRealTransaction = false;
    }

    public static function setConnection(\PDO $connection): void
    {
        self::$connection = $connection;
    }
}
