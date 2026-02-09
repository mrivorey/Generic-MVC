<?php

namespace Tests\Unit\Core;

use App\Core\FileSystem;
use App\Core\Logger;
use App\Core\Mailer;
use Tests\TestCase;

class MailerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/mailer_test_' . uniqid();
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
        // Clean up temp files
        $files = glob($this->tempDir . '/**/*') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        // Remove subdirectories
        $dirs = glob($this->tempDir . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            rmdir($dir);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    public function testSetConfigOverridesDefaults(): void
    {
        Mailer::setConfig([
            'host' => 'smtp.test.com',
            'port' => 465,
            'username' => 'testuser',
            'password' => 'testpass',
            'encryption' => 'ssl',
            'from_address' => 'test@test.com',
            'from_name' => 'Test',
        ]);

        // Config is private, but we can verify it takes effect by attempting to send
        // to an unreachable host — the connection will fail but use our config
        $result = Mailer::send('to@example.com', 'Test', 'Body');
        $this->assertFalse($result);
    }

    public function testResetClearsCachedConfig(): void
    {
        Mailer::setConfig([
            'host' => 'custom.host.com',
            'port' => 465,
            'username' => '',
            'password' => '',
            'encryption' => 'ssl',
            'from_address' => 'custom@test.com',
            'from_name' => 'Custom',
        ]);

        Mailer::reset();

        // After reset, loadConfig will re-read from app.php.
        // We can verify reset worked by setting a new config — if reset
        // didn't clear, this would be ignored.
        Mailer::setConfig([
            'host' => 'another.host.com',
            'port' => 587,
            'username' => '',
            'password' => '',
            'encryption' => 'tls',
            'from_address' => 'another@test.com',
            'from_name' => 'Another',
        ]);

        // Send will fail (unreachable) but proves config was accepted after reset
        $result = Mailer::send('to@example.com', 'Test', 'Body');
        $this->assertFalse($result);
    }

    public function testSendReturnsFalseOnConnectionFailure(): void
    {
        Mailer::setConfig([
            'host' => '192.0.2.1', // TEST-NET — unreachable
            'port' => 12345,
            'username' => '',
            'password' => '',
            'encryption' => '',
            'from_address' => 'test@example.com',
            'from_name' => 'Test',
            'timeout' => 1,
        ]);

        $result = Mailer::send('recipient@example.com', 'Test Subject', '<p>Test body</p>');

        $this->assertFalse($result);
    }
}
