<?php

namespace Tests\Integration\Middleware;

use App\Core\ExitException;
use App\Middleware\AuthMiddleware;
use App\Models\User;
use Tests\DatabaseTestCase;

class AuthMiddlewareTest extends DatabaseTestCase
{
    public function testCheckFalseWhenNoSession(): void
    {
        $this->assertFalse(AuthMiddleware::check());
    }

    public function testCheckTrueAfterSetAuthenticated(): void
    {
        $user = $this->createTestUser([], ['admin']);
        AuthMiddleware::setAuthenticated($user);

        $this->assertTrue(AuthMiddleware::check());
    }

    public function testSetAuthenticatedStoresSessionData(): void
    {
        $user = $this->createTestUser([
            'username' => 'sessiontest',
            'name' => 'Session Tester',
        ], ['editor']);

        AuthMiddleware::setAuthenticated($user);

        $this->assertTrue($_SESSION['authenticated']);
        $this->assertSame((int) $user['id'], $_SESSION['user_id']);
        $this->assertSame('sessiontest', $_SESSION['username']);
        $this->assertIsArray($_SESSION['user_roles']);
        $this->assertContains('editor', $_SESSION['user_roles']);
        $this->assertSame('Session Tester', $_SESSION['user_name']);
    }

    public function testSetAuthenticatedStoresMultipleRoles(): void
    {
        $user = $this->createTestUser([
            'username' => 'multirole',
        ], ['editor', 'viewer']);

        AuthMiddleware::setAuthenticated($user);

        $this->assertContains('editor', $_SESSION['user_roles']);
        $this->assertContains('viewer', $_SESSION['user_roles']);
        $this->assertCount(2, $_SESSION['user_roles']);
    }

    public function testRequireAuthThrowsExitExceptionWhenNotAuthenticated(): void
    {
        $this->expectException(ExitException::class);
        AuthMiddleware::requireAuth();
    }

    public function testRequireAuthPassesWhenAuthenticated(): void
    {
        $user = $this->createTestUser([], ['viewer']);
        AuthMiddleware::setAuthenticated($user);

        // Should not throw
        AuthMiddleware::requireAuth();
        $this->assertTrue(true);
    }

    public function testRequireRolePassesForAdmin(): void
    {
        $user = $this->createTestUser([], ['admin']);
        AuthMiddleware::setAuthenticated($user);

        // Should not throw
        AuthMiddleware::requireRole('admin');
        $this->assertTrue(true);
    }

    public function testRequireRoleThrowsForViewerAccessingAdmin(): void
    {
        $user = $this->createTestUser([], ['viewer']);
        AuthMiddleware::setAuthenticated($user);

        $this->expectException(ExitException::class);
        AuthMiddleware::requireRole('admin');
    }

    public function testRequireRolePassesWithMultipleAllowed(): void
    {
        $user = $this->createTestUser([], ['editor']);
        AuthMiddleware::setAuthenticated($user);

        // Should not throw — editor is in the allowed list
        AuthMiddleware::requireRole('admin', 'editor');
        $this->assertTrue(true);
    }

    public function testRequireRolePassesWhenUserHasAnyMatchingRole(): void
    {
        $user = $this->createTestUser([], ['editor', 'viewer']);
        AuthMiddleware::setAuthenticated($user);

        // User has editor, which matches
        AuthMiddleware::requireRole('editor');
        $this->assertTrue(true);
    }

    public function testRequirePermissionAdminBypass(): void
    {
        $user = $this->createTestUser([], ['admin']);
        AuthMiddleware::setAuthenticated($user);

        // Admin should have all permissions
        AuthMiddleware::requirePermission('users.delete');
        $this->assertTrue(true);
    }

    public function testRequirePermissionThrowsWhenLacking(): void
    {
        $user = $this->createTestUser([], ['viewer']);
        AuthMiddleware::setAuthenticated($user);

        $this->expectException(ExitException::class);
        AuthMiddleware::requirePermission('users.edit');
    }

    public function testUserReturnsNullWhenNotAuthenticated(): void
    {
        $this->assertNull(AuthMiddleware::user());
    }

    public function testUserReturnsDataAfterAuth(): void
    {
        $user = $this->createTestUser([
            'username' => 'userdata',
            'name' => 'User Data',
        ], ['editor']);
        AuthMiddleware::setAuthenticated($user);

        $userData = AuthMiddleware::user();

        $this->assertNotNull($userData);
        $this->assertSame((int) $user['id'], $userData['id']);
        $this->assertSame('userdata', $userData['username']);
        $this->assertIsArray($userData['roles']);
        $this->assertContains('editor', $userData['roles']);
        $this->assertSame('User Data', $userData['name']);
    }
}
