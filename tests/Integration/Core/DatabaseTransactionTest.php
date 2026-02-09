<?php

namespace Tests\Integration\Core;

use App\Core\Database;
use Tests\DatabaseTestCase;

class DatabaseTransactionTest extends DatabaseTestCase
{
    /**
     * Helper to insert a user directly via PDO and return the ID.
     */
    private function insertUser(string $username): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, name, is_active, password_hash) VALUES (?, ?, ?, 1, ?)'
        );
        $stmt->execute([
            $username,
            $username . '@example.com',
            'Test User',
            password_hash('password', PASSWORD_ARGON2ID),
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Helper to check if a user exists by username.
     */
    private function userExists(string $username): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
        $stmt->execute([$username]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function test_transaction_commits_on_success(): void
    {
        $username = 'txn_commit_' . uniqid();

        Database::transaction(function (\PDO $pdo) use ($username) {
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, email, name, is_active, password_hash) VALUES (?, ?, ?, 1, ?)'
            );
            $stmt->execute([
                $username,
                $username . '@example.com',
                'Test User',
                password_hash('password', PASSWORD_ARGON2ID),
            ]);
        });

        $this->assertTrue($this->userExists($username));
    }

    public function test_transaction_rolls_back_on_exception(): void
    {
        $username = 'txn_rollback_' . uniqid();

        try {
            Database::transaction(function (\PDO $pdo) use ($username) {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (username, email, name, is_active, password_hash) VALUES (?, ?, ?, 1, ?)'
                );
                $stmt->execute([
                    $username,
                    $username . '@example.com',
                    'Test User',
                    password_hash('password', PASSWORD_ARGON2ID),
                ]);

                throw new \RuntimeException('Simulated failure');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated failure', $e->getMessage());
        }

        $this->assertFalse($this->userExists($username));
    }

    public function test_transaction_returns_callback_result(): void
    {
        $result = Database::transaction(function (\PDO $pdo) {
            return 42;
        });

        $this->assertSame(42, $result);
    }

    public function test_nested_savepoints(): void
    {
        $outerUser = 'txn_outer_' . uniqid();
        $innerUser = 'txn_inner_' . uniqid();

        Database::transaction(function (\PDO $pdo) use ($outerUser, $innerUser) {
            // Insert in outer transaction
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, email, name, is_active, password_hash) VALUES (?, ?, ?, 1, ?)'
            );
            $stmt->execute([
                $outerUser,
                $outerUser . '@example.com',
                'Outer User',
                password_hash('password', PASSWORD_ARGON2ID),
            ]);

            // Inner transaction that rolls back
            try {
                Database::transaction(function (\PDO $pdo) use ($innerUser) {
                    $stmt = $pdo->prepare(
                        'INSERT INTO users (username, email, name, is_active, password_hash) VALUES (?, ?, ?, 1, ?)'
                    );
                    $stmt->execute([
                        $innerUser,
                        $innerUser . '@example.com',
                        'Inner User',
                        password_hash('password', PASSWORD_ARGON2ID),
                    ]);

                    throw new \RuntimeException('Inner failure');
                });
            } catch (\RuntimeException) {
                // Expected — inner transaction rolled back
            }
        });

        // Outer insert persisted, inner insert rolled back
        $this->assertTrue($this->userExists($outerUser));
        $this->assertFalse($this->userExists($innerUser));
    }

    public function test_manual_begin_commit(): void
    {
        $username = 'txn_manual_commit_' . uniqid();

        Database::beginTransaction();
        $this->insertUser($username);
        Database::commit();

        $this->assertTrue($this->userExists($username));
    }

    public function test_manual_begin_rollback(): void
    {
        $username = 'txn_manual_rollback_' . uniqid();

        Database::beginTransaction();
        $this->insertUser($username);
        Database::rollBack();

        $this->assertFalse($this->userExists($username));
    }

    public function test_commit_without_transaction_throws(): void
    {
        // DatabaseTestCase has a raw PDO transaction, but Database depth is 0.
        // Verify commit throws when depth is 0.
        $this->assertSame(0, Database::getTransactionDepth());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No active transaction to commit.');
        Database::commit();
    }

    public function test_rollback_without_transaction_throws(): void
    {
        // DatabaseTestCase has a raw PDO transaction, but Database depth is 0.
        // Verify rollBack throws when depth is 0.
        $this->assertSame(0, Database::getTransactionDepth());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No active transaction to roll back.');
        Database::rollBack();
    }
}
