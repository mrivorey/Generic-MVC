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
        $this->tempDir = realpath($this->tempDir);
        FileSystem::setStorageRoot($this->tempDir);

        Logger::reset();
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

    private function readDailyLog(string $channel = 'app'): string
    {
        $date = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        return FileSystem::read("logs/{$channel}-{$date}.log");
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

    public function test_daily_rotation_creates_dated_filename(): void
    {
        Logger::setConfig([
            'default_channel' => 'app',
            'min_level' => 'debug',
            'channels' => [],
            'timezone' => 'UTC',
            'rotation' => 'daily',
            'max_files' => 14,
        ]);

        Logger::info('Daily rotation test');

        $date = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        $this->assertTrue(FileSystem::exists("logs/app-{$date}.log"));
        $log = $this->readDailyLog();
        $this->assertStringContainsString('app.INFO: Daily rotation test', $log);
    }

    public function test_single_rotation_keeps_original_filename(): void
    {
        Logger::setConfig([
            'default_channel' => 'app',
            'min_level' => 'debug',
            'channels' => [],
            'timezone' => 'UTC',
            'rotation' => 'single',
            'max_files' => 14,
        ]);

        Logger::info('Single rotation test');

        $this->assertTrue(FileSystem::exists('logs/app.log'));
        $log = $this->readLog();
        $this->assertStringContainsString('app.INFO: Single rotation test', $log);
    }

    public function test_cleanup_removes_old_files(): void
    {
        // Create the logs directory
        $logsDir = $this->tempDir . '/logs';
        mkdir($logsDir, 0755, true);

        // Create old log files (30 days ago, well beyond max_files=7)
        for ($i = 20; $i <= 30; $i++) {
            $date = (new \DateTimeImmutable("-{$i} days", new \DateTimeZone('UTC')))->format('Y-m-d');
            file_put_contents($logsDir . "/app-{$date}.log", "old log entry\n");
        }

        Logger::setConfig([
            'default_channel' => 'app',
            'min_level' => 'debug',
            'channels' => [],
            'timezone' => 'UTC',
            'rotation' => 'daily',
            'max_files' => 7,
        ]);

        // Writing a log triggers cleanup
        Logger::info('Trigger cleanup');

        // Old files (beyond 7 days) should be deleted
        for ($i = 20; $i <= 30; $i++) {
            $date = (new \DateTimeImmutable("-{$i} days", new \DateTimeZone('UTC')))->format('Y-m-d');
            $this->assertFileDoesNotExist($logsDir . "/app-{$date}.log", "File app-{$date}.log should have been deleted");
        }
    }

    public function test_cleanup_preserves_recent_files(): void
    {
        // Create the logs directory
        $logsDir = $this->tempDir . '/logs';
        mkdir($logsDir, 0755, true);

        // Create recent log files (within max_files=7 days)
        for ($i = 1; $i <= 5; $i++) {
            $date = (new \DateTimeImmutable("-{$i} days", new \DateTimeZone('UTC')))->format('Y-m-d');
            file_put_contents($logsDir . "/app-{$date}.log", "recent log entry\n");
        }

        Logger::setConfig([
            'default_channel' => 'app',
            'min_level' => 'debug',
            'channels' => [],
            'timezone' => 'UTC',
            'rotation' => 'daily',
            'max_files' => 7,
        ]);

        // Writing a log triggers cleanup
        Logger::info('Trigger cleanup');

        // Recent files should still exist
        for ($i = 1; $i <= 5; $i++) {
            $date = (new \DateTimeImmutable("-{$i} days", new \DateTimeZone('UTC')))->format('Y-m-d');
            $this->assertFileExists($logsDir . "/app-{$date}.log", "File app-{$date}.log should be preserved");
        }
    }

    public function test_cleanup_runs_once_per_request(): void
    {
        // Create the logs directory
        $logsDir = $this->tempDir . '/logs';
        mkdir($logsDir, 0755, true);

        // Create an old log file that would be cleaned up
        $oldDate = (new \DateTimeImmutable('-30 days', new \DateTimeZone('UTC')))->format('Y-m-d');
        file_put_contents($logsDir . "/app-{$oldDate}.log", "old entry\n");

        Logger::setConfig([
            'default_channel' => 'app',
            'min_level' => 'debug',
            'channels' => [],
            'timezone' => 'UTC',
            'rotation' => 'daily',
            'max_files' => 7,
        ]);

        // First write triggers cleanup - old file gets deleted
        Logger::info('First message');
        $this->assertFileDoesNotExist($logsDir . "/app-{$oldDate}.log");

        // Recreate the old file to test that cleanup does NOT run again
        file_put_contents($logsDir . "/app-{$oldDate}.log", "old entry again\n");

        // Second write should NOT trigger cleanup (flag already set)
        Logger::info('Second message');
        $this->assertFileExists($logsDir . "/app-{$oldDate}.log", 'Cleanup should not run a second time in same request');
    }
}
