<?php

namespace App\Core;

class FileSystem
{
    private static ?string $storageRoot = null;

    public static function write(string $path, string $data): void
    {
        $resolved = self::resolvePath($path, createDir: true);

        if (file_put_contents($resolved, $data, LOCK_EX) === false) {
            throw new \RuntimeException("Failed to write file: {$path}");
        }
    }

    public static function read(string $path): string
    {
        $resolved = self::resolvePath($path);

        if (!file_exists($resolved)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        $contents = file_get_contents($resolved);

        if ($contents === false) {
            throw new \RuntimeException("Failed to read file: {$path}");
        }

        return $contents;
    }

    public static function append(string $path, string $data): void
    {
        $resolved = self::resolvePath($path, createDir: true);

        if (file_put_contents($resolved, $data, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException("Failed to append to file: {$path}");
        }
    }

    public static function delete(string $path): void
    {
        $resolved = self::resolvePath($path);

        if (!file_exists($resolved)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        if (!unlink($resolved)) {
            throw new \RuntimeException("Failed to delete file: {$path}");
        }
    }

    public static function exists(string $path): bool
    {
        try {
            $resolved = self::resolvePath($path);
        } catch (\RuntimeException) {
            return false;
        }

        return file_exists($resolved);
    }

    public static function setStorageRoot(string $path): void
    {
        self::$storageRoot = $path;
    }

    public static function reset(): void
    {
        self::$storageRoot = null;
    }

    private static function resolveRoot(): string
    {
        if (self::$storageRoot === null) {
            $config = require dirname(__DIR__, 2) . '/config/app.php';
            self::$storageRoot = realpath($config['paths']['storage']);

            if (self::$storageRoot === false) {
                throw new \RuntimeException('Storage root directory does not exist');
            }
        }

        return self::$storageRoot;
    }

    private static function resolvePath(string $relativePath, bool $createDir = false): string
    {
        if (str_contains($relativePath, "\0")) {
            throw new \RuntimeException("Invalid path: null byte detected");
        }

        $root = self::resolveRoot();
        $dir = dirname($relativePath);
        $filename = basename($relativePath);
        $fullDir = $root . '/' . $dir;

        if ($createDir && !is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        $realDir = realpath($fullDir);

        if ($realDir === false) {
            throw new \RuntimeException("Directory does not exist: {$dir}");
        }

        $resolved = $realDir . '/' . $filename;

        if (!str_starts_with($resolved, $root)) {
            throw new \RuntimeException("Path traversal denied: {$relativePath}");
        }

        return $resolved;
    }
}
