#!/usr/bin/env php
<?php
/**
 * Password Hash Generator
 *
 * Usage (run inside Docker container):
 *   docker-compose exec app php scripts/generate-password.php [password]
 *
 * If no password is provided, 'password' will be used as default.
 * The hash is written to the users table in the database.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;

$password = $argv[1] ?? 'password';

$hash = password_hash($password, PASSWORD_ARGON2ID);

try {
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare(
        'INSERT INTO users (username, password_hash) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
    );
    $stmt->execute(['admin', $hash]);

    echo "Password updated successfully!\n";
    echo "Username: admin\n";
    echo "Password: {$password}\n";
    echo "Hash: {$hash}\n";
} catch (\PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}
