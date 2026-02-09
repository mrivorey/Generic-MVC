<?php

namespace Tests\Unit\Core;

use App\Core\Cache;
use App\Core\CacheDriver;
use App\Core\FileCacheDriver;
use Tests\TestCase;

class CacheTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/cache_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        // Inject a FileCacheDriver pointing at temp dir
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

    public function test_get_set_round_trip(): void
    {
        Cache::set('greeting', 'hello world', 300);
        $this->assertSame('hello world', Cache::get('greeting'));
    }

    public function test_default_on_miss(): void
    {
        $this->assertNull(Cache::get('nonexistent'));
        $this->assertSame('fallback', Cache::get('nonexistent', 'fallback'));
    }

    public function test_has_true(): void
    {
        Cache::set('exists', 'yes', 300);
        $this->assertTrue(Cache::has('exists'));
    }

    public function test_has_false(): void
    {
        $this->assertFalse(Cache::has('does_not_exist'));
    }

    public function test_delete(): void
    {
        Cache::set('temp', 'value', 300);
        $this->assertSame('value', Cache::get('temp'));

        Cache::delete('temp');
        $this->assertNull(Cache::get('temp'));
        $this->assertSame('gone', Cache::get('temp', 'gone'));
    }

    public function test_clear(): void
    {
        Cache::set('a', 1, 300);
        Cache::set('b', 2, 300);
        Cache::set('c', 3, 300);

        $this->assertSame(1, Cache::get('a'));
        $this->assertSame(2, Cache::get('b'));
        $this->assertSame(3, Cache::get('c'));

        Cache::clear();

        $this->assertNull(Cache::get('a'));
        $this->assertNull(Cache::get('b'));
        $this->assertNull(Cache::get('c'));
    }

    public function test_expired_returns_default(): void
    {
        // Write a cache file with an expiry timestamp in the past
        $key = 'expired_item';
        $sanitizedKey = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $key);
        $path = $this->tempDir . '/' . $sanitizedKey . '.cache';

        $pastExpiry = time() - 100;
        $content = $pastExpiry . "\n" . serialize('stale data');
        file_put_contents($path, $content, LOCK_EX);

        $this->assertNull(Cache::get('expired_item'));
        $this->assertSame('default', Cache::get('expired_item', 'default'));

        // The expired file should have been cleaned up
        $this->assertFileDoesNotExist($path);
    }

    public function test_remember_caches_on_miss(): void
    {
        $callCount = 0;
        $result = Cache::remember('computed', 300, function () use (&$callCount) {
            $callCount++;
            return 'expensive result';
        });

        $this->assertSame('expensive result', $result);
        $this->assertSame(1, $callCount);

        // Value should now be cached
        $this->assertSame('expensive result', Cache::get('computed'));
    }

    public function test_remember_returns_cached(): void
    {
        Cache::set('preloaded', 'cached value', 300);

        $callCount = 0;
        $result = Cache::remember('preloaded', 300, function () use (&$callCount) {
            $callCount++;
            return 'fresh value';
        });

        $this->assertSame('cached value', $result);
        $this->assertSame(0, $callCount);
    }

    public function test_ttl_override(): void
    {
        // Set with a specific TTL, then verify the file contains a future expiry
        Cache::set('ttl_test', 'data', 600);

        $sanitizedKey = 'ttl_test';
        $path = $this->tempDir . '/' . $sanitizedKey . '.cache';
        $this->assertFileExists($path);

        $handle = fopen($path, 'r');
        $expiry = (int) trim(fgets($handle));
        fclose($handle);

        // Expiry should be roughly now + 600 seconds
        $this->assertGreaterThan(time() + 500, $expiry);
        $this->assertLessThanOrEqual(time() + 600, $expiry);
    }

    public function test_driver_injection(): void
    {
        $mock = new class implements CacheDriver {
            public array $store = [];

            public function get(string $key): mixed
            {
                return $this->store[$key] ?? null;
            }

            public function set(string $key, mixed $value, int $ttl = 0): void
            {
                $this->store[$key] = $value;
            }

            public function has(string $key): bool
            {
                return isset($this->store[$key]);
            }

            public function delete(string $key): void
            {
                unset($this->store[$key]);
            }

            public function clear(): void
            {
                $this->store = [];
            }
        };

        Cache::setDriver($mock);
        Cache::set('custom', 'driver_value');

        $this->assertSame('driver_value', Cache::get('custom'));
        $this->assertSame('driver_value', $mock->store['custom']);
    }

    public function test_reset_clears_driver(): void
    {
        Cache::set('before_reset', 'value', 300);
        $this->assertSame('value', Cache::get('before_reset'));

        // Reset clears the driver reference; a new driver won't have old data
        // if we point it to a fresh directory
        Cache::reset();

        $freshDir = sys_get_temp_dir() . '/cache_test_reset_' . uniqid();
        mkdir($freshDir, 0755, true);
        Cache::setDriver(new FileCacheDriver($freshDir));

        $this->assertNull(Cache::get('before_reset'));

        // Clean up
        Cache::reset();
        $this->removeDir($freshDir);
    }

    public function test_key_sanitization(): void
    {
        Cache::set('user:profile:42', 'profile data', 300);
        $this->assertSame('profile data', Cache::get('user:profile:42'));

        Cache::set('path/to/resource', 'resource data', 300);
        $this->assertSame('resource data', Cache::get('path/to/resource'));

        Cache::set('key with spaces!@#$', 'special', 300);
        $this->assertSame('special', Cache::get('key with spaces!@#$'));
    }

    public function test_complex_value_serialization(): void
    {
        // Array
        $array = ['name' => 'Alice', 'roles' => ['admin', 'editor'], 'active' => true];
        Cache::set('array_data', $array, 300);
        $this->assertSame($array, Cache::get('array_data'));

        // Nested array
        $nested = ['level1' => ['level2' => ['level3' => 'deep']]];
        Cache::set('nested_data', $nested, 300);
        $this->assertSame($nested, Cache::get('nested_data'));

        // Object (stdClass)
        $obj = (object) ['id' => 1, 'name' => 'Test'];
        Cache::set('object_data', $obj, 300);
        $this->assertEquals($obj, Cache::get('object_data'));

        // Integer
        Cache::set('int_data', 42, 300);
        $this->assertSame(42, Cache::get('int_data'));

        // Boolean
        Cache::set('bool_data', true, 300);
        $this->assertSame(true, Cache::get('bool_data'));

        // Float
        Cache::set('float_data', 3.14, 300);
        $this->assertSame(3.14, Cache::get('float_data'));
    }
}
