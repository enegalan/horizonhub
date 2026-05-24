<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Horizon\ServiceStatsAttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServiceStatsAttachmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attach_horizon_stats_fetches_stats_for_enabled_services(): void
    {
        $service = Service::query()->create(['name' => 'a', 'base_url' => 'https://a.test', 'status' => 'online']);

        $api = $this->createMock(HorizonApiProxyService::class);
        $api->method('getStats')->willReturn([
            'success' => true,
            'data' => ['failedJobs' => 4, 'recentJobs' => 9, 'status' => 'active'],
        ]);

        (new ServiceStatsAttachmentService)->attachHorizonStats([$service], $api);

        $this->assertSame(4, $service->horizon_failed_jobs_count);
        $this->assertSame(9, $service->horizon_jobs_count);
        $this->assertSame('active', $service->horizon_status);
    }

    public function test_attach_horizon_stats_skips_disabled_services(): void
    {
        $disabled = Service::query()->create([
            'name' => 'disabled-svc',
            'base_url' => 'https://disabled.test',
            'status' => 'online',
            'enabled' => false,
        ]);

        $api = $this->createMock(HorizonApiProxyService::class);
        $api->expects($this->never())->method('getStats');

        (new ServiceStatsAttachmentService)->attachHorizonStats([$disabled], $api);

        $this->assertSame(0, $disabled->horizon_failed_jobs_count);
        $this->assertSame(0, $disabled->horizon_jobs_count);
        $this->assertNull($disabled->horizon_status);
    }

    public function test_build_list_summary_counts_groups_offline_and_stand_by(): void
    {
        $services = collect([
            Service::query()->create(['name' => 'online-svc', 'base_url' => 'https://online.test', 'status' => 'online']),
            Service::query()->create(['name' => 'offline-svc', 'base_url' => 'https://offline.test', 'status' => 'offline']),
            Service::query()->create(['name' => 'standby-svc', 'base_url' => 'https://standby.test', 'status' => 'stand_by']),
        ]);

        $counts = (new ServiceStatsAttachmentService)->buildListSummaryCounts($services);

        $this->assertSame([
            'total' => 3,
            'online' => 1,
            'offline' => 2,
        ], $counts);
    }

    public function test_service_rejects_empty_base_url_on_save(): void
    {
        $this->expectException(ValidationException::class);

        Service::query()->create(['name' => 'no-url', 'base_url' => '', 'status' => 'online']);
    }
}
