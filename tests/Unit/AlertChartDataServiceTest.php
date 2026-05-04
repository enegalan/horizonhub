<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\Service;
use App\Services\Alerts\AlertChartDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlertChartDataServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_chart_uses_mysql_date_format_function(): void
    {
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://x.test', 'status' => 'online']);
        $alert = Alert::query()->create(['name' => 'chart', 'rule_type' => Alert::RULE_FAILURE_COUNT, 'enabled' => true]);

        AlertLog::query()->create([
            'alert_id' => $alert->id,
            'service_id' => $service->id,
            'trigger_count' => 1,
            'status' => 'sent',
            'sent_at' => now()->subHours(2),
        ]);
        AlertLog::query()->create([
            'alert_id' => $alert->id,
            'service_id' => $service->id,
            'trigger_count' => 1,
            'status' => 'failed',
            'sent_at' => now()->subHours(2),
        ]);

        if (DB::getDriverName() === 'sqlite') {
            $this->expectException(\Illuminate\Database\QueryException::class);
        }

        $serviceObj = new AlertChartDataService;
        $chart = $serviceObj->buildChart($alert, 1);
        $this->assertArrayHasKey('xAxis', $chart);
    }
}
