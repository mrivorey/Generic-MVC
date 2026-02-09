<?php

namespace App\Core;

class FileCacheDriver implements CacheDriver
{
    private string $cacheDir;
    private bool $gcRanThisRequest = false;

    public function __construct(?string $cacheDir = null)
    {
        if ($cacheDir !== null) {
            $this->cacheDir = $cacheDir;
        } else {
            $config = require dirname(__DIR__, 2) . '/config/app.php';
            $this->cacheDir = $config['paths']['storage'] . '/cache';
        }

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function get(string $key): mixed
    {
        $path = $this->path($key);

        if (!file_exists($path)) {
            return null;
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return null;
        }

        $expiry = (int) trim(fgets($handle));
        if ($expiry !== 0 && $expiry < time()) {
            fclose($handle);
            @unlink($path);
            return null;
        }

        $data = stream_get_contents($handle);
        fclose($handle);

        return unserialize($data);
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $path = $this->path($key);
        $expiry = $ttl > 0 ? time() + $ttl : 0;
        $content = $expiry . "\n" . serialize($value);

        file_put_contents($path, $content, LOCK_EX);

        $this->maybeGc();
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    public function clear(): void
    {
        $files = @scandir($this->cacheDir);
        if (!$files) {
            return;
        }

        foreach ($files as $file) {
            if (str_ends_with($file, '.cache')) {
                @unlink($this->cacheDir . '/' . $file);
            }
        }
    }

    private function path(string $key): string
    {
        return $this->cacheDir . '/' . $this->sanitizeKey($key) . '.cache';
    }

    private function sanitizeKey(string $key): string
    {
        if (strlen($key) > 128) {
            return md5($key);
        }
        return preg_replace('/[^a-zA-Z0-9_.-]/', '_', $key);
    }

    private function maybeGc(): void
    {
        if ($this->gcRanThisRequest) {
            return;
        }
        $this->gcRanThisRequest = true;

        $files = @scandir($this->cacheDir);
        if (!$files) {
            return;
        }

        $now = time();
        foreach ($files as $file) {
            if (!str_ends_with($file, '.cache')) {
                continue;
            }

            $fullPath = $this->cacheDir . '/' . $file;
            $handle = @fopen($fullPath, 'r');
            if (!$handle) {
                continue;
            }

            $expiry = (int) trim(fgets($handle));
            fclose($handle);

            if ($expiry !== 0 && $expiry < $now) {
                @unlink($fullPath);
            }
        }
    }
}
