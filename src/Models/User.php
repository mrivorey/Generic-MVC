<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class User extends Model
{
    protected static string $table = 'users';
    protected static array $fillable = ['username', 'email', 'name', 'is_active', 'password_hash'];

    public static function authenticate(string $username, string $password): ?array
    {
        $user = self::findBy('username', $username);
        if (!$user) {
            return null;
        }

        if (!$user['is_active']) {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        // Update last_login_at
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$user['id']]);

        return $user;
    }

    public static function updatePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $user = self::find($userId);
        if (!$user) {
            return false;
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            return false;
        }

        $newHash = password_hash($newPassword, PASSWORD_ARGON2ID);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        return $stmt->execute([$newHash, $userId]);
    }

    public static function setPassword(int $userId, string $newPassword): bool
    {
        $newHash = password_hash($newPassword, PASSWORD_ARGON2ID);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        return $stmt->execute([$newHash, $userId]);
    }

    public static function roles(int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT r.* FROM roles r
             JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = ?'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function hasRole(int $userId, string $slug): bool
    {
        $roles = self::roles($userId);
        foreach ($roles as $role) {
            if ($role['slug'] === $slug) {
                return true;
            }
        }
        return false;
    }

    public static function hasPermission(int $userId, string $slug): bool
    {
        $roles = self::roles($userId);
        if (empty($roles)) {
            return false;
        }

        // Admin has all permissions
        foreach ($roles as $role) {
            if ($role['slug'] === 'admin') {
                return true;
            }
        }

        // Check union of permissions from all roles
        $roleIds = array_column($roles, 'id');
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id IN ({$placeholders}) AND p.slug = ?"
        );
        $stmt->execute([...$roleIds, $slug]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function syncRoles(int $userId, array $roleIds): void
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('DELETE FROM user_roles WHERE user_id = ?');
        $stmt->execute([$userId]);

        if (!empty($roleIds)) {
            $stmt = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
            foreach ($roleIds as $roleId) {
                $stmt->execute([$userId, (int) $roleId]);
            }
        }
    }
}
