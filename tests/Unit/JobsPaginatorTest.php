<?php

namespace Tests\Unit;

use App\Support\Jobs\JobsPaginator;
use Tests\TestCase;

class JobsPaginatorTest extends TestCase
{
    public function test_fetch_filtered_only_returns_matching_jobs(): void
    {
        config()->set('horizonhub.horizon_api_job_list_page_size', 2);
        config()->set('horizonhub.max_horizon_pages', 5);

        $requests = 0;
        $result = JobsPaginator::fetchFiltered(
            $this->private__pageFetcher($this->private__jobs(5), $requests),
            'job-4',
        );

        $this->assertTrue($result['complete']);
        $this->assertSame(3, $requests);
        $this->assertSame(['job-4'], \array_column($result['jobs'], 'id'));
    }

    public function test_fetch_filtered_reads_the_whole_window_when_max_matches_is_not_reached(): void
    {
        config()->set('horizonhub.horizon_api_job_list_page_size', 2);
        config()->set('horizonhub.max_horizon_pages', 5);

        $requests = 0;
        $result = JobsPaginator::fetchFiltered(
            function (array $query) use (&$requests): array {
                $requests++;

                return $this->private__response($this->private__jobs(5), $query['starting_at'] + 1);
            },
            'job-4',
            3,
        );

        $this->assertTrue($result['complete']);
        $this->assertCount(1, $result['jobs']);
        $this->assertSame(3, $requests);
    }

    public function test_fetch_filtered_stops_paginating_once_max_matches_are_collected(): void
    {
        config()->set('horizonhub.horizon_api_job_list_page_size', 2);
        config()->set('horizonhub.max_horizon_pages', 5);

        $requests = 0;
        $result = JobsPaginator::fetchFiltered(
            function (array $query) use (&$requests): array {
                $requests++;

                return $this->private__response($this->private__jobs(5), $query['starting_at'] + 1);
            },
            'redis',
            3,
        );

        $this->assertFalse($result['complete']);
        $this->assertCount(3, $result['jobs']);
        $this->assertSame(2, $requests);
    }

    public function test_fetch_filtered_treats_non_positive_max_matches_as_unlimited(): void
    {
        config()->set('horizonhub.horizon_api_job_list_page_size', 2);
        config()->set('horizonhub.max_horizon_pages', 5);

        $result = JobsPaginator::fetchFiltered(
            $this->private__pageFetcher($this->private__jobs(5)),
            'redis',
            0,
        );

        $this->assertTrue($result['complete']);
        $this->assertCount(5, $result['jobs']);
    }

    /**
     * Build synthetic job rows for the upstream page fetcher.
     *
     * @param int $count How many jobs to generate.
     *
     * @return list<array<string, mixed>>
     */
    private function private__jobs(int $count): array
    {
        return \array_map(static fn (int $index): array => [
            'id' => 'job-' . $index,
            'queue' => 'redis.default',
            'name' => 'App\\Jobs\\Job' . $index,
        ], \range(0, $count - 1));
    }

    /**
     * Build a stub upstream page fetcher that serves the given jobs.
     *
     * The returned closure stands in for the Horizon API page fetch, so the tests
     * can count how many pages the paginator requested.
     *
     * @param list<array<string, mixed>> $jobs The jobs.
     * @param int|null $requests Incremented on every upstream page request.
     *
     * @return callable(array<string, mixed>): array{success: bool, data: array{jobs: list<array<string, mixed>>}}
     */
    private function private__pageFetcher(array $jobs, ?int &$requests = null): callable
    {
        return function (array $query) use ($jobs, &$requests): array {
            if ($requests !== null) {
                $requests++;
            }

            return $this->private__response($jobs, $query['starting_at'] + 1);
        };
    }

    /**
     * Build one successful upstream page response starting at the given offset.
     *
     * The page size comes from `horizonhub.horizon_api_job_list_page_size`, so the
     * paginator sees the same page size it would get from the real API.
     *
     * @param list<array<string, mixed>> $jobs The jobs.
     * @param int $offset Index of the first job to include in the page.
     *
     * @return array{success: bool, data: array{jobs: list<array<string, mixed>>}}
     */
    private function private__response(array $jobs, int $offset = 0): array
    {
        return [
            'success' => true,
            'data' => [
                'jobs' => \array_slice($jobs, $offset, config('horizonhub.horizon_api_job_list_page_size')),
            ],
        ];
    }
}
