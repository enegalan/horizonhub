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

    public function test_truncated_totals_explain_the_scan_cap(): void
    {
        config()->set('horizonhub.job_list_scan_cap', 200);

        $view = $this->view('components.pagination', [
            'paginator' => $this->private__paginator(370, true),
        ]);

        $view->assertSee('Showing 1–2 of 370+', false);
        $view->assertSee('Lower bound (370+)', false);
        $view->assertSee('at most 200 jobs per service', false);
        $view->assertSee('Across several services', false);
    }

    public function test_truncated_totals_keep_the_plus_suffix_when_the_current_page_slice_is_empty(): void
    {
        $paginator = (new SearchResultsPaginator([], 370, 2, 200, ['path' => '/horizon/jobs']))
            ->setResultsMayBeTruncated(true);

        $view = $this->view('components.pagination', [
            'paginator' => $paginator,
        ]);

        $view->assertSee('Showing 370+ items', false);
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
