<?php

namespace Tests\Unit;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServiceWithHorizonStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_attach_horizon_stats_fetches_stats_for_enabled_services(): void
    {
        $service = Service::create(['name' => 'a', 'base_url' => 'https://a.test', 'status' => 'online']);

        Http::fake([
            'https://a.test/horizon/api/stats' => Http::response([
                'failedJobs' => 4,
                'recentJobs' => 9,
                'status' => 'active',
            ], 200),
        ]);

        $service->withHorizonStats();

        $this->assertSame(4, $service->horizon_failed_jobs_count);
        $this->assertSame(9, $service->horizon_jobs_count);
        $this->assertSame('active', $service->horizon_status);
    }

    public function test_attach_horizon_stats_skips_disabled_services(): void
    {
        $disabled = Service::create([
            'name' => 'disabled-svc',
            'base_url' => 'https://disabled.test',
            'status' => 'online',
            'enabled' => false,
        ]);

        Http::fake(['*' => Http::response([], 200)]);

        $disabled->withHorizonStats();
        Http::assertNothingSent();

        $this->assertSame(0, $disabled->horizon_failed_jobs_count);
        $this->assertSame(0, $disabled->horizon_jobs_count);
        $this->assertNull($disabled->horizon_status);
    }

    public function test_service_rejects_empty_base_url_on_save(): void
    {
        $this->expectException(ValidationException::class);

        Service::create(['name' => 'no-url', 'base_url' => '', 'status' => 'online']);
    }
}
