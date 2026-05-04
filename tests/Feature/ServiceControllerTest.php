<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Horizon\ServiceShowPageDataService;
use App\Services\Horizon\ServiceStatsAttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_edit_show_store_update_destroy_and_connection_paths(): void
    {
        $service = Service::query()->create([
            'name' => 'svc-a',
            'base_url' => 'https://svc-a.test',
            'public_url' => 'https://public-a.test',
            'status' => 'offline',
        ]);

        $stats = $this->createMock(ServiceStatsAttachmentService::class);
        $stats->expects($this->once())->method('attachHorizonStats');
        $this->app->instance(ServiceStatsAttachmentService::class, $stats);

        $showData = $this->createMock(ServiceShowPageDataService::class);
        $showData->method('build')->willReturn([
            'jobsPastMinute' => 0,
            'jobsPastHour' => 0,
            'failedPastSevenDays' => 0,
            'totalProcesses' => null,
            'maxWaitTimeSeconds' => null,
            'queueWithMaxRuntime' => null,
            'queueWithMaxThroughput' => null,
            'horizonStatus' => null,
            'supervisorGroups' => collect(),
            'supervisors' => collect(),
            'workloadQueues' => collect(),
            'jobsProcessing' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15, 1),
            'jobsProcessed' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15, 1),
            'jobsFailed' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15, 1),
            'filters' => ['search' => ''],
        ]);
        $this->app->instance(ServiceShowPageDataService::class, $showData);

        $api = $this->createMock(HorizonApiProxyService::class);
        $api->method('ping')->willReturnOnConsecutiveCalls(
            ['success' => true],
            ['success' => false, 'message' => 'failed ping']
        );
        $this->app->instance(HorizonApiProxyService::class, $api);

        $this->get(route('horizon.services.index'))->assertOk();
        $this->get(route('horizon.services.edit', ['service' => $service]))->assertOk();
        $this->get(route('horizon.services.show', ['service' => $service]))->assertOk();

        $this->post(route('horizon.services.store'), [
            'name' => 'svc-b',
            'base_url' => 'https://svc-b.test/',
            'public_url' => 'https://public-b.test/',
        ])->assertRedirect(route('horizon.services.index'));
        $this->assertDatabaseHas('services', ['name' => 'svc-b', 'base_url' => 'https://svc-b.test', 'public_url' => 'https://public-b.test']);

        $this->put(route('horizon.services.update', ['service' => $service]), [
            'name' => 'svc-a-updated',
            'base_url' => 'https://svc-a-updated.test/',
            'public_url' => '',
        ])->assertRedirect(route('horizon.services.index'));

        $this->post(route('horizon.services.test-connection', ['service' => $service]))->assertRedirect();
        $service->refresh();
        $this->assertSame('online', $service->status);

        $this->post(route('horizon.services.test-connection', ['service' => $service]))->assertRedirect();
        $service->refresh();
        $this->assertSame('offline', $service->status);

        $this->delete(route('horizon.services.destroy', ['service' => $service]))->assertRedirect(route('horizon.services.index'));
        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }
}
