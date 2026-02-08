<?php

namespace App\Models;

use App\Core\Database;

class RememberToken
{
    private int $lifetime;
    private string $cookieName;

    public function __construct()
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $this->lifetime = $config['remember']['lifetime'];
        $this->cookieName = $config['remember']['cookie_name'];
    }

    public function create(string $username): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + $this->lifetime);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO remember_tokens (username, token_hash, expires_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$username, $tokenHash, $expiresAt]);

        return $rawToken;
    }

    public function validate(string $rawToken): ?string
    {
        $tokenHash = hash('sha256', $rawToken);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT username FROM remember_tokens WHERE token_hash = ? AND expires_at > NOW()'
        );
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();

        return $row ? $row['username'] : null;
    }

    public function delete(string $rawToken): void
    {
        $tokenHash = hash('sha256', $rawToken);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE token_hash = ?');
        $stmt->execute([$tokenHash]);
    }

    public function clearAll(): void
    {
        $pdo = Database::getConnection();
        $pdo->exec('DELETE FROM remember_tokens');
    }

    public function getLifetime(): int
    {
        return $this->lifetime;
    }

    public function getCookieName(): string
    {
        return $this->cookieName;
    }
}
