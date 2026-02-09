<?php

namespace Tests;

use App\Core\FileSystem;
use App\Core\FormBuilder;
use App\Core\Flash;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Routing\Router;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private array $originalSession = [];
    private array $originalServer = [];
    private array $originalPost = [];
    private array $originalRequest = [];
    private array $originalCookie = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Save originals
        $this->originalSession = $_SESSION ?? [];
        $this->originalServer = $_SERVER;
        $this->originalPost = $_POST;
        $this->originalRequest = $_REQUEST;
        $this->originalCookie = $_COOKIE;

        // Clean slate
        $_SESSION = [];
        $_POST = [];
        $_REQUEST = [];
        $_COOKIE = [];

        // Set defaults
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        // Reset static caches
        Router::reset();
        FileSystem::reset();
        FormBuilder::resetState();
        CsrfMiddleware::resetConfig();
        RateLimitMiddleware::resetConfig();
    }

    protected function tearDown(): void
    {
        // Restore originals
        $_SESSION = $this->originalSession;
        $_SERVER = $this->originalServer;
        $_POST = $this->originalPost;
        $_REQUEST = $this->originalRequest;
        $_COOKIE = $this->originalCookie;

        parent::tearDown();
    }
}
