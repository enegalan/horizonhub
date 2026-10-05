<?php

namespace Tests\Unit;

use App\Http\Controllers\Stream\HorizonStreamsController;
use App\Models\Service;
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

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/horizon/api/stats')) {
                return Http::response([
                    'status' => 'running',
                    'processes' => 3,
                    'jobsPerMinute' => 2.4,
                    'recentJobs' => 20,
                    'failedJobs' => 1,
                    'wait' => ['default' => 5.0, 'emails' => 0],
                    'queueWithMaxRuntime' => 'default',
                    'queueWithMaxThroughput' => 'emails',
                ], 200);
            }

            if (str_contains($request->url(), '/horizon/api/workload')) {
                return Http::response([
                    ['name' => 'default', 'length' => 5, 'processes' => 2, 'wait' => 1.2],
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
        $this->assertSame(5, $data['workloadQueues'][0]['jobs']);
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

        Http::fake(['*' => Http::response([], 200)]);

        $data = $this->private__invokeBuildServiceShowData($service, $request);

        $this->assertSame(0, $data['jobsPastMinute']);
        $this->assertNull($data['horizonStatus']);
        $this->assertTrue($data['workloadQueues']->isEmpty());

        Http::assertNothingSent();
    }

    /**
     * Invoke the controller's private service-detail data builder.
     *
     * `buildServiceShowData()` is a private method of the `BuildsServiceStreams`
     * trait composed into the controller, so it is reached through reflection
     * rather than through an SSE stream or a pass-through getter that only the
     * tests would use. Only enabled services trigger the Horizon reads.
     *
     * @param Service $service The service whose detail data is built.
     * @param Request $request The request supplying the search term and per-section page numbers.
     *
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
