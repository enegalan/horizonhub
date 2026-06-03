<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Horizon\HorizonClientService;
use App\Services\Services\ServiceStatsAttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServiceStatsAttachmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attach_horizon_stats_fetches_stats_for_enabled_services(): void
    {
        $service = Service::query()->create(['name' => 'a', 'base_url' => 'https://a.test', 'status' => 'online']);

        $api = $this->createMock(HorizonClientService::class);
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

        $api = $this->createMock(HorizonClientService::class);
        $api->expects($this->never())->method('getStats');

        (new ServiceStatsAttachmentService)->attachHorizonStats([$disabled], $api);

        $this->assertSame(0, $disabled->horizon_failed_jobs_count);
        $this->assertSame(0, $disabled->horizon_jobs_count);
        $this->assertNull($disabled->horizon_status);
    }

    public function test_service_rejects_empty_base_url_on_save(): void
    {
        $this->expectException(ValidationException::class);

        Service::query()->create(['name' => 'no-url', 'base_url' => '', 'status' => 'online']);
    }
}
