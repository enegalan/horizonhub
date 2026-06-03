<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Services\Alerts\Engine\AlertEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluate_alerts_command_calls_engine_and_returns_success(): void
    {
        $engine = $this->createMock(AlertEngine::class);
        $engine->expects($this->once())->method('evaluateScheduled');
        $this->app->instance(AlertEngine::class, $engine);

        $this->artisan('hh:evaluate-alerts')->assertSuccessful();
    }

    public function test_mark_stale_services_offline_command_updates_status_by_thresholds(): void
    {
        config()->set('horizonhub.stale_service_minutes', 30);
        config()->set('horizonhub.dead_service_minutes', 60);

        $standBy = Service::create([
            'name' => 'standby',
            'base_url' => 'https://standby.test',
            'status' => 'online',
            'last_seen_at' => now()->subMinutes(35),
        ]);
        $offline = Service::create([
            'name' => 'offline',
            'base_url' => 'https://offline.test',
            'status' => 'online',
            'last_seen_at' => now()->subMinutes(70),
        ]);

        $this->artisan('hh:mark-stale-services-offline')->assertSuccessful();

        $standBy->refresh();
        $offline->refresh();
        $this->assertSame('stand_by', $standBy->status);
        $this->assertSame('offline', $offline->status);
    }
}
