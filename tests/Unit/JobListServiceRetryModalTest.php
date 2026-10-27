<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Jobs\JobListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobListServiceRetryModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_failed_jobs_retry_modal_page_flags_incomplete_scans(): void
    {
        config()->set('horizonhub.job_list_chunk', 2);
        config()->set('horizonhub.job_list_scan_cap', 2);

        $page = 0;
        Http::fake(function ($request) use (&$page) {
            if (! str_contains($request->url(), '/jobs/failed')) {
                return Http::response('unexpected', 500);
            }

            $page++;

            if ($page === 1) {
                return Http::response(['jobs' => [
                    ['id' => 'job-1', 'queue' => 'default', 'name' => 'J1', 'failed_at' => '2024-06-01 14:00:00'],
                    ['id' => 'job-2', 'queue' => 'default', 'name' => 'J2', 'failed_at' => '2024-06-01 13:00:00'],
                ]], 200);
            }

            return Http::response(['jobs' => [
                ['id' => 'job-3', 'queue' => 'default', 'name' => 'J3', 'failed_at' => '2024-06-01 12:00:00'],
                ['id' => 'job-4', 'queue' => 'default', 'name' => 'J4', 'failed_at' => '2024-06-01 11:00:00'],
            ]], 200);
        });

        $svc = Service::create([
            'name' => 'svc-retry-modal-truncated',
            'base_url' => 'https://svc-retry-truncated.test',
            'status' => 'online',
        ]);

        $result = JobListService::buildFailedJobsRetryModalPage(new Collection([$svc]), '', null, null, 1, 10);

        $this->assertSame(2, $result['total']);
        $this->assertTrue($result['results_may_be_truncated']);
        $this->assertSame(['job-1', 'job-2'], \array_column($result['rows'], 'uuid'));
    }

    public function test_build_failed_jobs_retry_modal_page_slice_matches_full_total(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/jobs/failed')) {
                return Http::response(['jobs' => [
                    [
                        'id' => 'job-1',
                        'queue' => 'default',
                        'name' => 'J1',
                        'failed_at' => '2024-06-01 12:00:00',
                    ],
                    [
                        'id' => 'job-2',
                        'queue' => 'default',
                        'name' => 'J2',
                        'failed_at' => '2024-06-01 13:00:00',
                    ],
                    [
                        'id' => 'job-3',
                        'queue' => 'default',
                        'name' => 'J3',
                        'failed_at' => '2024-06-01 14:00:00',
                    ],
                ]], 200);
            }

            return Http::response('unexpected', 500);
        });

        $svc = Service::create([
            'name' => 'svc-retry-modal-page',
            'base_url' => 'https://svc-retry-page.test',
            'status' => 'online',
        ]);

        $services = new Collection([$svc]);

        $page = JobListService::buildFailedJobsRetryModalPage($services, '', null, null, 1, 1);
        $all = JobListService::buildFailedJobsRetryModalPage($services, '', null, null, 1, \PHP_INT_MAX);

        $this->assertSame(3, $page['total']);
        $this->assertCount(1, $page['rows']);
        $this->assertSame('job-3', $page['rows'][0]['uuid']);
        $this->assertCount(3, $all['rows']);
        $this->assertFalse($page['results_may_be_truncated']);
        $this->assertFalse($all['results_may_be_truncated']);
    }

    public function test_build_failed_jobs_retry_modal_page_with_max_per_page_returns_all_rows(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/jobs/failed')) {
                return Http::response(['jobs' => [
                    [
                        'id' => 'job-a',
                        'queue' => 'default',
                        'name' => 'JobA',
                        'failed_at' => '2024-06-01 10:00:00',
                    ],
                    [
                        'id' => 'job-b',
                        'queue' => 'default',
                        'name' => 'JobB',
                        'failed_at' => '2024-06-01 11:00:00',
                    ],
                ]], 200);
            }

            return Http::response('unexpected', 500);
        });

        $svc = Service::create([
            'name' => 'svc-retry-modal-batch',
            'base_url' => 'https://svc-retry-modal.test',
            'status' => 'online',
        ]);

        $services = new Collection([$svc]);

        $all = JobListService::buildFailedJobsRetryModalPage($services, '', null, null, 1, \PHP_INT_MAX);

        $this->assertSame(2, $all['total']);
        $this->assertCount(2, $all['rows']);
        $this->assertSame('job-b', $all['rows'][0]['uuid']);
        $this->assertSame('job-a', $all['rows'][1]['uuid']);
        $this->assertFalse($all['results_may_be_truncated']);
    }
}
