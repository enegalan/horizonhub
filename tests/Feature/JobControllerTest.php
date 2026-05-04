<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Horizon\HorizonJobDetailService;
use App\Services\Horizon\HorizonJobListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_with_aggregated_data_and_filters(): void
    {
        $service = Service::query()->create(['name' => 'svc-a', 'base_url' => 'https://a.test', 'status' => 'online']);
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15, 1);

        $jobList = $this->createMock(HorizonJobListService::class);
        $jobList->method('buildAggregatedJobsIndexFromRequest')->willReturn([
            'processing' => $paginator,
            'processed' => $paginator,
            'failed' => $paginator,
            'serviceFilterIds' => [$service->id],
            'search' => 'job',
        ]);
        $this->app->instance(HorizonJobListService::class, $jobList);

        $response = $this->get(route('horizon.jobs.index', ['search' => 'job', 'serviceFilter' => [$service->id]]));
        $response->assertOk();
    }

    public function test_show_returns_404_when_service_or_job_is_missing_and_renders_when_present(): void
    {
        $service = Service::query()->create(['name' => 'svc-b', 'base_url' => 'https://b.test', 'status' => 'online']);

        $api = $this->createMock(HorizonApiProxyService::class);
        $api->method('getJob')->willReturnOnConsecutiveCalls(
            ['success' => false],
            ['success' => true, 'data' => ['id' => 'job-1', 'name' => 'App\\Jobs\\Demo', 'payload' => ['foo' => 'bar']]]
        );
        $this->app->instance(HorizonApiProxyService::class, $api);

        $jobDetail = $this->createMock(HorizonJobDetailService::class);
        $jobDetail->method('buildShowViewData')->willReturn((object) [
            'uuid' => 'job-1',
            'name' => 'App\\Jobs\\Demo',
            'queue' => 'default',
            'status' => 'failed',
            'attempts' => 1,
            'retries' => 0,
            'runtime' => null,
            'queued_at' => null,
            'processed_at' => null,
            'failed_at' => null,
            'available_at' => null,
            'service' => $service,
            'exception' => null,
            'retried_by' => [],
            'payload' => ['foo' => 'bar'],
            'context' => ['ctx' => 1],
            'command_data' => ['delay' => null],
        ]);
        $this->app->instance(HorizonJobDetailService::class, $jobDetail);
        $this->app->instance(HorizonJobListService::class, $this->createMock(HorizonJobListService::class));

        $this->get(route('horizon.jobs.show', ['job' => 'x', 'service_id' => 99999]))->assertNotFound();
        $this->get(route('horizon.jobs.show', ['job' => 'x', 'service_id' => $service->id]))->assertNotFound();
        $this->get(route('horizon.jobs.show', ['job' => 'job-1', 'service_id' => $service->id]))->assertOk();
    }
}
