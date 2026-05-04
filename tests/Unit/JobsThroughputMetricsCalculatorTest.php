<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Metrics\JobsThroughputMetricsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobsThroughputMetricsCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_jobs_throughput_calculator_handles_service_and_global_paths(): void
    {
        $s1 = Service::query()->create(['name' => 'svc-a', 'base_url' => 'https://a.test', 'status' => 'online']);
        Service::query()->create(['name' => 'svc-b', 'base_url' => 'https://b.test', 'status' => 'online']);

        $api = $this->createMock(HorizonApiProxyService::class);
        $api->method('getStats')->willReturnCallback(function (Service $service) use ($s1) {
            if ($service->id === $s1->id) {
                return ['success' => true, 'data' => ['failedJobs' => 2, 'recentJobs' => 20, 'jobsPerMinute' => 3.4]];
            }

            return ['success' => true, 'data' => ['failedJobs' => 1, 'recentJobs' => 10, 'periods' => ['recentJobs' => 20]]];
        });

        $calc = new JobsThroughputMetricsCalculator($api);

        $this->assertSame(2, $calc->getFailedPastSevenDays($s1));
        $this->assertSame(3, $calc->getJobsPastMinute($s1));
        $this->assertSame(20, $calc->getJobsPastHour($s1));

        $globalFailed = $calc->getFailedPastSevenDays(null);
        $globalHour = $calc->getJobsPastHour(null);
        $globalMinute = $calc->getJobsPastMinute(null);
        $byService = $calc->getJobsPastHourByService();

        $this->assertSame(3, $globalFailed);
        $this->assertSame(30, $globalHour);
        $this->assertSame(4, $globalMinute);
        $this->assertCount(2, $byService['services']);
        $this->assertCount(2, $byService['jobsPastHour']);
    }
}
