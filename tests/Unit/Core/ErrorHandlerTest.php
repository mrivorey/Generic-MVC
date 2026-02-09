<?php

namespace Tests\Unit\Core;

use App\Core\ErrorHandler;
use App\Core\ExitException;
use App\Core\FileSystem;
use App\Core\Logger;
use App\Exceptions\AuthorizationException;
use App\Exceptions\HttpException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use Tests\TestCase;

class ErrorHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Logger::setConfig([
            'default_channel' => 'app',
            'min_level' => 'debug',
            'channels' => [],
            'timezone' => 'UTC',
        ]);
    }

    private function configureHandler(bool $debug = false): void
    {
        ErrorHandler::configure([
            'debug' => $debug,
            'paths' => ['views' => dirname(__DIR__, 3) . '/src/Views'],
        ]);
    }

    public function testHandleExceptionSets404ForNotFoundException(): void
    {
        $this->configureHandler();
        $_SERVER['REQUEST_URI'] = '/nonexistent';

        $this->expectOutputRegex('/404.*Page Not Found/s');
        ErrorHandler::handleException(new NotFoundException());
    }

    public function testHandleExceptionSets403ForAuthorizationException(): void
    {
        $this->configureHandler();
        $_SERVER['REQUEST_URI'] = '/admin/users';

        $this->expectOutputRegex('/403.*Access Denied/s');
        ErrorHandler::handleException(new AuthorizationException());
    }

    public function testHandleExceptionRenders500ForGenericException(): void
    {
        $this->configureHandler();
        $_SERVER['REQUEST_URI'] = '/';

        $this->expectOutputRegex('/500.*Server Error/s');
        ErrorHandler::handleException(new \RuntimeException('Something broke'));
    }

    public function testHandleExceptionRendersDevPageInDebugMode(): void
    {
        $this->configureHandler(true);
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->expectOutputRegex('/RuntimeException.*Debug error message.*Stack Trace/s');
        ErrorHandler::handleException(new \RuntimeException('Debug error message'));
    }

    public function testApiRequestRendersJson(): void
    {
        $this->configureHandler();
        $_SERVER['REQUEST_URI'] = '/api/v1/users';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->expectOutputRegex('/Resource not found/');
        ErrorHandler::handleException(new NotFoundException('Resource not found'));
    }

    public function testApiRequestHidesMessageFor500InProduction(): void
    {
        $this->configureHandler();
        $_SERVER['REQUEST_URI'] = '/api/v1/users';

        $this->expectOutputRegex('/Internal server error/');
        ErrorHandler::handleException(new \RuntimeException('Database password leaked'));
    }

    public function testApiRequestShowsDebugInfoInDebugMode(): void
    {
        $this->configureHandler(true);
        $_SERVER['REQUEST_URI'] = '/api/v1/users';

        $this->expectOutputRegex('/Detailed error.*RuntimeException/s');
        ErrorHandler::handleException(new \RuntimeException('Detailed error'));
    }

    #[WithoutErrorHandler]
    public function testHandleErrorConvertsWarningToException(): void
    {
        $thrown = null;
        try {
            ErrorHandler::handleError(E_WARNING, 'Test warning', __FILE__, __LINE__);
        } catch (\ErrorException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertSame('Test warning', $thrown->getMessage());
        $this->assertSame(E_WARNING, $thrown->getSeverity());
    }

    public function testHandleErrorRespectsErrorSuppression(): void
    {
        $originalReporting = error_reporting(0);

        try {
            $result = ErrorHandler::handleError(E_WARNING, 'Suppressed warning', __FILE__, __LINE__);
            $this->assertFalse($result);
        } finally {
            error_reporting($originalReporting);
        }
    }

    public function testValidationExceptionStoresSessionData(): void
    {
        $this->configureHandler();

        $errors = ['name' => ['Name is required']];
        $oldInput = ['email' => 'test@example.com'];
        $exception = new ValidationException($errors, '/form', $oldInput);

        try {
            ErrorHandler::handleException($exception);
        } catch (ExitException) {
            // Expected
        }

        $this->assertSame($errors, $_SESSION['_validation_errors']);
        $this->assertSame('test@example.com', $_SESSION['_old_input']['email']);
    }

    public function testValidationExceptionUsesRefererWhenNoRedirectUrl(): void
    {
        $this->configureHandler();

        $_SERVER['HTTP_REFERER'] = '/original-form';
        $exception = new ValidationException(['field' => ['Required']], '', []);

        try {
            ErrorHandler::handleException($exception);
        } catch (ExitException) {
            // Expected
        }

        $this->assertSame(['field' => ['Required']], $_SESSION['_validation_errors']);
    }

    public function testLogs404AsNotice(): void
    {
        $this->configureHandler();
        $_SERVER['REQUEST_URI'] = '/nonexistent';

        $this->expectOutputRegex('/404/');
        ErrorHandler::handleException(new NotFoundException());

        $logContent = FileSystem::read('logs/error.log');
        $this->assertNotNull($logContent);
        $this->assertStringContainsString('NOTICE', $logContent);
        $this->assertStringContainsString('Page not found', $logContent);
    }

    public function testLogs403AsWarning(): void
    {
        $this->configureHandler();
        $_SERVER['REQUEST_URI'] = '/admin';

        $this->expectOutputRegex('/403/');
        ErrorHandler::handleException(new AuthorizationException());

        $logContent = FileSystem::read('logs/error.log');
        $this->assertNotNull($logContent);
        $this->assertStringContainsString('WARNING', $logContent);
        $this->assertStringContainsString('Access denied', $logContent);
    }

    public function testLogs500AsError(): void
    {
        $this->configureHandler();
        $_SERVER['REQUEST_URI'] = '/';

        $this->expectOutputRegex('/500/');
        ErrorHandler::handleException(new \RuntimeException('Server failure'));

        $logContent = FileSystem::read('logs/error.log');
        $this->assertNotNull($logContent);
        $this->assertStringContainsString('ERROR', $logContent);
        $this->assertStringContainsString('Server failure', $logContent);
    }

    public function testHttpExceptionUsesCustomStatusCode(): void
    {
        $this->configureHandler();
        $_SERVER['REQUEST_URI'] = '/';

        $this->expectOutputRegex('/418/');
        ErrorHandler::handleException(new HttpException(418, 'I am a teapot'));
    }

    public function testProductionPageDoesNotLeakDetails(): void
    {
        $this->configureHandler();
        $_SERVER['REQUEST_URI'] = '/';

        $this->expectOutputRegex('/500/');
        ErrorHandler::handleException(new \RuntimeException('Secret database password'));
    }
}
