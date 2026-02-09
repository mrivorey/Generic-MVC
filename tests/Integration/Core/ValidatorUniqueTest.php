<?php

namespace Tests\Integration\Core;

use App\Core\Validator;
use Tests\DatabaseTestCase;

class ValidatorUniqueTest extends DatabaseTestCase
{
    public function testUniquePassesWhenNoMatch(): void
    {
        $v = Validator::make(
            ['slug' => 'nonexistent-role-' . uniqid()],
            ['slug' => 'unique:roles,slug']
        )->validate();

        $this->assertFalse($v->fails());
    }

    public function testUniqueFailsWhenExists(): void
    {
        $v = Validator::make(
            ['slug' => 'admin'],
            ['slug' => 'unique:roles,slug']
        )->validate();

        $this->assertTrue($v->fails());
        $this->assertStringContainsString('already been taken', $v->errors()['slug'][0]);
    }

    public function testUniqueWithExceptId(): void
    {
        // Get admin role ID
        $adminRole = \App\Models\Role::findBy('slug', 'admin');

        $v = Validator::make(
            ['slug' => 'admin'],
            ['slug' => 'unique:roles,slug,' . $adminRole['id']]
        )->validate();

        // Should pass because we're excluding the admin's own ID
        $this->assertFalse($v->fails());
    }

    public function testUniqueWithCustomColumn(): void
    {
        $v = Validator::make(
            ['role_name' => 'Admin'],
            ['role_name' => 'unique:roles,name']
        )->validate();

        $this->assertTrue($v->fails());
    }
}
