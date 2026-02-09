<?php

namespace Tests\Integration\Middleware;

use App\Core\ExitException;
use App\Middleware\ApiAuthMiddleware;
use App\Models\ApiKey;
use Tests\DatabaseTestCase;

class ApiAuthMiddlewareTest extends DatabaseTestCase
{
    public function testVerifySucceedsWithValidBearerToken(): void
    {
        $user = $this->createTestUser();
        $rawKey = ApiKey::generate($user['id'], 'Test Key');

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $rawKey;

        ApiAuthMiddleware::verify();

        $this->assertArrayHasKey('_api_user', $_REQUEST);
        $this->assertSame((int) $user['id'], (int) $_REQUEST['_api_user']['user_id']);
    }

    public function testVerifyThrowsWithMissingHeader(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $this->expectException(ExitException::class);
        ApiAuthMiddleware::verify();
    }

    public function testVerifyThrowsWithInvalidKey(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer app_invalid_key_' . bin2hex(random_bytes(16));

        $this->expectException(ExitException::class);
        ApiAuthMiddleware::verify();
    }

    public function testVerifyStoresUserInRequest(): void
    {
        $user = $this->createTestUser();
        $rawKey = ApiKey::generate($user['id'], 'Store Test');

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $rawKey;

        ApiAuthMiddleware::verify();

        $apiUser = $_REQUEST['_api_user'];
        $this->assertSame($user['username'], $apiUser['username']);
        $this->assertArrayHasKey('key_id', $apiUser);
        $this->assertArrayHasKey('email', $apiUser);
    }

    public function testVerifyRejectsInactiveUsersKey(): void
    {
        $user = $this->createTestUser(['is_active' => 0]);
        $rawKey = ApiKey::generate($user['id'], 'Inactive Key');

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $rawKey;

        $this->expectException(ExitException::class);
        ApiAuthMiddleware::verify();
    }

    public function testVerifyThrowsWithBadFormat(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';

        $this->expectException(ExitException::class);
        ApiAuthMiddleware::verify();
    }
}
