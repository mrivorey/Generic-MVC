<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Role extends Model
{
    protected static string $table = 'roles';
    protected static array $fillable = ['name', 'slug', 'description'];

    public static function findBySlug(string $slug): ?array
    {
        return self::findBy('slug', $slug);
    }

    public static function permissions(int $roleId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT p.* FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = ?'
        );
        $stmt->execute([$roleId]);
        return $stmt->fetchAll();
    }

    public static function hasPermission(int $roleId, string $slug): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ? AND p.slug = ?'
        );
        $stmt->execute([$roleId, $slug]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function users(int $roleId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT u.* FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             WHERE ur.role_id = ?'
        );
        $stmt->execute([$roleId]);
        return $stmt->fetchAll();
    }

    public static function syncPermissions(int $roleId, array $permissionIds): void
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?');
        $stmt->execute([$roleId]);

        if (!empty($permissionIds)) {
            $stmt = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach ($permissionIds as $permissionId) {
                $stmt->execute([$roleId, (int) $permissionId]);
            }
        }
    }
}
