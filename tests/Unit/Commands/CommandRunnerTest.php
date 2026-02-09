<?php

namespace Tests\Unit\Commands;

use App\Commands\CommandRunner;
use App\Core\Cache;
use App\Core\FileCacheDriver;
use Tests\TestCase;

class CommandRunnerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up a temp cache dir so cache:clear works without touching real storage
        $this->tempDir = sys_get_temp_dir() . '/cli_cache_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        Cache::setDriver(new FileCacheDriver($this->tempDir));
    }

    protected function tearDown(): void
    {
        Cache::reset();
        $this->removeDir($this->tempDir);
        parent::tearDown();
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function test_no_args_shows_help(): void
    {
        $runner = new CommandRunner();

        ob_start();
        $code = $runner->run([]);
        $output = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Available commands:', $output);
    }

    public function test_unknown_command_returns_one(): void
    {
        $runner = new CommandRunner();

        ob_start();
        $code = $runner->run(['nonexistent']);
        ob_end_clean();

        $this->assertSame(1, $code);
    }

    public function test_discovers_built_in_commands(): void
    {
        $runner = new CommandRunner();
        $commands = $runner->getCommands();

        $this->assertArrayHasKey('migrate', $commands);
        $this->assertArrayHasKey('cache:clear', $commands);
        $this->assertArrayHasKey('password:reset', $commands);
    }

    public function test_runs_valid_command(): void
    {
        $runner = new CommandRunner();

        ob_start();
        $code = $runner->run(['cache:clear']);
        ob_end_clean();

        $this->assertSame(0, $code);
    }
}
