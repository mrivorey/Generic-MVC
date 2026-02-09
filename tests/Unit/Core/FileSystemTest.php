<?php

namespace Tests\Unit\Core;

use App\Core\FileSystem;
use Tests\TestCase;

class FileSystemTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/fs_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        FileSystem::setStorageRoot($this->tempDir);
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

    public function test_write_and_read_round_trip(): void
    {
        mkdir($this->tempDir . '/data');

        FileSystem::write('data/hello.txt', 'world');

        $this->assertSame('world', FileSystem::read('data/hello.txt'));
    }

    public function test_write_overwrites_existing(): void
    {
        mkdir($this->tempDir . '/data');

        FileSystem::write('data/file.txt', 'first');
        FileSystem::write('data/file.txt', 'second');

        $this->assertSame('second', FileSystem::read('data/file.txt'));
    }

    public function test_write_auto_creates_nested_directories(): void
    {
        FileSystem::write('deep/nested/dir/file.txt', 'content');

        $this->assertSame('content', FileSystem::read('deep/nested/dir/file.txt'));
    }

    public function test_append_to_existing_file(): void
    {
        mkdir($this->tempDir . '/logs');

        FileSystem::write('logs/app.log', 'line1');
        FileSystem::append('logs/app.log', 'line2');

        $this->assertSame('line1line2', FileSystem::read('logs/app.log'));
    }

    public function test_append_creates_file_if_missing(): void
    {
        mkdir($this->tempDir . '/logs');

        FileSystem::append('logs/new.log', 'first line');

        $this->assertSame('first line', FileSystem::read('logs/new.log'));
    }

    public function test_append_auto_creates_directories(): void
    {
        FileSystem::append('new/dir/file.log', 'data');

        $this->assertSame('data', FileSystem::read('new/dir/file.log'));
    }

    public function test_delete_removes_file(): void
    {
        mkdir($this->tempDir . '/tmp');

        FileSystem::write('tmp/remove.txt', 'bye');
        $this->assertTrue(FileSystem::exists('tmp/remove.txt'));

        FileSystem::delete('tmp/remove.txt');
        $this->assertFalse(FileSystem::exists('tmp/remove.txt'));
    }

    public function test_delete_throws_for_missing_file(): void
    {
        mkdir($this->tempDir . '/tmp');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File not found');

        FileSystem::delete('tmp/nonexistent.txt');
    }

    public function test_exists_returns_true_for_existing_file(): void
    {
        mkdir($this->tempDir . '/data');

        FileSystem::write('data/check.txt', 'exists');

        $this->assertTrue(FileSystem::exists('data/check.txt'));
    }

    public function test_exists_returns_false_for_missing_file(): void
    {
        mkdir($this->tempDir . '/data');

        $this->assertFalse(FileSystem::exists('data/nope.txt'));
    }

    public function test_exists_returns_false_for_non_existent_directory(): void
    {
        $this->assertFalse(FileSystem::exists('no/such/dir/file.txt'));
    }

    public function test_path_traversal_blocked_on_write(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Path traversal denied');

        FileSystem::write('../etc/passwd', 'hacked');
    }

    public function test_path_traversal_blocked_on_read(): void
    {
        $this->expectException(\RuntimeException::class);

        FileSystem::read('../../config/app.php');
    }

    public function test_path_traversal_blocked_on_delete(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Path traversal denied');

        FileSystem::delete('../etc/passwd');
    }

    public function test_deep_traversal_blocked(): void
    {
        mkdir($this->tempDir . '/logs');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Path traversal denied');

        FileSystem::write('logs/../../.env', 'hacked');
    }

    public function test_null_byte_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('null byte');

        FileSystem::read("logs/evil\0.txt");
    }

    public function test_read_throws_for_missing_file(): void
    {
        mkdir($this->tempDir . '/data');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File not found');

        FileSystem::read('data/missing.txt');
    }
}
