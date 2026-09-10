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
    }
}
