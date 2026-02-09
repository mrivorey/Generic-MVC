<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class PasswordResetToken extends Model
{
    protected static string $table = 'password_reset_tokens';
    protected static array $fillable = ['user_id', 'token_hash', 'expires_at'];

    public static function createToken(int $userId): string
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $lifetime = $config['password_reset']['token_lifetime'];

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + $lifetime);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at), created_at = NOW()'
        );
        $stmt->execute([$userId, $tokenHash, $expiresAt]);

        return $rawToken;
    }

    public static function validate(string $rawToken): ?array
    {
        $tokenHash = hash('sha256', $rawToken);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT prt.user_id, u.username, u.email, u.name
             FROM password_reset_tokens prt
             JOIN users u ON u.id = prt.user_id
             WHERE prt.token_hash = ? AND prt.expires_at > NOW() AND u.is_active = 1'
        );
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function deleteToken(string $rawToken): void
    {
        $tokenHash = hash('sha256', $rawToken);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE token_hash = ?');
        $stmt->execute([$tokenHash]);
    }

    public static function clearForUser(int $userId): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    public static function clearExpired(): void
    {
        $pdo = Database::getConnection();
        $pdo->exec('DELETE FROM password_reset_tokens WHERE expires_at <= NOW()');
    }
}
