<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\Service;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use App\Services\Dashboard\DashboardDataService;
use App\Services\Horizon\HorizonClientService;
use App\Services\Metrics\MetricsDataService;
use App\Services\Services\ServiceStatsAttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DashboardDataServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_merges_metrics_with_service_health_and_recent_alert_logs(): void
    {
        $online = Service::query()->create(['name' => 'online-svc', 'base_url' => 'https://online.test', 'status' => 'online']);
        $offline = Service::query()->create(['name' => 'offline-svc', 'base_url' => 'https://offline.test', 'status' => 'offline']);
        $standBy = Service::query()->create(['name' => 'standby-svc', 'base_url' => 'https://standby.test', 'status' => 'stand_by']);

        $alert = Alert::query()->create([
            'name' => 'dash-alert',
            'rule_type' => FailureCount::type(),
            'enabled' => true,
        ]);

        foreach ([$online, $offline, $standBy] as $index => $service) {
            AlertLog::query()->create([
                'alert_id' => $alert->id,
                'service_id' => $service->id,
                'status' => 'sent',
                'trigger_count' => 1,
                'sent_at' => now()->subMinutes($index),
            ]);
        }

        $metrics = $this->createMock(MetricsDataService::class);
        $metrics->expects($this->once())
            ->method('buildMetricsDashboardData')
            ->with([])
            ->willReturn([
                'jobsPastMinute' => 4,
                'workloadRows' => [['service' => 'online-svc', 'queue' => 'default', 'jobs' => 1]],
            ]);

        $serviceStats = $this->createMock(ServiceStatsAttachmentService::class);
        $serviceStats->expects($this->once())
            ->method('attachHorizonStats')
            ->with(
                $this->callback(static function (Collection $services): bool {
                    return $services->count() === 3;
                }),
                $this->isInstanceOf(HorizonClientService::class),
            );

        $horizonApi = $this->createMock(HorizonClientService::class);
        $sut = new DashboardDataService($metrics, $serviceStats);
        $result = $sut->build($horizonApi);

        $this->assertSame(4, $result['jobsPastMinute']);
        $this->assertSame(1, $result['servicesOnlineCount']);
        $this->assertSame(3, $result['servicesTotal']);
        $this->assertSame('bg-orange-500', $result['servicesHealthDotClass']);
        $this->assertCount(3, $result['recentAlertLogs']);
        $this->assertSame($online->id, $result['recentAlertLogs'][0]->service_id);
    }

    public function test_build_uses_neutral_health_dot_when_no_services_exist(): void
    {
        $metrics = $this->createMock(MetricsDataService::class);
        $metrics->expects($this->once())
            ->method('buildMetricsDashboardData')
            ->with([])
            ->willReturn([
                'workloadRows' => [],
            ]);

        $serviceStats = $this->createMock(ServiceStatsAttachmentService::class);
        $serviceStats->expects($this->once())
            ->method('attachHorizonStats');

        $sut = new DashboardDataService($metrics, $serviceStats);
        $result = $sut->build($this->createMock(HorizonClientService::class));

        $this->assertSame('bg-slate-400', $result['servicesHealthDotClass']);
        $this->assertSame(0, $result['servicesTotal']);
        $this->assertSame(0, $result['servicesOnlineCount']);
    }
}
