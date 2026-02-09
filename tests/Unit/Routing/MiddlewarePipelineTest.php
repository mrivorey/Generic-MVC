<?php

namespace Tests\Unit\Routing;

use App\Routing\MiddlewarePipeline;
use Tests\TestCase;

class MiddlewarePipelineTest extends TestCase
{
    private static bool $handlerCalled = false;
    private static ?string $handlerParam = null;
    private static array $executionOrder = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::$handlerCalled = false;
        self::$handlerParam = null;
        self::$executionOrder = [];
    }

    public static function dummyHandler(): void
    {
        self::$handlerCalled = true;
    }

    public static function paramHandler(string $param): void
    {
        self::$handlerParam = $param;
    }

    public static function firstHandler(): void
    {
        self::$executionOrder[] = 'first';
    }

    public static function secondHandler(): void
    {
        self::$executionOrder[] = 'second';
    }

    public function testRunCallsRegisteredHandler(): void
    {
        MiddlewarePipeline::register('test_dummy', [self::class, 'dummyHandler']);
        MiddlewarePipeline::run(['test_dummy']);

        $this->assertTrue(self::$handlerCalled);
    }

    public function testRunPassesColonSeparatedParameters(): void
    {
        MiddlewarePipeline::register('test_param', [self::class, 'paramHandler']);
        MiddlewarePipeline::run(['test_param:admin']);

        $this->assertSame('admin', self::$handlerParam);
    }

    public function testRunThrowsForUnknownAlias(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown middleware alias: nonexistent_mw');

        MiddlewarePipeline::run(['nonexistent_mw']);
    }

    public function testRegisterAddsNewAlias(): void
    {
        MiddlewarePipeline::register('test_new', [self::class, 'dummyHandler']);
        MiddlewarePipeline::run(['test_new']);

        $this->assertTrue(self::$handlerCalled);
    }

    public function testMultipleMiddlewareRunInOrder(): void
    {
        MiddlewarePipeline::register('test_first', [self::class, 'firstHandler']);
        MiddlewarePipeline::register('test_second', [self::class, 'secondHandler']);

        MiddlewarePipeline::run(['test_first', 'test_second']);

        $this->assertSame(['first', 'second'], self::$executionOrder);
    }
}
