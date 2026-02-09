<?php

namespace Tests\Unit\Middleware;

use App\Core\FileSystem;
use App\Core\Logger;
use App\Middleware\RequestLogMiddleware;
use Tests\TestCase;

class RequestLogMiddlewareTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a temp directory so tests don't interfere with each other
        $this->tempDir = sys_get_temp_dir() . '/request_log_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        FileSystem::setStorageRoot($this->tempDir);

        Logger::setConfig([
            'default_channel' => 'app',
            'min_level' => 'debug',
            'channels' => ['requests' => ['min_level' => 'info']],
            'timezone' => 'UTC',
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        $this->removeDir($this->tempDir);
        parent::tearDown();
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testLogsRequestMethodAndUri(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/admin/users';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        RequestLogMiddleware::start();
        RequestLogMiddleware::log();

        $logContent = FileSystem::read('logs/requests.log');
        $this->assertStringContainsString('GET /admin/users', $logContent);
        $this->assertStringContainsString('192.168.1.1', $logContent);
    }

    public function testLogsStatusCode(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/login';

        RequestLogMiddleware::start();
        RequestLogMiddleware::log();

        $logContent = FileSystem::read('logs/requests.log');
        $this->assertStringContainsString('POST /login', $logContent);
    }

    public function testLogsDurationInMs(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        RequestLogMiddleware::start();
        RequestLogMiddleware::log();

        $logContent = FileSystem::read('logs/requests.log');
        $this->assertMatchesRegularExpression('/\d+(\.\d+)?ms/', $logContent);
    }

    public function testIncludesUserIdWhenAuthenticated(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/profile';
        $_SESSION['user_id'] = 42;

        RequestLogMiddleware::start();
        RequestLogMiddleware::log();

        $logContent = FileSystem::read('logs/requests.log');
        $this->assertStringContainsString('"user_id":42', $logContent);
    }

    public function testDoesNotLogWithoutStart(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        // Call log without start — should do nothing
        RequestLogMiddleware::log();

        $this->assertFalse(FileSystem::exists('logs/requests.log'));
    }

    public function testResetClearsStartTime(): void
    {
        RequestLogMiddleware::start();
        RequestLogMiddleware::reset();
        RequestLogMiddleware::log();

        $this->assertFalse(FileSystem::exists('logs/requests.log'));
    }
}
