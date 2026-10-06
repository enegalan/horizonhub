<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Jobs\JobListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobListServiceLargeSearchBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    private const JOB_COUNT = 1000;

    private const PAGE_SIZE = 50;

    private int $requests = 0;

    /**
     * Flush the cache so the aggregated jobs index is rebuilt from the fake on every scan.
     */
    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_dense_search_reads_fewer_pages_than_the_uncapped_scan(): void
    {
        $this->private__configurePaging();
        $service = $this->private__service();
        $this->private__fakeHorizon($service, $this->private__jobIds(self::JOB_COUNT));

        config()->set('horizonhub.job_search_match_cap', 0);
        $this->requests = 0;
        Cache::flush();
        $uncapped = JobListService::buildAggregatedJobsIndexFromRequest($this->private__request());
        $uncappedRequests = $this->requests;

        config()->set('horizonhub.job_search_match_cap', 500);
        $this->requests = 0;
        Cache::flush();
        $capped = JobListService::buildAggregatedJobsIndexFromRequest($this->private__request());
        $cappedRequests = $this->requests;

        $this->assertSame(20, $uncappedRequests, 'The uncapped scan must read the whole window.');
        $this->assertSame(1000, $uncapped['failed']->total());
        $this->assertFalse($uncapped['failed']->resultsMayBeTruncated());

        $this->assertSame(10, $cappedRequests, 'The capped scan must stop once the cap is filled.');
        $this->assertSame(500, $capped['failed']->total());
        $this->assertTrue($capped['failed']->resultsMayBeTruncated());

        $this->assertSame(
            $uncapped['failed']->getCollection()->take(500)->pluck('uuid')->values()->all(),
            $capped['failed']->getCollection()->pluck('uuid')->values()->all(),
            'Stopping early must return the same rows, in the same order, as the full scan.',
        );
    }

    public function test_selective_search_keeps_scanning_to_report_an_exact_total(): void
    {
        $this->private__configurePaging();
        config()->set('horizonhub.job_search_match_cap', 500);

        $service = $this->private__service();
        $this->private__fakeHorizon($service, $this->private__jobIds(1));
        $this->requests = 0;

        $result = JobListService::buildAggregatedJobsIndexFromRequest($this->private__request());

        $this->assertSame(20, $this->requests);
        $this->assertSame(1, $result['failed']->total());
        $this->assertSame('job-999-invoice', $result['failed']->first()->uuid);
        $this->assertFalse($result['failed']->resultsMayBeTruncated());
    }

    /**
     * Configure a paging window that reads the whole fixture in exactly `max_horizon_pages` requests.
     */
    private function private__configurePaging(): void
    {
        config()->set('horizonhub.horizon_api_job_list_page_size', self::PAGE_SIZE);
        config()->set('horizonhub.max_horizon_pages', (int) (self::JOB_COUNT / self::PAGE_SIZE));
        config()->set('horizonhub.jobs_per_page', 20);
    }

    /**
     * Fake the Horizon job list endpoints with a paged slice of the given job IDs.
     *
     * The fake answers every URL, but only `/jobs/failed` requests are counted in
     * `$this->requests` and served from the fixture; any other path returns an
     * empty job list.
     *
     * @param Service $service The service owning the endpoints; the fake matches all URLs, so this argument is never read.
     * @param list<string> $jobIds The job IDs to serve, ordered as Horizon returns them.
     */
    private function private__fakeHorizon(Service $service, array $jobIds): void
    {
        $pageSize = (int) config('horizonhub.horizon_api_job_list_page_size');

        Http::fake(function (Request $request) use ($jobIds, $pageSize) {
            if (! str_contains($request->url(), '/jobs/failed')) {
                return Http::response(['jobs' => [], 'total' => 0], 200);
            }

            $this->requests++;
            $offset = \max(0, (int) ($request->data()['starting_at'] ?? -1));

            $jobs = [];

            foreach (\array_slice($jobIds, $offset, $pageSize) as $index => $id) {
                $jobs[] = [
                    'id' => $id,
                    'queue' => 'redis.default',
                    'name' => \str_contains($id, 'invoice') ? 'App\\Jobs\\SendInvoice' : 'App\\Jobs\\SendReport',
                    'failed_at' => (string) (1717249200 - ($offset + $index)),
                    'index' => $offset + $index + 1,
                ];
            }

            return Http::response(['jobs' => $jobs, 'total' => \count($jobIds)], 200);
        });
    }

    /**
     * Build a fixture of `JOB_COUNT` job IDs whose trailing entries match the "Invoice" search.
     *
     * @param int $matchingCount The number of trailing IDs served as `App\Jobs\SendInvoice` jobs.
     *
     * @return list<string>
     */
    private function private__jobIds(int $matchingCount): array
    {
        $jobIds = [];
        $nonMatchingCount = self::JOB_COUNT - $matchingCount;

        for ($index = 0; $index < $nonMatchingCount; $index++) {
            $jobIds[] = 'job-' . $index . '-report';
        }

        for ($index = $nonMatchingCount; $index < self::JOB_COUNT; $index++) {
            $jobIds[] = 'job-' . $index . '-invoice';
        }

        return $jobIds;
    }

    /**
     * Build the synthetic jobs page request the benchmark scans with.
     *
     * The search is "Invoice" and all three job sections start on their first
     * page, so an aggregated scan covers every enabled service.
     */
    private function private__request(): HttpRequest
    {
        return HttpRequest::create('/horizon/jobs', 'GET', [
            'search' => 'Invoice',
            'page_processing' => 1,
            'page_processed' => 1,
            'page_failed' => 1,
        ]);
    }

    /**
     * Create the single enabled service the benchmark scans.
     */
    private function private__service(): Service
    {
        return Service::create([
            'name' => 'svc-benchmark',
            'base_url' => 'https://svc-benchmark.test',
            'status' => 'online',
        ]);
    }
}
