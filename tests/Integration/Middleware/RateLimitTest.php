<?php

namespace Tests\Integration\Middleware;

use App\Middleware\RateLimitMiddleware;
use Tests\DatabaseTestCase;

class RateLimitTest extends DatabaseTestCase
{
    private string $testIp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testIp = '10.0.0.' . rand(1, 254);
        $_SERVER['REMOTE_ADDR'] = $this->testIp;
    }

    public function testCheckAllowedInitially(): void
    {
        $result = RateLimitMiddleware::check($this->testIp);

        $this->assertTrue($result['allowed']);
        $this->assertSame(3, $result['remaining']); // default max_attempts
        $this->assertNull($result['retry_after']);
    }

    public function testRecordAttemptDecrementsRemaining(): void
    {
        RateLimitMiddleware::recordAttempt($this->testIp);
        $result = RateLimitMiddleware::check($this->testIp);

        $this->assertTrue($result['allowed']);
        $this->assertSame(2, $result['remaining']);
    }

    public function testLockoutAfterMaxAttempts(): void
    {
        // Record 3 attempts (default max)
        RateLimitMiddleware::recordAttempt($this->testIp);
        RateLimitMiddleware::recordAttempt($this->testIp);
        RateLimitMiddleware::recordAttempt($this->testIp);

        $result = RateLimitMiddleware::check($this->testIp);

        $this->assertFalse($result['allowed']);
        $this->assertSame(0, $result['remaining']);
        $this->assertNotNull($result['retry_after']);
    }

    public function testProgressiveLockoutDoubles(): void
    {
        // First lockout
        RateLimitMiddleware::recordAttempt($this->testIp);
        RateLimitMiddleware::recordAttempt($this->testIp);
        RateLimitMiddleware::recordAttempt($this->testIp);

        $firstCheck = RateLimitMiddleware::check($this->testIp);
        $firstRetry = $firstCheck['retry_after'];

        // Manually expire the lockout for testing
        $pdo = self::$pdo;
        $pdo->prepare('UPDATE rate_limits SET lockout_until = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE ip_address = ?')
            ->execute([$this->testIp]);

        // Second round of attempts
        RateLimitMiddleware::recordAttempt($this->testIp);
        RateLimitMiddleware::recordAttempt($this->testIp);
        RateLimitMiddleware::recordAttempt($this->testIp);

        $secondCheck = RateLimitMiddleware::check($this->testIp);
        $secondRetry = $secondCheck['retry_after'];

        // Second lockout should be longer (doubled)
        $this->assertGreaterThan($firstRetry, $secondRetry);
    }

    public function testClearResets(): void
    {
        RateLimitMiddleware::recordAttempt($this->testIp);
        RateLimitMiddleware::recordAttempt($this->testIp);
        RateLimitMiddleware::clear($this->testIp);

        $result = RateLimitMiddleware::check($this->testIp);

        $this->assertTrue($result['allowed']);
        $this->assertSame(3, $result['remaining']);
    }

    public function testCheckWithDisabledConfig(): void
    {
        // Override config to disabled
        $ref = new \ReflectionProperty(RateLimitMiddleware::class, 'config');
        $ref->setValue(null, [
            'enabled' => false,
            'max_attempts' => 3,
            'lockout_minutes' => 30,
            'progressive' => true,
            'max_lockout_minutes' => 1440,
            'attempt_window' => 900,
        ]);

        $result = RateLimitMiddleware::check($this->testIp);

        $this->assertTrue($result['allowed']);

        // Restore
        RateLimitMiddleware::resetConfig();
    }

    public function testIsLocked(): void
    {
        $this->assertFalse(RateLimitMiddleware::isLocked($this->testIp));

        RateLimitMiddleware::recordAttempt($this->testIp);
        RateLimitMiddleware::recordAttempt($this->testIp);
        RateLimitMiddleware::recordAttempt($this->testIp);

        $this->assertTrue(RateLimitMiddleware::isLocked($this->testIp));
    }

    public function testMultipleAttemptsWithinWindow(): void
    {
        RateLimitMiddleware::recordAttempt($this->testIp);
        $result1 = RateLimitMiddleware::check($this->testIp);

        RateLimitMiddleware::recordAttempt($this->testIp);
        $result2 = RateLimitMiddleware::check($this->testIp);

        $this->assertGreaterThan($result2['remaining'], $result1['remaining']);
    }

    public function testRecordAttemptNoOpWhenDisabled(): void
    {
        $ref = new \ReflectionProperty(RateLimitMiddleware::class, 'config');
        $ref->setValue(null, [
            'enabled' => false,
            'max_attempts' => 3,
            'lockout_minutes' => 30,
            'progressive' => true,
            'max_lockout_minutes' => 1440,
            'attempt_window' => 900,
        ]);

        RateLimitMiddleware::recordAttempt($this->testIp);

        // Should still show as allowed with full remaining
        $result = RateLimitMiddleware::check($this->testIp);
        $this->assertTrue($result['allowed']);

        RateLimitMiddleware::resetConfig();
    }
}
