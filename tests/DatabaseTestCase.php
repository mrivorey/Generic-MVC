<?php

namespace Tests;

use App\Core\Database;
use App\Models\User;

abstract class DatabaseTestCase extends TestCase
{
    protected static ?\PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$pdo === null) {
            $host = getenv('DB_HOST') ?: 'mysql';
            $port = getenv('DB_PORT') ?: '3306';
            $name = getenv('DB_NAME') ?: 'app_test';
            $user = getenv('DB_USERNAME') ?: 'app';
            $pass = getenv('DB_PASSWORD') ?: 'app_secret';

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

            self::$pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        // Inject test connection into the Database singleton
        Database::setConnection(self::$pdo);

        // Begin transaction for test isolation
        self::$pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Roll back to keep test DB clean
        if (self::$pdo && self::$pdo->inTransaction()) {
            self::$pdo->rollBack();
        }

        Database::reset();

        parent::tearDown();
    }

    protected function createTestUser(array $overrides = [], array $roles = ['viewer']): array
    {
        $defaults = [
            'username' => 'testuser_' . uniqid(),
            'email' => 'test_' . uniqid() . '@example.com',
            'name' => 'Test User',
            'is_active' => 1,
            'password_hash' => password_hash('password', PASSWORD_ARGON2ID),
        ];

        $data = array_merge($defaults, $overrides);

        $stmt = self::$pdo->prepare(
            'INSERT INTO users (username, email, name, is_active, password_hash) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['username'],
            $data['email'],
            $data['name'],
            $data['is_active'],
            $data['password_hash'],
        ]);

        $id = (int) self::$pdo->lastInsertId();

        // Assign roles
        $roleIds = [];
        foreach ($roles as $slug) {
            $roleIds[] = $this->getRoleId($slug);
        }
        User::syncRoles($id, $roleIds);

        return array_merge($data, ['id' => $id]);
    }

    protected function getRoleId(string $slug): int
    {
        $stmt = self::$pdo->prepare('SELECT id FROM roles WHERE slug = ?');
        $stmt->execute([$slug]);
        return (int) $stmt->fetchColumn();
    }
}
