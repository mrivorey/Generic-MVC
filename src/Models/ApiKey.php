<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class ApiKey extends Model
{
    protected static string $table = 'api_keys';
    protected static array $fillable = ['user_id', 'name', 'key_hash'];

    public static function generate(int $userId, string $name): string
    {
        $rawKey = 'app_' . bin2hex(random_bytes(32));
        $keyHash = hash('sha256', $rawKey);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO api_keys (user_id, name, key_hash) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $name, $keyHash]);

        return $rawKey;
    }

    public static function validateKey(string $rawKey): ?array
    {
        $keyHash = hash('sha256', $rawKey);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT ak.*, u.id AS uid, u.username, u.email, u.name AS user_name, u.is_active
             FROM api_keys ak
             JOIN users u ON u.id = ak.user_id
             WHERE ak.key_hash = ? AND u.is_active = 1'
        );
        $stmt->execute([$keyHash]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        // Update last_used_at
        $update = $pdo->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE id = ?');
        $update->execute([$row['id']]);

        return [
            'key_id' => $row['id'],
            'user_id' => $row['user_id'],
            'username' => $row['username'],
            'email' => $row['email'],
            'user_name' => $row['user_name'],
        ];
    }

    public static function forUser(int $userId): array
    {
        return self::where('user_id', $userId);
    }

    public static function revoke(int $keyId, int $userId): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM api_keys WHERE id = ? AND user_id = ?');
        return $stmt->execute([$keyId, $userId]);
    }
}
