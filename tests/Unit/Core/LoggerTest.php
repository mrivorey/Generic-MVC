<?php

namespace Tests\Unit\Core;

use App\Core\FileSystem;
use App\Core\Logger;
use App\Core\LogChannel;
use Tests\TestCase;

class LoggerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/logger_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        FileSystem::setStorageRoot($this->tempDir);

        Logger::setConfig([
            'default_channel' => 'app',
            'min_level' => 'debug',
            'channels' => [],
            'timezone' => 'UTC',
        ]);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
        parent::tearDown();
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }

    private function readLog(string $channel = 'app'): string
    {
        return FileSystem::read("logs/{$channel}.log");
    }

    public function test_logs_to_default_channel_file(): void
    {
        Logger::info('Application started');

        $log = $this->readLog();
        $this->assertStringContainsString('app.INFO: Application started', $log);
    }

    public function test_named_channel_writes_to_separate_file(): void
    {
        Logger::channel('auth')->info('Login succeeded');

        $log = $this->readLog('auth');
        $this->assertStringContainsString('auth.INFO: Login succeeded', $log);
        $this->assertFalse(FileSystem::exists('logs/app.log'));
    }

    public function test_all_psr3_levels_produce_correct_output(): void
    {
        $levels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

        foreach ($levels as $level) {
            Logger::$level("Test {$level}");
        }

        $log = $this->readLog();

        foreach ($levels as $level) {
            $upper = strtoupper($level);
            $this->assertStringContainsString("app.{$upper}: Test {$level}", $log);
        }
    }

    public function test_context_serialized_as_json(): void
    {
        Logger::info('User login', ['username' => 'admin', 'ip' => '192.168.1.1']);

        $log = $this->readLog();
        $this->assertStringContainsString('{"username":"admin","ip":"192.168.1.1"}', $log);
    }

    public function test_context_omitted_when_empty(): void
    {
        Logger::info('Simple message');

        $log = $this->readLog();
        $this->assertMatchesRegularExpression('/app\.INFO: Simple message\n$/', $log);
    }

    public function test_level_filtering_skips_below_minimum(): void
    {
        Logger::setConfig([
            'default_channel' => 'app',
            'min_level' => 'warning',
            'channels' => [],
            'timezone' => 'UTC',
        ]);

        Logger::debug('Should be skipped');
        Logger::info('Also skipped');
        Logger::warning('Should appear');

        $log = $this->readLog();
        $this->assertStringNotContainsString('Should be skipped', $log);
        $this->assertStringNotContainsString('Also skipped', $log);
        $this->assertStringContainsString('Should appear', $log);
    }

    public function test_channel_specific_min_level_overrides_global(): void
    {
        Logger::setConfig([
            'default_channel' => 'app',
            'min_level' => 'debug',
            'channels' => [
                'auth' => ['min_level' => 'error'],
            ],
            'timezone' => 'UTC',
        ]);

        Logger::channel('auth')->info('Should be skipped');
        Logger::channel('auth')->error('Should appear');

        $log = $this->readLog('auth');
        $this->assertStringNotContainsString('Should be skipped', $log);
        $this->assertStringContainsString('Should appear', $log);
    }

    public function test_channel_returns_cached_instance(): void
    {
        $first = Logger::channel('auth');
        $second = Logger::channel('auth');

        $this->assertSame($first, $second);
    }

    public function test_channel_returns_log_channel_instance(): void
    {
        $channel = Logger::channel('auth');

        $this->assertInstanceOf(LogChannel::class, $channel);
    }

    public function test_reset_clears_state(): void
    {
        $before = Logger::channel('test');
        Logger::reset();

        Logger::setConfig([
            'default_channel' => 'app',
            'min_level' => 'debug',
            'channels' => [],
            'timezone' => 'UTC',
        ]);

        $after = Logger::channel('test');
        $this->assertNotSame($before, $after);
    }

    public function test_log_line_format_matches_expected_pattern(): void
    {
        Logger::info('Test message', ['key' => 'value']);

        $log = $this->readLog();
        $this->assertMatchesRegularExpression(
            '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] app\.INFO: Test message \{"key":"value"\}\n$/',
            $log
        );
    }

    public function test_timestamp_format(): void
    {
        Logger::info('Timestamp test');

        $log = $this->readLog();
        $this->assertMatchesRegularExpression('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $log);
    }

    public function test_multiple_log_entries_append(): void
    {
        Logger::info('First');
        Logger::info('Second');
        Logger::info('Third');

        $log = $this->readLog();
        $lines = array_filter(explode("\n", $log));

        $this->assertCount(3, $lines);
    }

    public function test_unconfigured_channel_uses_global_min_level(): void
    {
        Logger::setConfig([
            'default_channel' => 'app',
            'min_level' => 'error',
            'channels' => [],
            'timezone' => 'UTC',
        ]);

        Logger::channel('custom')->info('Should be skipped');

        $this->assertFalse(FileSystem::exists('logs/custom.log'));
    }

    public function test_slashes_not_escaped_in_context(): void
    {
        Logger::info('Path test', ['url' => '/api/v1/users']);

        $log = $this->readLog();
        $this->assertStringContainsString('{"url":"/api/v1/users"}', $log);
    }
}
