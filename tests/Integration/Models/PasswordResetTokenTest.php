<?php

namespace Tests\Integration\Models;

use App\Models\PasswordResetToken;
use Tests\DatabaseTestCase;

class PasswordResetTokenTest extends DatabaseTestCase
{
    public function testCreateTokenReturnsRawToken(): void
    {
        $user = $this->createTestUser();
        $rawToken = PasswordResetToken::createToken($user['id']);

        $this->assertIsString($rawToken);
        $this->assertSame(64, strlen($rawToken)); // 32 bytes = 64 hex chars
    }

    public function testValidateWithValidToken(): void
    {
        $user = $this->createTestUser();
        $rawToken = PasswordResetToken::createToken($user['id']);

        $result = PasswordResetToken::validate($rawToken);

        $this->assertNotNull($result);
        $this->assertSame((int) $user['id'], (int) $result['user_id']);
        $this->assertSame($user['username'], $result['username']);
        $this->assertSame($user['email'], $result['email']);
    }

    public function testValidateWithExpiredTokenReturnsNull(): void
    {
        $user = $this->createTestUser();
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        // Insert an already-expired token
        $stmt = self::$pdo->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$user['id'], $tokenHash, date('Y-m-d H:i:s', time() - 3600)]);

        $result = PasswordResetToken::validate($rawToken);

        $this->assertNull($result);
    }

    public function testValidateWithInvalidToken(): void
    {
        $result = PasswordResetToken::validate('totally-invalid-token');

        $this->assertNull($result);
    }

    public function testDeleteToken(): void
    {
        $user = $this->createTestUser();
        $rawToken = PasswordResetToken::createToken($user['id']);

        PasswordResetToken::deleteToken($rawToken);

        $result = PasswordResetToken::validate($rawToken);
        $this->assertNull($result);
    }

    public function testClearForUser(): void
    {
        $user = $this->createTestUser();
        PasswordResetToken::createToken($user['id']);

        PasswordResetToken::clearForUser($user['id']);

        // Verify tokens are gone
        $stmt = self::$pdo->prepare('SELECT COUNT(*) FROM password_reset_tokens WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    public function testNewTokenReplacesOldToken(): void
    {
        $user = $this->createTestUser();
        $firstToken = PasswordResetToken::createToken($user['id']);
        $secondToken = PasswordResetToken::createToken($user['id']);

        // First token should be invalid (replaced)
        $this->assertNull(PasswordResetToken::validate($firstToken));

        // Second token should be valid
        $this->assertNotNull(PasswordResetToken::validate($secondToken));

        // Only one row per user
        $stmt = self::$pdo->prepare('SELECT COUNT(*) FROM password_reset_tokens WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function testValidateWithInactiveUserReturnsNull(): void
    {
        $user = $this->createTestUser(['is_active' => 0]);
        $rawToken = PasswordResetToken::createToken($user['id']);

        $result = PasswordResetToken::validate($rawToken);

        $this->assertNull($result);
    }
}
