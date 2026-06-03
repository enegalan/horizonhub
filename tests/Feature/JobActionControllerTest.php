<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Services\Horizon\HorizonClientService;
use App\Services\Jobs\JobListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobActionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_list_filters_services_by_tag(): void
    {
        $matching = Service::create([
            'name' => 'svc-tagged',
            'base_url' => 'https://tagged.test',
            'status' => 'online',
            'tags' => ['production'],
        ]);
        Service::create([
            'name' => 'svc-other',
            'base_url' => 'https://other.test',
            'status' => 'online',
            'tags' => ['staging'],
        ]);

        $jobList = $this->createMock(JobListService::class);
        $jobList->expects($this->once())
            ->method('buildFailedJobsRetryModalPage')
            ->with(
                $this->callback(function ($services) use ($matching): bool {
                    return $services->count() === 1 && (int) $services->first()->id === (int) $matching->id;
                }),
                '',
                null,
                null,
                1,
                \PHP_INT_MAX,
            )
            ->willReturn(['rows' => [], 'total' => 0, 'last_page' => 1]);
        $this->app->instance(JobListService::class, $jobList);

        $this->getJson(route('horizon.jobs.failed', [
            'selection' => 'all',
            'service_tag' => ['production'],
        ]))->assertOk();
    }

    public function test_failed_list_returns_empty_meta_when_no_services_match(): void
    {
        $jobList = $this->createMock(JobListService::class);
        $this->app->instance(JobListService::class, $jobList);

        $response = $this->getJson(route('horizon.jobs.failed'));
        $response->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_failed_list_selection_all_returns_compact_jobs_shape(): void
    {
        $service = Service::create(['name' => 'svc', 'base_url' => 'https://x.test', 'status' => 'online']);
        $jobList = $this->createMock(JobListService::class);
        $jobList->method('buildFailedJobsRetryModalPage')->willReturn([
            'rows' => [
                ['uuid' => 'u1', 'service_id' => $service->id],
                ['uuid' => 'u2', 'service_id' => $service->id],
            ],
            'total' => 2,
            'last_page' => 1,
        ]);
        $this->app->instance(JobListService::class, $jobList);

        $response = $this->getJson(route('horizon.jobs.failed', ['selection' => 'all', 'service_ids' => [$service->id]]));
        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('jobs.0.id', 'u1')
            ->assertJsonPath('jobs.0.service_id', $service->id);
    }

    public function test_failed_list_selection_all_returns_empty_jobs_when_no_service_matches(): void
    {
        $this->app->instance(JobListService::class, $this->createMock(JobListService::class));

        $response = $this->getJson(route('horizon.jobs.failed', ['selection' => 'all']));
        $response->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('jobs', []);
    }

    public function test_retry_and_retry_batch_handle_success_and_service_missing_cases(): void
    {
        $service = Service::create(['name' => 'svc', 'base_url' => 'https://x.test', 'status' => 'online']);
        $api = $this->createMock(HorizonClientService::class);
        $api->method('retryJob')->willReturnCallback(function ($svc, $uuid) {
            if ($uuid === 'u-2') {
                return ['success' => false, 'message' => 'retry failed'];
            }

            return ['success' => true];
        });
        $this->app->instance(HorizonClientService::class, $api);
        $this->app->instance(JobListService::class, $this->createMock(JobListService::class));

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
        $service = Service::create(['name' => 'svc-retry-fail', 'base_url' => 'https://retry-fail.test', 'status' => 'online']);
        $api = $this->createMock(HorizonClientService::class);
        $api->method('retryJob')->willReturn(['success' => false, 'message' => 'api fail']);
        $this->app->instance(HorizonClientService::class, $api);
        $this->app->instance(JobListService::class, $this->createMock(JobListService::class));

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
        $service = Service::create(['name' => 'svc2', 'base_url' => 'https://x2.test', 'status' => 'online']);
        $api = $this->createMock(HorizonClientService::class);
        $api->method('retryJob')->willReturn([
            'success' => false,
            'message' => 'gateway issue',
            'status' => 502,
        ]);
        $this->app->instance(HorizonClientService::class, $api);
        $this->app->instance(JobListService::class, $this->createMock(JobListService::class));

        $this->postJson(route('horizon.jobs.retry'), [
            'uuid' => 'u-fail',
            'service_id' => $service->id,
        ])->assertStatus(502)->assertJsonPath('message', 'gateway issue');
    }
}
