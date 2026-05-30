<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Horizon\HorizonClientService;
use App\Services\Jobs\JobListService;
use App\Services\Services\ServiceFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class JobListServiceCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_aggregated_and_service_paginators_and_retry_modal_page(): void
    {
        config()->set('horizonhub.jobs_per_page', 2);
        config()->set('horizonhub.max_horizon_pages', 2);
        config()->set('horizonhub.horizon_api_job_list_page_size', 2);

        $s1 = Service::query()->create(['name' => 'svc-a', 'base_url' => 'https://a.test', 'status' => 'online']);
        $s2 = Service::query()->create(['name' => 'svc-b', 'base_url' => 'https://b.test', 'status' => 'online']);

        $api = $this->createMock(HorizonClientService::class);
        $api->method('getPendingJobs')->willReturn([
            'success' => true,
            'data' => ['jobs' => [
                ['id' => 'p1', 'queue' => 'redis.default', 'name' => 'JobA', 'pushedAt' => now()->subMinute()->timestamp, 'index' => 1],
            ]],
        ]);
        $api->method('getCompletedJobs')->willReturn([
            'success' => true,
            'data' => ['jobs' => [
                ['id' => 'c1', 'queue' => 'redis.default', 'name' => 'JobC', 'pushedAt' => now()->subMinutes(2)->timestamp, 'completed_at' => now()->subMinute()->timestamp, 'index' => 1],
            ]],
        ]);
        $api->method('getFailedJobs')->willReturn([
            'success' => true,
            'data' => ['jobs' => [
                ['id' => 'f1', 'queue' => 'redis.failed', 'name' => 'JobF', 'failed_at' => now()->toIso8601String(), 'index' => 1],
            ]],
        ]);

        $service = new JobListService($api, new ServiceFilterService);

        $request = Request::create('/horizon/jobs', 'GET', [
            'search' => 'Job',
            'service_id' => [$s1->id, $s2->id],
            'page_processing' => 1,
            'page_processed' => 1,
            'page_failed' => 1,
        ]);

        $aggregated = $service->buildAggregatedJobsIndexFromRequest($request);
        $this->assertSame([$s1->id, $s2->id], $aggregated['serviceFilterIds']);
        $this->assertSame('Job', $aggregated['search']);
        $this->assertGreaterThanOrEqual(1, $aggregated['processing']->total());
        $this->assertGreaterThanOrEqual(1, $aggregated['processed']->total());
        $this->assertGreaterThanOrEqual(1, $aggregated['failed']->total());

        $singleService = $service->buildServiceStatusPaginators($s1, '', 1, 1, 1, 2, '/horizon/services/' . $s1->id, []);
        $this->assertGreaterThanOrEqual(1, $singleService['processing']->total());

        $retryModal = $service->buildFailedJobsRetryModalPage(collect([$s1, $s2]), 'JobF', now()->subHour()->toDateTimeString(), now()->addHour()->toDateTimeString(), 1, 10);
        $this->assertGreaterThanOrEqual(1, $retryModal['total']);
        $this->assertSame(1, $retryModal['last_page']);
        $this->assertArrayHasKey('uuid', $retryModal['rows'][0]);
    }
}
