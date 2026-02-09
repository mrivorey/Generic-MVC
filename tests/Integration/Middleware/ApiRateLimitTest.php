<?php

namespace Tests\Integration\Middleware;

use App\Core\ExitException;
use App\Middleware\ApiRateLimitMiddleware;
use Tests\DatabaseTestCase;

class ApiRateLimitTest extends DatabaseTestCase
{
    public function testCheckPassesUnderLimit(): void
    {
        $user = $this->createTestUser();
        $_REQUEST['_api_user'] = [
            'key_id' => 1,
            'user_id' => $user['id'],
            'username' => $user['username'],
        ];

        // Should not throw
        ApiRateLimitMiddleware::check();
        $this->assertTrue(true);
    }

    public function testCheckThrowsExitExceptionAtLimit(): void
    {
        $user = $this->createTestUser();
        $keyId = 99999;
        $_REQUEST['_api_user'] = [
            'key_id' => $keyId,
            'user_id' => $user['id'],
            'username' => $user['username'],
        ];

        // Insert a rate_limits entry that's already at the limit
        $attempts = array_fill(0, 60, time()); // 60 attempts in current window
        $attemptsJson = json_encode($attempts);
        self::$pdo->prepare(
            'INSERT INTO rate_limits (ip_address, attempts) VALUES (?, ?)'
        )->execute(["api:{$keyId}", $attemptsJson]);

        $this->expectException(ExitException::class);
        ApiRateLimitMiddleware::check();
    }

    public function testCheckWithNoApiUserDoesNothing(): void
    {
        unset($_REQUEST['_api_user']);

        // Should silently return
        ApiRateLimitMiddleware::check();
        $this->assertTrue(true);
    }

    public function testCheckRecordsAttempt(): void
    {
        $user = $this->createTestUser();
        $keyId = 88888;
        $_REQUEST['_api_user'] = [
            'key_id' => $keyId,
            'user_id' => $user['id'],
            'username' => $user['username'],
        ];

        ApiRateLimitMiddleware::check();

        // Verify an entry was created
        $stmt = self::$pdo->prepare('SELECT attempts FROM rate_limits WHERE ip_address = ?');
        $stmt->execute(["api:{$keyId}"]);
        $row = $stmt->fetch();

        $this->assertNotFalse($row);
        $attempts = json_decode($row['attempts'], true);
        $this->assertCount(1, $attempts);
    }
}
