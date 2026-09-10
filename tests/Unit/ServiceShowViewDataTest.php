<?php

namespace Tests\Unit;

use App\Http\Controllers\Stream\HorizonStreamsController;
use App\Models\Service;
use App\Services\Metrics\MetricsDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServiceShowViewDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_collects_stats_supervisors_workload_and_paginators(): void
    {
        $service = Service::create(['name' => 'svc', 'base_url' => 'https://svc.test', 'status' => 'online']);
        $request = Request::create('/horizon/services/' . $service->id, 'GET', ['search' => 'job-x']);

        $metrics = $this->createMock(MetricsDataService::class);
        $metrics->method('getJobsPastMinute')->willReturn(2);
        $metrics->method('getJobsPastHour')->willReturn(20);
        $metrics->method('getFailedPastSevenDays')->willReturn(1);
        $metrics->method('getWorkloadForService')->willReturn([
            ['queue' => 'default', 'jobs' => 5, 'processes' => 2, 'wait' => 1.2],
        ]);
        $this->app->instance(MetricsDataService::class, $metrics);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/horizon/api/stats')) {
                return Http::response([
                    'status' => 'running',
                    'processes' => 3,
                    'wait' => ['default' => 5.0, 'emails' => 0],
                    'queueWithMaxRuntime' => 'default',
                    'queueWithMaxThroughput' => 'emails',
                ], 200);
            }

            if (str_contains($request->url(), '/horizon/api/masters')) {
                return Http::response([[
                    'supervisors' => [[
                        'name' => 'prod:supervisor-1',
                        'status' => 'running',
                        'processes' => [1, 2],
                        'options' => ['connection' => 'redis', 'queue' => ['default', 'emails'], 'balance' => 'auto'],
                    ]],
                ]], 200);
            }

            if (str_contains($request->url(), '/horizon/api/jobs/')) {
                return Http::response(['jobs' => []], 200);
            }

            return Http::response('unexpected', 500);
        });

        $data = $this->private__invokeBuildServiceShowData($service, $request);

        $this->assertSame(2, $data['jobsPastMinute']);
        $this->assertSame(20, $data['jobsPastHour']);
        $this->assertSame(1, $data['failedPastSevenDays']);
        $this->assertSame('running', $data['horizonStatus']);
        $this->assertSame(3, $data['totalProcesses']);
        $this->assertSame(5.0, $data['maxWaitTimeSeconds']);
        $this->assertSame('job-x', $data['search']);
        $this->assertCount(1, $data['supervisors']);
        $this->assertCount(1, $data['workloadQueues']);
    }

    public function test_build_returns_empty_data_when_service_is_disabled(): void
    {
        $service = Service::create([
            'name' => 'disabled-svc',
            'base_url' => 'https://disabled.test',
            'status' => 'online',
            'enabled' => false,
        ]);
        $request = Request::create('/horizon/services/' . $service->id, 'GET');

        $metrics = $this->createMock(MetricsDataService::class);
        $metrics->expects($this->never())->method('getJobsPastMinute');
        $this->app->instance(MetricsDataService::class, $metrics);

        Http::fake(['*' => Http::response([], 200)]);

        $data = $this->private__invokeBuildServiceShowData($service, $request);

        $this->assertSame(0, $data['jobsPastMinute']);
        $this->assertNull($data['horizonStatus']);
        $this->assertTrue($data['workloadQueues']->isEmpty());

        Http::assertNothingSent();
    }

    /**
     * @return array<string, mixed>
     */
    private function private__invokeBuildServiceShowData(Service $service, Request $request): array
    {
        $controller = $this->app->make(HorizonStreamsController::class);
        $reflection = new \ReflectionMethod($controller, 'private__buildServiceShowData');
        $reflection->setAccessible(true);

        return $reflection->invoke($controller, $service, $request);
    }
}
