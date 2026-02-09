<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Smoke tests that make real HTTP requests to the running app.
 *
 * These verify the full stack boots: front controller, routes, middleware,
 * views, and headers. Requires the Docker app container to be running.
 */
class SmokeTest extends TestCase
{
    private static string $baseUrl = 'http://localhost:8088';

    private function httpGet(string $path): array
    {
        $ch = curl_init(self::$baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $this->markTestSkipped('App not running: ' . curl_error($ch));
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        return [
            'status' => $statusCode,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    public function test_homepage_loads(): void
    {
        $response = $this->httpGet('/');

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('</html>', $response['body']);
    }

    public function test_login_page_loads(): void
    {
        $response = $this->httpGet('/login');

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('login', strtolower($response['body']));
    }

    public function test_forgot_password_page_loads(): void
    {
        $response = $this->httpGet('/forgot-password');

        $this->assertSame(200, $response['status']);
    }

    public function test_nonexistent_page_returns_404(): void
    {
        $response = $this->httpGet('/this-page-does-not-exist');

        $this->assertSame(404, $response['status']);
    }

    public function test_protected_page_redirects_to_login(): void
    {
        $response = $this->httpGet('/profile');

        // Auth middleware should redirect or return 403
        $this->assertTrue(
            in_array($response['status'], [302, 403]),
            "Expected 302 or 403, got {$response['status']}"
        );
    }

    public function test_admin_page_requires_auth(): void
    {
        $response = $this->httpGet('/admin/users');

        $this->assertTrue(
            in_array($response['status'], [302, 403]),
            "Expected 302 or 403, got {$response['status']}"
        );
    }

    public function test_security_headers_present(): void
    {
        $response = $this->httpGet('/');

        $this->assertStringContainsString('X-Content-Type-Options: nosniff', $response['headers']);
        $this->assertStringContainsString('X-Frame-Options: DENY', $response['headers']);
        $this->assertStringContainsString('X-XSS-Protection: 0', $response['headers']);
        $this->assertStringContainsString('Referrer-Policy: strict-origin-when-cross-origin', $response['headers']);
        $this->assertStringContainsString('Permissions-Policy: camera=(), microphone=(), geolocation=()', $response['headers']);
    }

    public function test_api_without_auth_returns_401(): void
    {
        $response = $this->httpGet('/api/v1/user');

        $this->assertSame(401, $response['status']);
        $this->assertStringContainsString('"error"', $response['body']);
    }
}
