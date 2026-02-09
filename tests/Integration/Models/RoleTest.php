<?php

namespace Tests\Integration\Models;

use App\Models\Role;
use App\Models\User;
use Tests\DatabaseTestCase;

class RoleTest extends DatabaseTestCase
{
    public function testFindBySlug(): void
    {
        $role = Role::findBySlug('admin');

        $this->assertNotNull($role);
        $this->assertSame('Admin', $role['name']);
    }

    public function testFindBySlugReturnsNullForMissing(): void
    {
        $this->assertNull(Role::findBySlug('superadmin'));
    }

    public function testPermissions(): void
    {
        $admin = Role::findBySlug('admin');
        $permissions = Role::permissions((int) $admin['id']);

        $this->assertGreaterThanOrEqual(4, count($permissions));

        $slugs = array_column($permissions, 'slug');
        $this->assertContains('users.view', $slugs);
        $this->assertContains('users.edit', $slugs);
    }

    public function testHasPermissionTrue(): void
    {
        $admin = Role::findBySlug('admin');
        $this->assertTrue(Role::hasPermission((int) $admin['id'], 'users.edit'));
    }

    public function testHasPermissionFalse(): void
    {
        $viewer = Role::findBySlug('viewer');
        $this->assertFalse(Role::hasPermission((int) $viewer['id'], 'users.edit'));
    }

    public function testSeededRolesExist(): void
    {
        $admin = Role::findBySlug('admin');
        $editor = Role::findBySlug('editor');
        $viewer = Role::findBySlug('viewer');

        $this->assertNotNull($admin);
        $this->assertNotNull($editor);
        $this->assertNotNull($viewer);
    }

    public function testUsers(): void
    {
        $user = $this->createTestUser([], ['admin']);

        $adminRole = Role::findBySlug('admin');
        $users = Role::users((int) $adminRole['id']);

        $userIds = array_column($users, 'id');
        $this->assertContains($user['id'], $userIds);
    }

    public function testSyncPermissions(): void
    {
        $role = Role::findBySlug('viewer');
        $roleId = (int) $role['id'];

        // Initially viewer has users.view
        $this->assertTrue(Role::hasPermission($roleId, 'users.view'));
        $this->assertFalse(Role::hasPermission($roleId, 'users.edit'));

        // Sync to different permissions
        $pdo = self::$pdo;
        $stmt = $pdo->prepare('SELECT id FROM permissions WHERE slug = ?');
        $stmt->execute(['users.edit']);
        $editPermId = (int) $stmt->fetchColumn();

        Role::syncPermissions($roleId, [$editPermId]);

        $this->assertTrue(Role::hasPermission($roleId, 'users.edit'));
        $this->assertFalse(Role::hasPermission($roleId, 'users.view'));
    }
}
