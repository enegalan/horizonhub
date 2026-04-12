<?php

namespace Tests\Unit;

use App\Services\Horizon\HorizonMetricsService;
use App\Services\Horizon\MetricsDashboardDataService;
use Mockery;
use Tests\TestCase;

class MetricsDashboardDataServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_build_returns_chart_data_and_summaries(): void
    {
        $metrics = Mockery::mock(HorizonMetricsService::class);
        $metrics->shouldReceive('getThroughputTotalsForServiceIds')->once()->with([])->andReturn([
            'jobsPastMinute' => 1,
            'jobsPastHour' => 2,
            'failedPastSevenDays' => 3,
        ]);
        $metrics->shouldReceive('getFailureRate24h')->once()->with([])->andReturn(['rate' => 1.5, 'processed' => 100, 'failed' => 2]);
        $metrics->shouldReceive('getJobRuntimesLast24h')->once()->with([])->andReturn(['points' => []]);
        $metrics->shouldReceive('getFailureRateOverTime')->once()->with([])->andReturn(['xAxis' => [], 'rate' => []]);
        $metrics->shouldReceive('getJobsVolumeLast24h')->once()->with([])->andReturn(['xAxis' => [], 'completed' => [], 'failed' => []]);
        $metrics->shouldReceive('getWorkloadData')->once()->with([])->andReturn([]);
        $metrics->shouldReceive('getSupervisorsData')->once()->with([])->andReturn([]);
        $metrics->shouldReceive('getWaitByQueueChartData')->once()->with([])->andReturn(null);

        $sut = new MetricsDashboardDataService($metrics);
        $out = $sut->build([]);

        $this->assertSame(1, $out['jobsPastMinute']);
        $this->assertSame(2, $out['jobsPastHour']);
        $this->assertSame(3, $out['failedPastSevenDays']);
        $this->assertArrayHasKey('metricsChartData', $out);
        $this->assertArrayHasKey('jobsVolumeLast24h', $out['metricsChartData']);
        $this->assertStringContainsString('queue(s)', $out['workloadSummary']);
        $this->assertStringContainsString('supervisor(s)', $out['supervisorsSummary']);
        $this->assertFalse($out['hasServiceChart']);
    }
}
