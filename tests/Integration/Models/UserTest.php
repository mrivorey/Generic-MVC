<?php

namespace Tests\Integration\Models;

use App\Models\User;
use App\Models\Role;
use Tests\DatabaseTestCase;

class UserTest extends DatabaseTestCase
{
    public function testAuthenticateSuccess(): void
    {
        $user = $this->createTestUser([
            'username' => 'authtest',
            'password_hash' => password_hash('correct-password', PASSWORD_ARGON2ID),
        ]);

        $result = User::authenticate('authtest', 'correct-password');

        $this->assertNotNull($result);
        $this->assertSame('authtest', $result['username']);
    }

    public function testAuthenticateWrongPassword(): void
    {
        $this->createTestUser([
            'username' => 'wrongpw',
            'password_hash' => password_hash('correct', PASSWORD_ARGON2ID),
        ]);

        $result = User::authenticate('wrongpw', 'incorrect');

        $this->assertNull($result);
    }

    public function testAuthenticateInactiveUser(): void
    {
        $this->createTestUser([
            'username' => 'inactive',
            'is_active' => 0,
            'password_hash' => password_hash('password', PASSWORD_ARGON2ID),
        ]);

        $result = User::authenticate('inactive', 'password');

        $this->assertNull($result);
    }

    public function testAuthenticateNonexistentUser(): void
    {
        $result = User::authenticate('doesnotexist', 'password');

        $this->assertNull($result);
    }

    public function testAuthenticateUpdatesLastLoginAt(): void
    {
        $user = $this->createTestUser([
            'username' => 'logintime',
            'password_hash' => password_hash('password', PASSWORD_ARGON2ID),
        ]);

        $before = User::find($user['id']);
        $this->assertNull($before['last_login_at']);

        User::authenticate('logintime', 'password');

        $after = User::find($user['id']);
        $this->assertNotNull($after['last_login_at']);
    }

    public function testUpdatePasswordSuccess(): void
    {
        $user = $this->createTestUser([
            'password_hash' => password_hash('oldpass', PASSWORD_ARGON2ID),
        ]);

        $result = User::updatePassword($user['id'], 'oldpass', 'newpass');

        $this->assertTrue($result);

        // Verify new password works
        $updated = User::find($user['id']);
        $this->assertTrue(password_verify('newpass', $updated['password_hash']));
    }

    public function testUpdatePasswordWrongCurrent(): void
    {
        $user = $this->createTestUser([
            'password_hash' => password_hash('correct', PASSWORD_ARGON2ID),
        ]);

        $result = User::updatePassword($user['id'], 'wrong', 'newpass');

        $this->assertFalse($result);
    }

    public function testSetPassword(): void
    {
        $user = $this->createTestUser();

        $result = User::setPassword($user['id'], 'brandnew');

        $this->assertTrue($result);
        $updated = User::find($user['id']);
        $this->assertTrue(password_verify('brandnew', $updated['password_hash']));
    }

    public function testRoles(): void
    {
        $user = $this->createTestUser([], ['admin']);

        $roles = User::roles($user['id']);

        $this->assertNotEmpty($roles);
        $slugs = array_column($roles, 'slug');
        $this->assertContains('admin', $slugs);
    }

    public function testRolesMultiple(): void
    {
        $user = $this->createTestUser([], ['editor', 'viewer']);

        $roles = User::roles($user['id']);

        $slugs = array_column($roles, 'slug');
        $this->assertContains('editor', $slugs);
        $this->assertContains('viewer', $slugs);
        $this->assertCount(2, $roles);
    }

    public function testHasRoleTrue(): void
    {
        $user = $this->createTestUser([], ['admin']);

        $this->assertTrue(User::hasRole($user['id'], 'admin'));
    }

    public function testHasRoleFalse(): void
    {
        $user = $this->createTestUser([], ['viewer']);

        $this->assertFalse(User::hasRole($user['id'], 'admin'));
    }

    public function testHasRoleWithMultipleRoles(): void
    {
        $user = $this->createTestUser([], ['editor', 'viewer']);

        $this->assertTrue(User::hasRole($user['id'], 'editor'));
        $this->assertTrue(User::hasRole($user['id'], 'viewer'));
        $this->assertFalse(User::hasRole($user['id'], 'admin'));
    }

    public function testHasPermissionAdminHasAll(): void
    {
        $user = $this->createTestUser([], ['admin']);

        $this->assertTrue(User::hasPermission($user['id'], 'users.edit'));
        $this->assertTrue(User::hasPermission($user['id'], 'users.delete'));
    }

    public function testHasPermissionEditorLimited(): void
    {
        $user = $this->createTestUser([], ['editor']);

        // Editor has users.view but not users.edit
        $this->assertTrue(User::hasPermission($user['id'], 'users.view'));
        $this->assertFalse(User::hasPermission($user['id'], 'users.edit'));
    }

    public function testHasPermissionMultiRoleUnion(): void
    {
        // Create a user with both editor and viewer roles
        // Both have users.view — verify union works
        $user = $this->createTestUser([], ['editor', 'viewer']);

        $this->assertTrue(User::hasPermission($user['id'], 'users.view'));
        $this->assertFalse(User::hasPermission($user['id'], 'users.delete'));
    }

    public function testHasPermissionNoRoles(): void
    {
        $user = $this->createTestUser([], []);

        $this->assertFalse(User::hasPermission($user['id'], 'users.view'));
    }
}
