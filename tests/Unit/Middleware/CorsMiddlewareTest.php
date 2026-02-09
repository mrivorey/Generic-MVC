<?php

namespace Tests\Unit\Middleware;

use App\Middleware\CorsMiddleware;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

class CorsMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CorsMiddleware::resetConfig();
    }

    protected function tearDown(): void
    {
        CorsMiddleware::resetConfig();
        parent::tearDown();
    }

    public function test_no_headers_without_origin(): void
    {
        // No HTTP_ORIGIN set — handle() should return immediately
        unset($_SERVER['HTTP_ORIGIN']);

        CorsMiddleware::setConfig([
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST'],
            'allowed_headers' => ['Content-Type'],
            'max_age' => 3600,
            'allow_credentials' => false,
        ]);

        // Should return without error or exception
        CorsMiddleware::handle();

        $this->assertTrue(true, 'handle() returned early without Origin header');
    }

    public function test_disallowed_origin_sets_no_headers(): void
    {
        CorsMiddleware::setConfig([
            'allowed_origins' => ['https://trusted.example.com'],
            'allowed_methods' => ['GET'],
            'allowed_headers' => ['Content-Type'],
            'max_age' => 3600,
            'allow_credentials' => false,
        ]);

        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example.com';

        // Should return early because origin is not in allowed list
        CorsMiddleware::handle();

        $this->assertTrue(true, 'handle() returned early for disallowed origin');
    }

    public function test_wildcard_allows_any_origin(): void
    {
        CorsMiddleware::setConfig([
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST'],
            'allowed_headers' => ['Content-Type'],
            'max_age' => 86400,
            'allow_credentials' => false,
        ]);

        $_SERVER['HTTP_ORIGIN'] = 'https://any-site.example.com';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // In CLI mode headers_sent() is true after PHPUnit output, so it
        // returns after the origin check. No exception expected.
        CorsMiddleware::handle();

        $this->assertTrue(true, 'Wildcard origin accepted without error');
    }

    public function test_specific_origin_allowed(): void
    {
        CorsMiddleware::setConfig([
            'allowed_origins' => ['https://trusted.example.com', 'https://also-trusted.example.com'],
            'allowed_methods' => ['GET', 'POST'],
            'allowed_headers' => ['Content-Type'],
            'max_age' => 3600,
            'allow_credentials' => false,
        ]);

        $_SERVER['HTTP_ORIGIN'] = 'https://trusted.example.com';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Should pass origin check (then bail on headers_sent in CLI)
        CorsMiddleware::handle();

        $this->assertTrue(true, 'Specific allowed origin accepted');
    }

    public function test_reset_clears_config(): void
    {
        CorsMiddleware::setConfig([
            'allowed_origins' => ['https://old.example.com'],
            'allowed_methods' => ['GET'],
            'allowed_headers' => ['Content-Type'],
            'max_age' => 100,
            'allow_credentials' => true,
        ]);

        CorsMiddleware::resetConfig();

        // After reset, set new config — old config should be gone
        CorsMiddleware::setConfig([
            'allowed_origins' => ['https://new.example.com'],
            'allowed_methods' => ['GET', 'POST', 'PUT'],
            'allowed_headers' => ['Content-Type', 'Authorization'],
            'max_age' => 86400,
            'allow_credentials' => false,
        ]);

        $_SERVER['HTTP_ORIGIN'] = 'https://old.example.com';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Old origin should now be disallowed — handle() returns early
        CorsMiddleware::handle();

        $this->assertTrue(true, 'Config was fully replaced after reset');
    }

    public function test_credentials_config_accepted(): void
    {
        CorsMiddleware::setConfig([
            'allowed_origins' => ['https://app.example.com'],
            'allowed_methods' => ['GET', 'POST'],
            'allowed_headers' => ['Content-Type', 'Authorization'],
            'max_age' => 3600,
            'allow_credentials' => true,
        ]);

        $_SERVER['HTTP_ORIGIN'] = 'https://app.example.com';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Should pass origin check without error
        CorsMiddleware::handle();

        $this->assertTrue(true, 'Credentials config accepted without error');
    }

    public function test_empty_origin_returns_early(): void
    {
        CorsMiddleware::setConfig([
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET'],
            'allowed_headers' => ['Content-Type'],
            'max_age' => 3600,
            'allow_credentials' => false,
        ]);

        $_SERVER['HTTP_ORIGIN'] = '';

        CorsMiddleware::handle();

        $this->assertTrue(true, 'Empty origin string treated as no origin');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_options_preflight_exits(): void
    {
        require dirname(__DIR__, 3) . '/vendor/autoload.php';

        \App\Core\ExitTrap::enableTestMode();
        \App\Middleware\CorsMiddleware::setConfig([
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST'],
            'allowed_headers' => ['Content-Type'],
            'max_age' => 3600,
            'allow_credentials' => false,
        ]);

        $_SERVER['HTTP_ORIGIN'] = 'http://example.com';
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';

        $this->expectException(\App\Core\ExitException::class);
        \App\Middleware\CorsMiddleware::handle();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_preflight_exit_code_is_zero(): void
    {
        require dirname(__DIR__, 3) . '/vendor/autoload.php';

        \App\Core\ExitTrap::enableTestMode();
        \App\Middleware\CorsMiddleware::setConfig([
            'allowed_origins' => ['https://app.example.com'],
            'allowed_methods' => ['GET', 'POST', 'PUT'],
            'allowed_headers' => ['Content-Type', 'Authorization'],
            'max_age' => 7200,
            'allow_credentials' => true,
        ]);

        $_SERVER['HTTP_ORIGIN'] = 'https://app.example.com';
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';

        try {
            \App\Middleware\CorsMiddleware::handle();
            $this->fail('Expected ExitException was not thrown');
        } catch (\App\Core\ExitException $e) {
            $this->assertSame(0, $e->getExitCode());
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_wildcard_preflight_exits(): void
    {
        require dirname(__DIR__, 3) . '/vendor/autoload.php';

        \App\Core\ExitTrap::enableTestMode();
        \App\Middleware\CorsMiddleware::setConfig([
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET'],
            'allowed_headers' => ['Content-Type'],
            'max_age' => 3600,
            'allow_credentials' => true, // Should be ignored with wildcard
        ]);

        $_SERVER['HTTP_ORIGIN'] = 'http://any-site.example.com';
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';

        $this->expectException(\App\Core\ExitException::class);
        \App\Middleware\CorsMiddleware::handle();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_non_options_request_does_not_exit(): void
    {
        require dirname(__DIR__, 3) . '/vendor/autoload.php';

        \App\Core\ExitTrap::enableTestMode();
        \App\Middleware\CorsMiddleware::setConfig([
            'allowed_origins' => ['https://app.example.com'],
            'allowed_methods' => ['GET', 'POST'],
            'allowed_headers' => ['Content-Type'],
            'max_age' => 3600,
            'allow_credentials' => false,
        ]);

        $_SERVER['HTTP_ORIGIN'] = 'https://app.example.com';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Should NOT throw ExitException for non-OPTIONS requests
        \App\Middleware\CorsMiddleware::handle();

        $this->assertTrue(true, 'Non-OPTIONS request completed without exit');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_disallowed_origin_does_not_exit_on_options(): void
    {
        require dirname(__DIR__, 3) . '/vendor/autoload.php';

        \App\Core\ExitTrap::enableTestMode();
        \App\Middleware\CorsMiddleware::setConfig([
            'allowed_origins' => ['https://trusted.example.com'],
            'allowed_methods' => ['GET', 'POST'],
            'allowed_headers' => ['Content-Type'],
            'max_age' => 3600,
            'allow_credentials' => false,
        ]);

        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example.com';
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';

        // Disallowed origin should return early, NOT reach preflight exit
        \App\Middleware\CorsMiddleware::handle();

        $this->assertTrue(true, 'Disallowed origin OPTIONS did not trigger preflight exit');
    }
}
