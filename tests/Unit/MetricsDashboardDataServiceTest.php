<?php

namespace Tests\Unit;

use App\Services\Horizon\HorizonMetricsService;
use App\Services\Horizon\MetricsDashboardDataService;
use Tests\TestCase;

class MetricsDashboardDataServiceTest extends TestCase
{
    public function test_build_returns_chart_data_and_summaries(): void
    {
        $metrics = $this->createMock(HorizonMetricsService::class);
        $metrics->expects($this->once())
            ->method('getThroughputTotalsForServiceIds')
            ->with([])
            ->willReturn([
                'jobsPastMinute' => 1,
                'jobsPastHour' => 2,
                'failedPastSevenDays' => 3,
            ]);
        $metrics->expects($this->once())
            ->method('getFailureRate24h')
            ->with([])
            ->willReturn(['rate' => 1.5, 'processed' => 100, 'failed' => 2]);
        $metrics->expects($this->once())
            ->method('getJobRuntimesLast24h')
            ->with([])
            ->willReturn(['points' => []]);
        $metrics->expects($this->once())
            ->method('getFailureRateOverTime')
            ->with([])
            ->willReturn(['xAxis' => [], 'rate' => []]);
        $metrics->expects($this->once())
            ->method('getJobsVolumeLast24h')
            ->with([])
            ->willReturn(['xAxis' => [], 'completed' => [], 'failed' => []]);
        $metrics->expects($this->once())
            ->method('getWorkloadData')
            ->with([])
            ->willReturn([]);
        $metrics->expects($this->once())
            ->method('getSupervisorsData')
            ->with([])
            ->willReturn([]);
        $metrics->expects($this->once())
            ->method('getWaitByQueueChartData')
            ->with([])
            ->willReturn(null);
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
        $this->assertTrue($out['hasRuntimeChart']);
        $this->assertTrue($out['hasFailureRateChart']);
        $this->assertTrue($out['hasJobsVolumeChart']);
    }
}
