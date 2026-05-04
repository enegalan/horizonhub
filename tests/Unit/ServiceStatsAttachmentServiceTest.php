<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Horizon\ServiceStatsAttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceStatsAttachmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attach_horizon_stats_handles_missing_base_url_and_success_payload(): void
    {
        $serviceWithBaseUrl = Service::query()->create(['name' => 'a', 'base_url' => 'https://a.test', 'status' => 'online']);
        $serviceNoBaseUrl = Service::query()->create(['name' => 'b', 'base_url' => '', 'status' => 'online']);

        $api = $this->createMock(HorizonApiProxyService::class);
        $api->method('getStats')->willReturn([
            'success' => true,
            'data' => ['failedJobs' => 4, 'recentJobs' => 9, 'status' => 'active'],
        ]);

        (new ServiceStatsAttachmentService)->attachHorizonStats([$serviceWithBaseUrl, $serviceNoBaseUrl], $api);

        $this->assertSame(4, $serviceWithBaseUrl->horizon_failed_jobs_count);
        $this->assertSame(9, $serviceWithBaseUrl->horizon_jobs_count);
        $this->assertSame('active', $serviceWithBaseUrl->horizon_status);

        $this->assertSame(0, $serviceNoBaseUrl->horizon_failed_jobs_count);
        $this->assertSame(0, $serviceNoBaseUrl->horizon_jobs_count);
        $this->assertNull($serviceNoBaseUrl->horizon_status);
    }
}
