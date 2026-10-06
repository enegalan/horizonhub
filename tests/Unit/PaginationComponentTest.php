<?php

namespace Tests\Unit;

use App\Support\Jobs\SearchResultsPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class PaginationComponentTest extends TestCase
{
    public function test_exact_totals_are_rendered_without_a_plus_suffix(): void
    {
        $view = $this->view('components.pagination', [
            'paginator' => $this->private__paginator(500, false),
        ]);

        $view->assertSee('Showing 1–2 of 500', false);
        $view->assertDontSee('of 500+', false);
    }

    public function test_plain_length_aware_paginators_are_rendered_without_a_plus_suffix(): void
    {
        $view = $this->view('components.pagination', [
            'paginator' => new LengthAwarePaginator([1, 2], 500, 2, 1, ['path' => '/alerts']),
        ]);

        $view->assertSee('Showing 1–2 of 500', false);
    }

    public function test_truncated_search_totals_are_rendered_with_a_plus_suffix(): void
    {
        $view = $this->view('components.pagination', [
            'paginator' => $this->private__paginator(500, true),
        ]);

        $view->assertSee('Showing 1–2 of 500+', false);
    }

    /**
     * Build a two-item paginator for the pagination component under both total modes.
     *
     * @param int $total The reported row count, rendered as a lower bound when truncated.
     * @param bool $truncated Whether the total is a lower bound that renders with a "+" suffix.
     */
    private function private__paginator(int $total, bool $truncated): SearchResultsPaginator
    {
        return (new SearchResultsPaginator([1, 2], $total, 2, 1, ['path' => '/horizon/jobs']))
            ->setResultsMayBeTruncated($truncated);
    }
}
