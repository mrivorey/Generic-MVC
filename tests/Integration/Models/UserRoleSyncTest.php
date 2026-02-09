<?php

namespace Tests\Integration\Models;

use App\Models\User;
use Tests\DatabaseTestCase;

class UserRoleSyncTest extends DatabaseTestCase
{
    public function testSyncRolesAssign(): void
    {
        $user = $this->createTestUser([], []);

        $this->assertEmpty(User::roles($user['id']));

        User::syncRoles($user['id'], [$this->getRoleId('admin')]);

        $roles = User::roles($user['id']);
        $this->assertCount(1, $roles);
        $this->assertSame('admin', $roles[0]['slug']);
    }

    public function testSyncRolesReassign(): void
    {
        $user = $this->createTestUser([], ['viewer']);

        $this->assertTrue(User::hasRole($user['id'], 'viewer'));
        $this->assertFalse(User::hasRole($user['id'], 'editor'));

        User::syncRoles($user['id'], [$this->getRoleId('editor')]);

        $this->assertFalse(User::hasRole($user['id'], 'viewer'));
        $this->assertTrue(User::hasRole($user['id'], 'editor'));
    }

    public function testSyncRolesMultiple(): void
    {
        $user = $this->createTestUser([], []);

        User::syncRoles($user['id'], [
            $this->getRoleId('admin'),
            $this->getRoleId('editor'),
        ]);

        $roles = User::roles($user['id']);
        $slugs = array_column($roles, 'slug');
        $this->assertCount(2, $roles);
        $this->assertContains('admin', $slugs);
        $this->assertContains('editor', $slugs);
    }

    public function testSyncRolesClear(): void
    {
        $user = $this->createTestUser([], ['admin', 'editor']);

        $this->assertCount(2, User::roles($user['id']));

        User::syncRoles($user['id'], []);

        $this->assertEmpty(User::roles($user['id']));
    }

    public function testSyncRolesIdempotent(): void
    {
        $user = $this->createTestUser([], ['admin']);

        User::syncRoles($user['id'], [$this->getRoleId('admin')]);
        User::syncRoles($user['id'], [$this->getRoleId('admin')]);

        $roles = User::roles($user['id']);
        $this->assertCount(1, $roles);
        $this->assertSame('admin', $roles[0]['slug']);
    }
}
