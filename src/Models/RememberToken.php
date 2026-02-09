<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class RememberToken extends Model
{
    protected static string $table = 'remember_tokens';
    protected static array $fillable = ['user_id', 'token_hash', 'expires_at'];

    public static function createToken(int $userId): string
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $lifetime = $config['remember']['lifetime'];

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + $lifetime);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $tokenHash, $expiresAt]);

        return $rawToken;
    }

    public static function validate(string $rawToken): ?array
    {
        $tokenHash = hash('sha256', $rawToken);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT rt.user_id, u.username FROM remember_tokens rt
             JOIN users u ON u.id = rt.user_id
             WHERE rt.token_hash = ? AND rt.expires_at > NOW() AND u.is_active = 1'
        );
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function deleteToken(string $rawToken): void
    {
        $tokenHash = hash('sha256', $rawToken);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE token_hash = ?');
        $stmt->execute([$tokenHash]);
    }

    public static function clearForUser(int $userId): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    public static function clearAll(): void
    {
        $pdo = Database::getConnection();
        $pdo->exec('DELETE FROM remember_tokens');
    }

    public static function getLifetime(): int
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        return $config['remember']['lifetime'];
    }

    public static function getCookieName(): string
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        return $config['remember']['cookie_name'];
    }
}
