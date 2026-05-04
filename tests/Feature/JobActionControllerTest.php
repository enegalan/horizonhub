<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Horizon\HorizonJobListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobActionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_list_returns_empty_meta_when_no_services_match(): void
    {
        $jobList = $this->createMock(HorizonJobListService::class);
        $this->app->instance(HorizonJobListService::class, $jobList);

        $response = $this->getJson(route('horizon.jobs.failed'));
        $response->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_failed_list_selection_all_returns_compact_jobs_shape(): void
    {
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://x.test', 'status' => 'online']);
        $jobList = $this->createMock(HorizonJobListService::class);
        $jobList->method('buildFailedJobsRetryModalPage')->willReturn([
            'rows' => [
                ['uuid' => 'u1', 'service_id' => $service->id],
                ['uuid' => 'u2', 'service_id' => $service->id],
            ],
            'total' => 2,
            'last_page' => 1,
        ]);
        $this->app->instance(HorizonJobListService::class, $jobList);

        $response = $this->getJson(route('horizon.jobs.failed', ['selection' => 'all', 'service_id' => $service->id]));
        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('jobs.0.id', 'u1')
            ->assertJsonPath('jobs.0.service_id', $service->id);
    }

    public function test_failed_list_selection_all_returns_empty_jobs_when_no_service_matches(): void
    {
        $this->app->instance(HorizonJobListService::class, $this->createMock(HorizonJobListService::class));

        $response = $this->getJson(route('horizon.jobs.failed', ['selection' => 'all']));
        $response->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('jobs', []);
    }

    public function test_retry_and_retry_batch_handle_success_and_service_missing_cases(): void
    {
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://x.test', 'status' => 'online']);
        $api = $this->createMock(HorizonApiProxyService::class);
        $api->method('retryJob')->willReturnCallback(function ($svc, $uuid) {
            if ($uuid === 'u-2') {
                return ['success' => false, 'message' => 'retry failed'];
            }

            return ['success' => true];
        });
        $this->app->instance(HorizonApiProxyService::class, $api);
        $this->app->instance(HorizonJobListService::class, $this->createMock(HorizonJobListService::class));

        $this->postJson(route('horizon.jobs.retry'), [
            'uuid' => 'u-1',
            'service_id' => $service->id,
        ])->assertOk()->assertJsonPath('message', 'Retry requested');

        $this->postJson(route('horizon.jobs.retry-batch'), [
            'jobs' => [
                ['id' => 'u-1', 'service_id' => $service->id],
                ['id' => 'u-2', 'service_id' => $service->id],
            ],
        ])->assertOk()
            ->assertJsonPath('requested', 2)
            ->assertJsonPath('succeeded', 1)
            ->assertJsonPath('failed', 1)
            ->assertJsonPath('results.1.message', 'retry failed');
    }

    public function test_retry_batch_returns_failed_result_when_api_retry_fails(): void
    {
        $service = Service::query()->create(['name' => 'svc-retry-fail', 'base_url' => 'https://retry-fail.test', 'status' => 'online']);
        $api = $this->createMock(HorizonApiProxyService::class);
        $api->method('retryJob')->willReturn(['success' => false, 'message' => 'api fail']);
        $this->app->instance(HorizonApiProxyService::class, $api);
        $this->app->instance(HorizonJobListService::class, $this->createMock(HorizonJobListService::class));

        $this->postJson(route('horizon.jobs.retry-batch'), [
            'jobs' => [
                ['id' => 'u-retry-fail', 'service_id' => $service->id],
            ],
        ])->assertOk()
            ->assertJsonPath('requested', 1)
            ->assertJsonPath('succeeded', 0)
            ->assertJsonPath('failed', 1)
            ->assertJsonPath('results.0.message', 'api fail');
    }

    public function test_retry_requires_valid_payload_and_returns_422_for_invalid_data(): void
    {
        $response = $this->postJson(route('horizon.jobs.retry'), []);
        $response->assertStatus(422);
    }

    public function test_retry_returns_api_error_status_and_message_when_retry_fails(): void
    {
        $service = Service::query()->create(['name' => 'svc2', 'base_url' => 'https://x2.test', 'status' => 'online']);
        $api = $this->createMock(HorizonApiProxyService::class);
        $api->method('retryJob')->willReturn([
            'success' => false,
            'message' => 'gateway issue',
            'status' => 502,
        ]);
        $this->app->instance(HorizonApiProxyService::class, $api);
        $this->app->instance(HorizonJobListService::class, $this->createMock(HorizonJobListService::class));

        $this->postJson(route('horizon.jobs.retry'), [
            'uuid' => 'u-fail',
            'service_id' => $service->id,
        ])->assertStatus(502)->assertJsonPath('message', 'gateway issue');
    }
}
