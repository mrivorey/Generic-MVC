<?php

namespace Tests\Integration\Models;

use App\Models\RememberToken;
use Tests\DatabaseTestCase;

class RememberTokenTest extends DatabaseTestCase
{
    public function testCreateTokenReturnsRawToken(): void
    {
        $user = $this->createTestUser();
        $rawToken = RememberToken::createToken($user['id']);

        $this->assertIsString($rawToken);
        $this->assertSame(64, strlen($rawToken)); // 32 bytes = 64 hex chars
    }

    public function testValidateWithValidToken(): void
    {
        $user = $this->createTestUser();
        $rawToken = RememberToken::createToken($user['id']);

        $result = RememberToken::validate($rawToken);

        $this->assertNotNull($result);
        $this->assertSame((int) $user['id'], (int) $result['user_id']);
    }

    public function testValidateWithExpiredTokenReturnsNull(): void
    {
        $user = $this->createTestUser();
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        // Insert an already-expired token
        $stmt = self::$pdo->prepare(
            'INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$user['id'], $tokenHash, date('Y-m-d H:i:s', time() - 3600)]);

        $result = RememberToken::validate($rawToken);

        $this->assertNull($result);
    }

    public function testValidateWithInvalidToken(): void
    {
        $result = RememberToken::validate('totally-invalid-token');

        $this->assertNull($result);
    }

    public function testDeleteToken(): void
    {
        $user = $this->createTestUser();
        $rawToken = RememberToken::createToken($user['id']);

        RememberToken::deleteToken($rawToken);

        $result = RememberToken::validate($rawToken);
        $this->assertNull($result);
    }

    public function testClearForUser(): void
    {
        $user = $this->createTestUser();
        RememberToken::createToken($user['id']);
        RememberToken::createToken($user['id']);

        RememberToken::clearForUser($user['id']);

        // Verify tokens are gone
        $stmt = self::$pdo->prepare('SELECT COUNT(*) FROM remember_tokens WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    public function testGetLifetime(): void
    {
        $lifetime = RememberToken::getLifetime();
        $this->assertIsInt($lifetime);
        $this->assertGreaterThan(0, $lifetime);
    }

    public function testGetCookieName(): void
    {
        $name = RememberToken::getCookieName();
        $this->assertIsString($name);
        $this->assertNotEmpty($name);
    }
}
