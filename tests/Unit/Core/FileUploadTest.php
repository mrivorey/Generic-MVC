<?php

namespace Tests\Unit\Core;

use App\Core\FileSystem;
use App\Core\FileUpload;
use Tests\TestCase;

class FileUploadTest extends TestCase
{
    private string $tempDir;
    private array $originalFiles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/upload_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->tempDir = realpath($this->tempDir);
        FileSystem::setStorageRoot($this->tempDir);

        $this->originalFiles = $_FILES;
        $_FILES = [];

        FileUpload::setConfig([
            'allowed_types' => ['image/jpeg', 'image/png', 'application/pdf'],
            'max_size' => 5242880,
        ]);
    }

    protected function tearDown(): void
    {
        $_FILES = $this->originalFiles;
        FileUpload::reset();
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

    private function createTempFile(string $content = 'test content'): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'upload_');
        file_put_contents($tmpFile, $content);
        return $tmpFile;
    }

    /**
     * Minimal JPEG: SOI marker (FFD8) + APP0 marker (FFE0)
     */
    private function createJpegContent(): string
    {
        return "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 100);
    }

    /**
     * Minimal PDF header
     */
    private function createPdfContent(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< >>\nendobj";
    }

    /**
     * Plain text content (not an allowed MIME type by default)
     */
    private function createTextContent(): string
    {
        return "This is plain text content that finfo will detect as text/plain.";
    }

    public function test_null_for_no_upload(): void
    {
        $result = FileUpload::handle('avatar');
        $this->assertNull($result);
    }

    public function test_null_for_no_file_error(): void
    {
        $_FILES['avatar'] = [
            'name' => '',
            'type' => '',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0,
        ];

        $result = FileUpload::handle('avatar');
        $this->assertNull($result);
    }

    public function test_success_returns_array(): void
    {
        $tmpFile = $this->createTempFile($this->createJpegContent());

        $_FILES['avatar'] = [
            'name' => 'photo.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ];

        $result = FileUpload::handle('avatar');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('original_name', $result);
        $this->assertArrayHasKey('size', $result);
        $this->assertArrayHasKey('mime_type', $result);
        $this->assertEquals('photo.jpg', $result['original_name']);
        $this->assertStringStartsWith('uploads/files/', $result['path']);
        $this->assertStringEndsWith('.jpg', $result['path']);

        // Verify the file was actually written via FileSystem
        $this->assertTrue(FileSystem::exists($result['path']));

        @unlink($tmpFile);
    }

    public function test_unique_filename_with_extension(): void
    {
        $tmpFile = $this->createTempFile($this->createJpegContent());

        $_FILES['avatar'] = [
            'name' => 'my-photo.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ];

        $result = FileUpload::handle('avatar');

        // Path should be uploads/files/{32-hex-chars}.jpg
        $filename = basename($result['path']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}\.jpg$/', $filename);

        @unlink($tmpFile);
    }

    public function test_custom_directory(): void
    {
        $tmpFile = $this->createTempFile($this->createJpegContent());

        $_FILES['avatar'] = [
            'name' => 'profile.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ];

        $result = FileUpload::handle('avatar', ['directory' => 'avatars']);

        $this->assertStringStartsWith('uploads/avatars/', $result['path']);

        @unlink($tmpFile);
    }

    public function test_throws_on_disallowed_mime(): void
    {
        $tmpFile = $this->createTempFile($this->createTextContent());

        $_FILES['document'] = [
            'name' => 'readme.txt',
            'type' => 'text/plain',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not allowed');

        try {
            FileUpload::handle('document');
        } finally {
            @unlink($tmpFile);
        }
    }

    public function test_throws_on_too_large(): void
    {
        $tmpFile = $this->createTempFile($this->createJpegContent());

        $_FILES['avatar'] = [
            'name' => 'big.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 10000000, // 10MB, over the 5MB limit
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File size exceeds maximum allowed size');

        try {
            FileUpload::handle('avatar');
        } finally {
            @unlink($tmpFile);
        }
    }

    public function test_throws_on_upload_error(): void
    {
        $_FILES['avatar'] = [
            'name' => 'photo.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_PARTIAL,
            'size' => 0,
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('only partially uploaded');

        FileUpload::handle('avatar');
    }

    public function test_delete_removes_file(): void
    {
        // Write a file directly via FileSystem
        $path = 'uploads/files/test-delete.txt';
        FileSystem::write($path, 'delete me');
        $this->assertTrue(FileSystem::exists($path));

        $result = FileUpload::delete($path);

        $this->assertTrue($result);
        $this->assertFalse(FileSystem::exists($path));
    }

    public function test_delete_rejects_non_uploads_path(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot delete files outside the uploads directory');

        FileUpload::delete('logs/app.log');
    }

    public function test_reset_clears_config(): void
    {
        // Set a custom config with a very small max_size
        FileUpload::setConfig([
            'allowed_types' => ['image/jpeg'],
            'max_size' => 100,
        ]);

        $tmpFile = $this->createTempFile($this->createJpegContent());

        $_FILES['avatar'] = [
            'name' => 'photo.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 200, // Over the 100 byte limit
        ];

        // Should throw with the 100 byte limit
        try {
            FileUpload::handle('avatar');
            $this->fail('Expected RuntimeException for size limit');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('File size exceeds', $e->getMessage());
        }

        // Reset and set a larger config
        FileUpload::reset();
        FileUpload::setConfig([
            'allowed_types' => ['image/jpeg'],
            'max_size' => 5242880,
        ]);

        // Now the same file should be accepted
        $result = FileUpload::handle('avatar');
        $this->assertIsArray($result);

        @unlink($tmpFile);
    }
}
