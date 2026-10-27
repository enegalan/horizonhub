<?php

namespace Tests\Unit;

use App\Support\Jobs\JobsPaginator;
use Tests\TestCase;

class JobsPaginatorTest extends TestCase
{
    public function test_fetch_filtered_caps_oversized_upstream_pages_to_the_configured_chunk(): void
    {
        config()->set('horizonhub.job_list_chunk', 25);
        config()->set('horizonhub.job_list_scan_cap', 200);

        $jobs = $this->private__jobs(500);
        $requests = 0;
        $result = JobsPaginator::fetchFiltered(
            function (array $query) use ($jobs, &$requests): array {
                $requests++;
                $offset = $query['starting_at'] + 1;

                return [
                    'success' => true,
                    'data' => [
                        'jobs' => \array_slice($jobs, $offset, 50),
                    ],
                ];
            },
            '',
        );

        $this->assertFalse($result['complete']);
        $this->assertSame(8, $requests);
        $this->assertCount(200, $result['jobs']);
        $this->assertSame('job-0', $result['jobs'][0]['id']);
        $this->assertSame('job-199', $result['jobs'][199]['id']);
    }

    public function test_fetch_filtered_marks_results_incomplete_when_scan_cap_pages_are_exhausted(): void
    {
        config()->set('horizonhub.job_list_chunk', 2);
        config()->set('horizonhub.job_list_scan_cap', 4);

        $result = JobsPaginator::fetchFiltered(
            $this->private__pageFetcher($this->private__jobs(6)),
            '',
        );

        $this->assertFalse($result['complete']);
        $this->assertCount(4, $result['jobs']);
    }

    public function test_fetch_filtered_marks_results_incomplete_when_the_source_fails(): void
    {
        config()->set('horizonhub.job_list_chunk', 2);
        config()->set('horizonhub.job_list_scan_cap', 10);

        $result = JobsPaginator::fetchFiltered(
            static fn (array $query): array => ['success' => false],
            '',
        );

        $this->assertFalse($result['complete']);
        $this->assertSame([], $result['jobs']);
    }

    public function test_fetch_filtered_only_returns_matching_jobs(): void
    {
        config()->set('horizonhub.job_list_chunk', 2);
        config()->set('horizonhub.job_list_scan_cap', 10);

        $requests = 0;
        $result = JobsPaginator::fetchFiltered(
            $this->private__pageFetcher($this->private__jobs(5), $requests),
            'job-4',
        );

        $this->assertTrue($result['complete']);
        $this->assertSame(3, $requests);
        $this->assertSame(['job-4'], \array_column($result['jobs'], 'id'));
    }

    public function test_fetch_filtered_stops_once_the_scan_cap_of_matches_is_collected(): void
    {
        config()->set('horizonhub.job_list_chunk', 2);
        config()->set('horizonhub.job_list_scan_cap', 3);

        $requests = 0;
        $result = JobsPaginator::fetchFiltered(
            function (array $query) use (&$requests): array {
                $requests++;

                return $this->private__response($this->private__jobs(5), $query['starting_at'] + 1);
            },
            'redis',
        );

        $this->assertFalse($result['complete']);
        $this->assertCount(3, $result['jobs']);
        $this->assertSame(2, $requests);
    }

    public function test_fetch_since_timestamp_trims_pages_to_the_remaining_scan_cap(): void
    {
        config()->set('horizonhub.job_list_chunk', 50);
        config()->set('horizonhub.job_list_scan_cap', 51);

        $since = 1_700_000_000;
        $jobs = \array_map(static fn (int $index): array => [
            'id' => 'job-' . $index,
            'completed_at' => $since + $index,
        ], \range(0, 99));

        $requests = 0;
        $result = JobsPaginator::fetchSinceTimestamp(
            $since,
            function (array $query) use ($jobs, &$requests): array {
                $requests++;

                return $this->private__response($jobs, $query['starting_at'] + 1);
            },
            static fn (array $job): int => (int) $job['completed_at'],
        );

        $this->assertSame(2, $requests);
        $this->assertCount(51, $result);
        $this->assertSame('job-0', $result[0]['id']);
        $this->assertSame('job-50', $result[50]['id']);
    }

    /**
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
     * Build a stub upstream page response that serves the given jobs.
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
                'jobs' => \array_slice($jobs, $offset, config('horizonhub.job_list_chunk')),
            ],
        ];
    }
}
