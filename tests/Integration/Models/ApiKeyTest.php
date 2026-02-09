<?php

namespace Tests\Integration\Models;

use App\Models\ApiKey;
use Tests\DatabaseTestCase;

class ApiKeyTest extends DatabaseTestCase
{
    public function testGenerateReturnsRawKeyWithPrefix(): void
    {
        $user = $this->createTestUser();
        $rawKey = ApiKey::generate($user['id'], 'Test Key');

        $this->assertIsString($rawKey);
        $this->assertStringStartsWith('app_', $rawKey);
    }

    public function testValidateKeySuccess(): void
    {
        $user = $this->createTestUser();
        $rawKey = ApiKey::generate($user['id'], 'Test Key');

        $result = ApiKey::validateKey($rawKey);

        $this->assertNotNull($result);
        $this->assertSame((int) $user['id'], (int) $result['user_id']);
        $this->assertSame($user['username'], $result['username']);
        $this->assertArrayHasKey('key_id', $result);
    }

    public function testValidateKeyInvalid(): void
    {
        $result = ApiKey::validateKey('app_invalid_key_' . bin2hex(random_bytes(16)));

        $this->assertNull($result);
    }

    public function testValidateKeyInactiveUser(): void
    {
        $user = $this->createTestUser(['is_active' => 0]);
        $rawKey = ApiKey::generate($user['id'], 'Test Key');

        $result = ApiKey::validateKey($rawKey);

        $this->assertNull($result);
    }

    public function testForUser(): void
    {
        $user = $this->createTestUser();
        ApiKey::generate($user['id'], 'Key 1');
        ApiKey::generate($user['id'], 'Key 2');

        $keys = ApiKey::forUser($user['id']);

        $this->assertCount(2, $keys);
    }

    public function testRevoke(): void
    {
        $user = $this->createTestUser();
        $rawKey = ApiKey::generate($user['id'], 'To Revoke');

        // Get the key ID
        $keys = ApiKey::forUser($user['id']);
        $keyId = (int) $keys[0]['id'];

        $result = ApiKey::revoke($keyId, $user['id']);

        $this->assertTrue($result);

        // Key should no longer validate
        $this->assertNull(ApiKey::validateKey($rawKey));
    }

    public function testRevokeEnforcesUserOwnership(): void
    {
        $user1 = $this->createTestUser();
        $user2 = $this->createTestUser();
        $rawKey = ApiKey::generate($user1['id'], 'User1 Key');

        $keys = ApiKey::forUser($user1['id']);
        $keyId = (int) $keys[0]['id'];

        // User2 tries to revoke User1's key — should execute but not delete
        ApiKey::revoke($keyId, $user2['id']);

        // Key should still be valid
        $result = ApiKey::validateKey($rawKey);
        $this->assertNotNull($result);
    }
}
