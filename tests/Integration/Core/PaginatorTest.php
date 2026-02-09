<?php

namespace Tests\Integration\Core;

use App\Core\Paginator;
use Tests\DatabaseTestCase;

class PaginatorTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Paginator::reset();
    }

    public function testCorrectPageOfResults(): void
    {
        // Create 10 users
        for ($i = 0; $i < 10; $i++) {
            $this->createTestUser(['username' => "paguser_{$i}_" . uniqid()]);
        }

        Paginator::setCurrentPage(2);
        $result = Paginator::paginate('users', perPage: 3, orderBy: 'id ASC');

        $this->assertSame(3, count($result->items()));
        $this->assertSame(2, $result->currentPage());
        $this->assertGreaterThanOrEqual(10, $result->total());
    }

    public function testDefaultPage1(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createTestUser(['username' => "defuser_{$i}_" . uniqid()]);
        }

        // No setCurrentPage call — should default to page 1
        $result = Paginator::paginate('users', perPage: 3, orderBy: 'id ASC');

        $this->assertSame(1, $result->currentPage());
        $this->assertSame(3, count($result->items()));
    }

    public function testClampsToLastPage(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createTestUser(['username' => "clampuser_{$i}_" . uniqid()]);
        }

        Paginator::setCurrentPage(999);
        $result = Paginator::paginate('users', perPage: 3);

        $this->assertSame($result->lastPage(), $result->currentPage());
        $this->assertGreaterThanOrEqual(0, count($result->items()));
    }

    public function testConditionsFiltering(): void
    {
        // Create active users
        for ($i = 0; $i < 4; $i++) {
            $this->createTestUser([
                'username' => "active_{$i}_" . uniqid(),
                'is_active' => 1,
            ]);
        }

        // Create inactive users
        for ($i = 0; $i < 3; $i++) {
            $this->createTestUser([
                'username' => "inactive_{$i}_" . uniqid(),
                'is_active' => 0,
            ]);
        }

        Paginator::setCurrentPage(1);
        $activeResult = Paginator::paginate('users', perPage: 50, conditions: ['is_active' => 1]);
        $inactiveResult = Paginator::paginate('users', perPage: 50, conditions: ['is_active' => 0]);

        $this->assertGreaterThanOrEqual(4, $activeResult->total());
        $this->assertSame(3, $inactiveResult->total());

        // Verify all inactive results really are inactive
        foreach ($inactiveResult->items() as $item) {
            $this->assertSame(0, (int) $item['is_active']);
        }
    }

    public function testOrderBy(): void
    {
        $this->createTestUser(['username' => 'zzz_order_' . uniqid(), 'name' => 'Zara']);
        $this->createTestUser(['username' => 'aaa_order_' . uniqid(), 'name' => 'Alice']);

        Paginator::setCurrentPage(1);
        $result = Paginator::paginate('users', perPage: 50, orderBy: 'name ASC');
        $items = $result->items();

        // Find our test users in the results and verify Alice comes before Zara
        $aliceIndex = null;
        $zaraIndex = null;
        foreach ($items as $index => $item) {
            if ($item['name'] === 'Alice') {
                $aliceIndex = $index;
            }
            if ($item['name'] === 'Zara') {
                $zaraIndex = $index;
            }
        }

        $this->assertNotNull($aliceIndex);
        $this->assertNotNull($zaraIndex);
        $this->assertLessThan($zaraIndex, $aliceIndex);
    }

    public function testEmptyTable(): void
    {
        // Paginate the rate_limits table which should be empty in tests
        Paginator::setCurrentPage(1);
        $result = Paginator::paginate('rate_limits');

        $this->assertSame(0, $result->total());
        $this->assertSame([], $result->items());
        $this->assertSame(1, $result->lastPage());
    }

    public function testFromQueryWithJoins(): void
    {
        // Create test users with roles
        $this->createTestUser(['username' => 'joinuser_' . uniqid()], ['admin']);
        $this->createTestUser(['username' => 'joinuser2_' . uniqid()], ['viewer']);

        $countSql = "SELECT COUNT(*) FROM users u INNER JOIN user_roles ur ON u.id = ur.user_id INNER JOIN roles r ON ur.role_id = r.id WHERE r.slug = ?";
        $selectSql = "SELECT u.*, r.slug AS role_slug FROM users u INNER JOIN user_roles ur ON u.id = ur.user_id INNER JOIN roles r ON ur.role_id = r.id WHERE r.slug = ? ORDER BY u.id ASC";
        $bindings = ['admin'];

        Paginator::setCurrentPage(1);
        $result = Paginator::fromQuery($countSql, $selectSql, $bindings, perPage: 10);

        $this->assertGreaterThanOrEqual(1, $result->total());
        foreach ($result->items() as $item) {
            $this->assertSame('admin', $item['role_slug']);
        }
    }
}
