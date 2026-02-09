<?php

namespace Tests\Integration\Core;

use App\Models\Role;
use Tests\DatabaseTestCase;

/**
 * Tests the abstract Model class using the Role model (simple structure).
 */
class ModelTest extends DatabaseTestCase
{
    public function testFindReturnsRow(): void
    {
        $roles = Role::all();
        $first = $roles[0];

        $found = Role::find((int) $first['id']);
        $this->assertNotNull($found);
        $this->assertSame($first['slug'], $found['slug']);
    }

    public function testFindReturnsNullForMissing(): void
    {
        $this->assertNull(Role::find(99999));
    }

    public function testFindByReturnsRow(): void
    {
        $role = Role::findBy('slug', 'admin');
        $this->assertNotNull($role);
        $this->assertSame('admin', $role['slug']);
    }

    public function testFindByReturnsNullForMissing(): void
    {
        $this->assertNull(Role::findBy('slug', 'nonexistent'));
    }

    public function testWhereReturnsMultiple(): void
    {
        $roles = Role::all();
        $this->assertGreaterThanOrEqual(3, count($roles));
    }

    public function testAllReturnsAllRows(): void
    {
        $roles = Role::all();
        $this->assertCount(3, $roles); // admin, editor, viewer
    }

    public function testAllWithOrderBy(): void
    {
        $roles = Role::all('name ASC');
        $this->assertSame('admin', $roles[0]['slug']);
        $this->assertSame('viewer', $roles[2]['slug']);
    }

    public function testCreateReturnsInsertId(): void
    {
        $id = Role::create([
            'name' => 'Moderator',
            'slug' => 'moderator',
            'description' => 'Moderator role',
        ]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $role = Role::find($id);
        $this->assertSame('moderator', $role['slug']);
    }

    public function testCreateFiltersFillable(): void
    {
        // 'id' is not in fillable, should be ignored
        $id = Role::create([
            'id' => 9999,
            'name' => 'Tester',
            'slug' => 'tester',
        ]);

        $role = Role::find($id);
        $this->assertNotSame(9999, (int) $role['id']);
        $this->assertSame('tester', $role['slug']);
    }

    public function testUpdate(): void
    {
        $id = Role::create(['name' => 'Temp', 'slug' => 'temp']);
        $result = Role::update($id, ['name' => 'Updated']);

        $this->assertTrue($result);
        $role = Role::find($id);
        $this->assertSame('Updated', $role['name']);
    }

    public function testUpdateFiltersFillable(): void
    {
        $id = Role::create(['name' => 'Temp2', 'slug' => 'temp2']);
        // 'created_at' is not fillable
        $result = Role::update($id, ['name' => 'Updated2', 'created_at' => '2020-01-01']);

        $role = Role::find($id);
        $this->assertSame('Updated2', $role['name']);
    }

    public function testDelete(): void
    {
        $id = Role::create(['name' => 'ToDelete', 'slug' => 'to-delete']);
        $result = Role::delete($id);

        $this->assertTrue($result);
        $this->assertNull(Role::find($id));
    }

    public function testQuery(): void
    {
        $results = Role::query('SELECT * FROM roles WHERE slug IN (?, ?)', ['admin', 'editor']);

        $this->assertCount(2, $results);
        $slugs = array_column($results, 'slug');
        $this->assertContains('admin', $slugs);
        $this->assertContains('editor', $slugs);
    }
}
