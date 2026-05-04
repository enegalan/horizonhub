<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Metrics\JobsVolumeLast24hCalculator;
use App\Services\Metrics\RuntimeMetricsCalculator;
use App\Services\Metrics\WorkloadMetricsCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsCalculatorsCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_jobs_volume_last24h_returns_bucketed_series(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-30 12:00:00'));
        $service = Service::query()->create(['name' => 'svc-volume', 'base_url' => 'https://v.test', 'status' => 'online']);
        $since = now()->subHours(24)->getTimestamp();

        $api = $this->createMock(HorizonApiProxyService::class);
        $api->method('getCompletedJobs')->willReturn([
            'success' => true,
            'data' => ['jobs' => [['index' => 1, 'completed_at' => $since + 3600]]],
        ]);
        $api->method('getFailedJobs')->willReturn([
            'success' => true,
            'data' => ['jobs' => [['index' => 2, 'failed_at' => $since + 3600]]],
        ]);

        $calc = new JobsVolumeLast24hCalculator($api);
        $result = $calc->getJobsVolumeLast24h([$service->id]);
        $this->assertCount(25, $result['xAxis']);
        $this->assertSame(1, $result['completed'][1]);
        $this->assertSame(1, $result['failed'][1]);
        Carbon::setTestNow();
    }

    public function test_runtime_calculator_returns_completed_and_failed_points(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-30 12:00:00'));
        $service = Service::query()->create(['name' => 'svc-runtime-calc', 'base_url' => 'https://r.test', 'status' => 'online']);
        $since = now()->subHours(24)->getTimestamp();

        $api = $this->createMock(HorizonApiProxyService::class);
        $api->method('getCompletedJobs')->willReturn([
            'success' => true,
            'data' => ['jobs' => [[
                'index' => 1,
                'name' => 'App\\Jobs\\Done',
                'reserved_at' => $since + 100,
                'completed_at' => $since + 140,
            ]]],
        ]);
        $api->method('getFailedJobs')->willReturn([
            'success' => true,
            'data' => ['jobs' => [[
                'index' => 2,
                'name' => 'App\\Jobs\\Failed',
                'reserved_at' => $since + 200,
                'failed_at' => $since + 260,
            ]]],
        ]);

        $calc = new RuntimeMetricsCalculator($api);
        $result = $calc->getJobRuntimesLast24h([$service->id]);
        $this->assertCount(2, $result['points']);
        $this->assertSame('completed', $result['points'][0]['status']);
        $this->assertSame('failed', $result['points'][1]['status']);
        Carbon::setTestNow();
    }

    public function test_workload_calculator_builds_data_and_fallback_from_masters(): void
    {
        $serviceA = Service::query()->create(['name' => 'svc-a', 'base_url' => 'https://a.test', 'status' => 'online']);
        $serviceB = Service::query()->create(['name' => 'svc-b', 'base_url' => 'https://b.test', 'status' => 'online']);

        $api = $this->createMock(HorizonApiProxyService::class);
        $api->method('getWorkload')->willReturnCallback(function (Service $service): array {
            if ($service->name === 'svc-a') {
                return ['success' => true, 'data' => ['data' => [['name' => 'redis.default', 'length' => 3, 'processes' => 1, 'wait' => 0.4]]]];
            }

            return ['success' => true, 'data' => ['data' => []]];
        });
        $api->method('getMasters')->willReturnCallback(function (Service $service): array {
            if ($service->name === 'svc-a') {
                return ['success' => true, 'data' => [[
                    'supervisors' => [[
                        'name' => 'sup-a',
                        'processes' => [1, 2],
                        'options' => ['queue' => 'redis.default'],
                    ]],
                ]]];
            }

            return ['success' => true, 'data' => [[
                'supervisors' => [[
                    'name' => 'sup-b',
                    'options' => ['queue' => ['redis.fallback']],
                ]],
            ]]];
        });

        $calc = new WorkloadMetricsCalculator($api);
        $workload = $calc->getWorkloadData([]);
        $this->assertNotEmpty($workload);
        $this->assertSame('default', $workload[0]['queue']);

        $supervisors = $calc->getSupervisorsData([]);
        $this->assertCount(2, $supervisors);
        $this->assertSame('sup-a', $supervisors[0]['name']);
        $this->assertSame(3, $supervisors[0]['jobs']);
    }
}
