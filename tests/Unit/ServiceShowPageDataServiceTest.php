<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Horizon\HorizonJobListService;
use App\Services\Horizon\HorizonMetricsService;
use App\Services\Horizon\ServiceShowPageDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ServiceShowPageDataServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_collects_stats_supervisors_workload_and_paginators(): void
    {
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://svc.test', 'status' => 'online']);
        $request = Request::create('/horizon/services/' . $service->id, 'GET', ['search' => 'job-x']);

        $metrics = $this->createMock(HorizonMetricsService::class);
        $metrics->method('getJobsPastMinute')->willReturn(2);
        $metrics->method('getJobsPastHour')->willReturn(20);
        $metrics->method('getFailedPastSevenDays')->willReturn(1);
        $metrics->method('getWorkloadForService')->willReturn([
            ['queue' => 'default', 'jobs' => 5, 'processes' => 2, 'wait' => 1.2],
        ]);

        $paginator = new LengthAwarePaginator([], 0, 15, 1);
        $jobList = $this->createMock(HorizonJobListService::class);
        $jobList->method('buildServiceStatusPaginators')->willReturn([
            'processing' => $paginator,
            'processed' => $paginator,
            'failed' => $paginator,
        ]);

        $horizonApi = $this->createMock(HorizonApiProxyService::class);
        $horizonApi->method('getStats')->willReturn([
            'success' => true,
            'data' => [
                'status' => 'running',
                'processes' => 3,
                'wait' => ['default' => 5.0, 'emails' => 0],
                'queueWithMaxRuntime' => 'default',
                'queueWithMaxThroughput' => 'emails',
            ],
        ]);
        $horizonApi->method('getMasters')->willReturn([
            'success' => true,
            'data' => [[
                'supervisors' => [[
                    'name' => 'prod:supervisor-1',
                    'status' => 'running',
                    'processes' => [1, 2],
                    'options' => ['connection' => 'redis', 'queue' => ['default', 'emails'], 'balance' => 'auto'],
                ]],
            ]],
        ]);

        $data = (new ServiceShowPageDataService($metrics, $jobList))->build($service, $request, $horizonApi);

        $this->assertSame(2, $data['jobsPastMinute']);
        $this->assertSame(20, $data['jobsPastHour']);
        $this->assertSame(1, $data['failedPastSevenDays']);
        $this->assertSame('running', $data['horizonStatus']);
        $this->assertSame(3, $data['totalProcesses']);
        $this->assertSame(5.0, $data['maxWaitTimeSeconds']);
        $this->assertSame('job-x', $data['filters']['search']);
        $this->assertCount(1, $data['supervisors']);
        $this->assertCount(1, $data['workloadQueues']);
    }
}
