<?php

namespace Tests\Integration\Middleware;

use App\Core\ExitException;
use App\Middleware\CsrfMiddleware;
use Tests\TestCase;

class CsrfTest extends TestCase
{
    public function testTokenGeneratesAndCaches(): void
    {
        $token = CsrfMiddleware::token();

        $this->assertIsString($token);
        $this->assertSame(64, strlen($token)); // 32 bytes = 64 hex
    }

    public function testTokenReturnsSameOnSubsequentCalls(): void
    {
        $token1 = CsrfMiddleware::token();
        $token2 = CsrfMiddleware::token();

        $this->assertSame($token1, $token2);
    }

    public function testFieldReturnsHiddenInput(): void
    {
        $html = CsrfMiddleware::field();

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="_csrf_token"', $html);
        $this->assertStringContainsString('value="', $html);
    }

    public function testValidatePassesWithMatchingTokens(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $token = CsrfMiddleware::token();
        $_POST['_csrf_token'] = $token;

        $this->assertTrue(CsrfMiddleware::validate());
    }

    public function testValidateFailsWithMismatchedTokens(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        CsrfMiddleware::token(); // generate session token
        $_POST['_csrf_token'] = 'wrong-token';

        $this->assertFalse(CsrfMiddleware::validate());
    }

    public function testValidateFailsWithMissingSessionToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = 'some-token';
        // No session token set

        $this->assertFalse(CsrfMiddleware::validate());
    }

    public function testValidateSkipsSafeMethods(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->assertTrue(CsrfMiddleware::validate());
    }

    public function testVerifyThrowsExitExceptionOnFailure(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = 'invalid';

        $this->expectException(ExitException::class);
        CsrfMiddleware::verify();
    }

    public function testRegenerateCreatesNewToken(): void
    {
        $oldToken = CsrfMiddleware::token();
        $newToken = CsrfMiddleware::regenerate();

        $this->assertNotSame($oldToken, $newToken);
        $this->assertSame(64, strlen($newToken));
    }

    public function testClearRemovesToken(): void
    {
        CsrfMiddleware::token();
        CsrfMiddleware::clear();

        // Next call should generate a new token
        $this->assertArrayNotHasKey('_csrf_token', $_SESSION);
    }
}
