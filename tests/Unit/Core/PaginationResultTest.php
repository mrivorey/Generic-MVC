<?php

namespace Tests\Unit\Core;

use App\Core\PaginationResult;
use Tests\TestCase;

class PaginationResultTest extends TestCase
{
    public function testItemsReturnsItems(): void
    {
        $items = [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']];
        $result = new PaginationResult($items, 1, 1, 2, 10);

        $this->assertSame($items, $result->items());
    }

    public function testCurrentPage(): void
    {
        $result = new PaginationResult([], 3, 5, 50, 10);

        $this->assertSame(3, $result->currentPage());
    }

    public function testLastPage(): void
    {
        $result = new PaginationResult([], 1, 5, 50, 10);

        $this->assertSame(5, $result->lastPage());
    }

    public function testTotal(): void
    {
        $result = new PaginationResult([], 1, 5, 50, 10);

        $this->assertSame(50, $result->total());
    }

    public function testPerPage(): void
    {
        $result = new PaginationResult([], 1, 5, 50, 10);

        $this->assertSame(10, $result->perPage());
    }

    public function testHasMorePagesTrue(): void
    {
        $result = new PaginationResult([], 1, 3, 30, 10);

        $this->assertTrue($result->hasMorePages());
    }

    public function testHasMorePagesFalse(): void
    {
        $result = new PaginationResult([], 3, 3, 30, 10);

        $this->assertFalse($result->hasMorePages());
    }

    public function testLinksEmptyForSinglePage(): void
    {
        $result = new PaginationResult([], 1, 1, 5, 10);

        $this->assertSame('', $result->links('http://example.com/items'));
    }

    public function testLinksHtmlStructure(): void
    {
        // 3 pages, on page 1: prev + 3 pages + next = 5 li elements
        $result = new PaginationResult([], 1, 3, 30, 10);
        $html = $result->links('http://example.com/items');

        $this->assertStringContainsString('<nav>', $html);
        $this->assertStringContainsString('</nav>', $html);
        $this->assertStringContainsString('<ul class="pagination">', $html);
        $this->assertStringContainsString('</ul>', $html);

        // Count li elements: prev + 3 pages + next = 5
        $this->assertSame(5, substr_count($html, '<li class="page-item'));
    }

    public function testLinksActivePageMarked(): void
    {
        $result = new PaginationResult([], 2, 3, 30, 10);
        $html = $result->links('http://example.com/items');

        $this->assertStringContainsString('<li class="page-item active"><span class="page-link">2</span></li>', $html);
    }

    public function testLinksPrevDisabledOnFirstPage(): void
    {
        $result = new PaginationResult([], 1, 3, 30, 10);
        $html = $result->links('http://example.com/items');

        $this->assertStringContainsString('<li class="page-item disabled"><span class="page-link">&laquo;</span></li>', $html);
    }

    public function testLinksNextDisabledOnLastPage(): void
    {
        $result = new PaginationResult([], 3, 3, 30, 10);
        $html = $result->links('http://example.com/items');

        $this->assertStringContainsString('<li class="page-item disabled"><span class="page-link">&raquo;</span></li>', $html);
    }

    public function testLinksPreservesQueryString(): void
    {
        $result = new PaginationResult([], 1, 3, 30, 10);
        $html = $result->links('http://example.com/items?search=foo&status=active');

        // Verify existing params are preserved in page links
        $this->assertStringContainsString('search=foo', $html);
        $this->assertStringContainsString('status=active', $html);
        $this->assertStringContainsString('page=', $html);
    }

    public function testLinksEllipsisForManyPages(): void
    {
        $result = new PaginationResult([], 10, 20, 200, 10);
        $html = $result->links('http://example.com/items');

        // Should contain ellipsis markers
        $this->assertStringContainsString('...', $html);

        // Should show first and last page
        $this->assertStringContainsString('>1<', $html);
        $this->assertStringContainsString('>20<', $html);

        // Should show current page as active
        $this->assertStringContainsString('<li class="page-item active"><span class="page-link">10</span></li>', $html);
    }
}
