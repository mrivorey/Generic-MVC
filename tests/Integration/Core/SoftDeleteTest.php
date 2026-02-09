<?php

namespace Tests\Integration\Core;

use App\Models\User;
use App\Models\Role;
use Tests\DatabaseTestCase;

class SoftDeleteTest extends DatabaseTestCase
{
    public function test_delete_sets_deleted_at(): void
    {
        $user = $this->createTestUser();
        User::delete($user['id']);

        $stmt = self::$pdo->prepare('SELECT deleted_at FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();

        $this->assertNotNull($row['deleted_at']);
    }

    public function test_find_excludes_deleted(): void
    {
        $user = $this->createTestUser();
        User::delete($user['id']);

        $found = User::find($user['id']);
        $this->assertNull($found);
    }

    public function test_findBy_excludes_deleted(): void
    {
        $user = $this->createTestUser();
        User::delete($user['id']);

        $found = User::findBy('username', $user['username']);
        $this->assertNull($found);
    }

    public function test_where_excludes_deleted(): void
    {
        $user1 = $this->createTestUser(['name' => 'SoftDeleteTest_Where']);
        $user2 = $this->createTestUser(['name' => 'SoftDeleteTest_Where']);
        User::delete($user1['id']);

        $results = User::where('name', 'SoftDeleteTest_Where');
        $ids = array_column($results, 'id');

        $this->assertNotContains($user1['id'], $ids);
        $this->assertContains($user2['id'], $ids);
    }

    public function test_all_excludes_deleted(): void
    {
        $user1 = $this->createTestUser();
        $user2 = $this->createTestUser();
        User::delete($user1['id']);

        $all = User::all();
        $ids = array_column($all, 'id');

        $this->assertNotContains($user1['id'], $ids);
        $this->assertContains($user2['id'], $ids);
    }

    public function test_withTrashed_includes_deleted(): void
    {
        $user = $this->createTestUser();
        User::delete($user['id']);

        User::withTrashed();
        $found = User::find($user['id']);

        $this->assertNotNull($found);
        $this->assertSame($user['id'], $found['id']);
    }

    public function test_onlyTrashed_returns_only_deleted(): void
    {
        $user1 = $this->createTestUser(['name' => 'SoftDeleteTest_OnlyTrashed']);
        $user2 = $this->createTestUser(['name' => 'SoftDeleteTest_OnlyTrashed']);
        User::delete($user1['id']);

        User::onlyTrashed();
        $results = User::where('name', 'SoftDeleteTest_OnlyTrashed');
        $ids = array_column($results, 'id');

        $this->assertContains($user1['id'], $ids);
        $this->assertNotContains($user2['id'], $ids);
    }

    public function test_flags_auto_reset(): void
    {
        $user = $this->createTestUser();
        User::delete($user['id']);

        // First call with withTrashed — should include deleted
        User::withTrashed();
        $found = User::find($user['id']);
        $this->assertNotNull($found);

        // Second call without withTrashed — should exclude deleted (flag auto-reset)
        $found = User::find($user['id']);
        $this->assertNull($found);
    }

    public function test_restore_clears_deleted_at(): void
    {
        $user = $this->createTestUser();
        User::delete($user['id']);

        // Verify it's soft-deleted
        $found = User::find($user['id']);
        $this->assertNull($found);

        // Restore
        User::restore($user['id']);

        // Should now be findable
        $found = User::find($user['id']);
        $this->assertNotNull($found);
        $this->assertSame($user['id'], $found['id']);
    }

    public function test_forceDelete_removes_row(): void
    {
        $user = $this->createTestUser();
        User::forceDelete($user['id']);

        // Even withTrashed should not find it
        User::withTrashed();
        $found = User::find($user['id']);
        $this->assertNull($found);

        // Direct DB check
        $stmt = self::$pdo->prepare('SELECT COUNT(*) FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    public function test_model_without_soft_deletes_hard_deletes(): void
    {
        $id = Role::create(['name' => 'TempRole', 'slug' => 'temp-soft-delete-test']);

        // Verify it exists
        $role = Role::find($id);
        $this->assertNotNull($role);

        // Delete via Role (which has no softDeletes)
        Role::delete($id);

        // Row should be completely gone from DB
        $stmt = self::$pdo->prepare('SELECT COUNT(*) FROM roles WHERE id = ?');
        $stmt->execute([$id]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }
}
