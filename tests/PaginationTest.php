<?php

namespace Vusys\LaravelSmarty\Tests;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class PaginationTest extends TestCase
{
    public function test_tailwind_pagination_renders_via_smarty(): void
    {
        $paginator = $this->makePaginator(currentPage: 2, perPage: 10, total: 50);

        $output = $paginator->links()->toHtml();

        $this->assertStringContainsString('<nav role="navigation"', $output);
        $this->assertStringContainsString('aria-current="page"', $output);
        $this->assertStringContainsString('rel="next"', $output);
        $this->assertStringContainsString('rel="prev"', $output);
        $this->assertStringContainsString('http://localhost/users?page=3', $output);
    }

    public function test_simple_pagination_renders_via_smarty(): void
    {
        $simple = new Paginator(range(1, 10), 5, 1, ['path' => 'http://localhost/posts']);

        $output = $simple->links()->toHtml();

        $this->assertStringContainsString('<nav role="navigation"', $output);
        $this->assertStringContainsString('http://localhost/posts?page=2', $output);
    }

    public function test_bootstrap_5_variant_renders_via_smarty(): void
    {
        $paginator = $this->makePaginator(currentPage: 2, perPage: 10, total: 50);

        $output = $paginator->links('pagination::bootstrap-5')->toHtml();

        $this->assertStringContainsString('class="pagination"', $output);
        $this->assertStringContainsString('class="page-item active"', $output);
        $this->assertStringContainsString('class="page-link"', $output);
    }

    protected function makePaginator(int $currentPage, int $perPage, int $total): LengthAwarePaginator
    {
        $items = range(1, min($perPage, $total));

        return new LengthAwarePaginator(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $currentPage,
            options: ['path' => 'http://localhost/users'],
        );
    }
}
