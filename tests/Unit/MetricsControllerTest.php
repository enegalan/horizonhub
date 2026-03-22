<?php

namespace Tests\Unit;

use App\Http\Controllers\Horizon\MetricsController;
use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Service;
use App\Services\Horizon\HorizonMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

class MetricsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createServiceRequest(string $uri): ServiceRequest
    {
        $request = ServiceRequest::create($uri, 'GET');
        $request->setContainer($this->app);

        return $request;
    }

    public function test_data_summary_returns_expected_keys_and_delegates(): void
    {
        $service = Service::create([
            'name' => 'svc-metrics-controller-summary',
            'api_key' => 'k12345678901234567890123456789012345678901234567890123456789012',
            'base_url' => 'https://metrics-controller-summary.test',
            'status' => 'online',
        ]);

        $metrics = $this->createMock(HorizonMetricsService::class);
        $serviceId = (int) $service->id;

        $metrics->expects($this->once())
            ->method('getThroughputTotalsForServiceIds')
            ->with([$serviceId])
            ->willReturn([
                'jobsPastMinute' => 11,
                'jobsPastHour' => 22,
                'failedPastSevenDays' => 33,
            ]);

        $metrics->expects($this->once())
            ->method('getFailureRate24h')
            ->willReturnCallback(static function (array $scope) use ($serviceId): array {
                Assert::assertSame([$serviceId], $scope);

                return [
                    'rate' => 1.2,
                    'processed' => 10,
                    'failed' => 3,
                ];
            });

        $controller = new MetricsController($metrics);
        $request = $this->createServiceRequest('/horizon/metrics/data/summary?service_id='.$serviceId);

        $response = $controller->dataSummary($request);
        $data = $response->getData(true);

        $this->assertSame(11, $data['jobsPastMinute']);
        $this->assertSame(22, $data['jobsPastHour']);
        $this->assertSame(33, $data['failedPastSevenDays']);
        $this->assertSame(1.2, $data['failureRate24h']['rate']);
        $this->assertSame(10, $data['failureRate24h']['processed']);
        $this->assertSame(3, $data['failureRate24h']['failed']);
    }

    public function test_data_job_runtimes_last24h_returns_payload_and_delegates(): void
    {
        $service = Service::create([
            'name' => 'svc-metrics-controller-job-runtimes',
            'api_key' => 'k22345678901234567890123456789012345678901234567890123456789012',
            'base_url' => 'https://metrics-controller-job-runtimes.test',
            'status' => 'online',
        ]);

        $metrics = $this->createMock(HorizonMetricsService::class);

        $payload = [
            'points' => [
                [
                    'endAtMs' => 1_720_000_000_000,
                    'seconds' => 12.5,
                    'name' => 'App\\Jobs\\Example',
                    'service' => 'svc-metrics-controller-job-runtimes',
                    'status' => 'completed',
                ],
            ],
        ];

        $metrics->expects($this->once())
            ->method('getJobRuntimesLast24h')
            ->willReturnCallback(static function (array $scope) use ($service, $payload): array {
                Assert::assertSame([(int) $service->id], $scope);

                return $payload;
            });

        $controller = new MetricsController($metrics);
        $request = $this->createServiceRequest('/horizon/metrics/data/job-runtimes-last-24h?service_id='.$service->id);

        $response = $controller->dataJobRuntimesLast24h($request);
        $data = $response->getData(true);

        $this->assertSame($payload, $data);
    }

    public function test_data_failure_rate_over_time_returns_payload_and_delegates(): void
    {
        $service = Service::create([
            'name' => 'svc-metrics-controller-failure-rate',
            'api_key' => 'k32345678901234567890123456789012345678901234567890123456789012',
            'base_url' => 'https://metrics-controller-failure-rate.test',
            'status' => 'online',
        ]);

        $metrics = $this->createMock(HorizonMetricsService::class);

        $metrics->expects($this->once())
            ->method('getFailureRateOverTime')
            ->willReturnCallback(static function (array $scope) use ($service): array {
                Assert::assertSame([(int) $service->id], $scope);

                return ['xAxis' => ['01/01 00:00'], 'rate' => [12.3]];
            });

        $controller = new MetricsController($metrics);
        $request = $this->createServiceRequest('/horizon/metrics/data/failure-rate-over-time?service_id='.$service->id);

        $response = $controller->dataFailureRateOverTime($request);
        $data = $response->getData(true);

        $this->assertSame(['xAxis' => ['01/01 00:00'], 'rate' => [12.3]], $data);
    }

    public function test_data_jobs_volume_last24h_returns_payload_and_delegates(): void
    {
        $service = Service::create([
            'name' => 'svc-metrics-controller-jobs-volume',
            'api_key' => 'k62345678901234567890123456789012345678901234567890123456789012',
            'base_url' => 'https://metrics-controller-jobs-volume.test',
            'status' => 'online',
        ]);

        $metrics = $this->createMock(HorizonMetricsService::class);

        $metrics->expects($this->once())
            ->method('getJobsVolumeLast24h')
            ->willReturnCallback(static function (array $scope) use ($service): array {
                Assert::assertSame([(int) $service->id], $scope);

                return [
                    'xAxis' => ['20/03 15:00'],
                    'completed' => [1],
                    'failed' => [0],
                ];
            });

        $controller = new MetricsController($metrics);
        $request = $this->createServiceRequest('/horizon/metrics/data/jobs-volume-last-24h?service_id='.$service->id);

        $response = $controller->dataJobsVolumeLast24h($request);
        $data = $response->getData(true);

        $this->assertSame(
            ['xAxis' => ['20/03 15:00'], 'completed' => [1], 'failed' => [0]],
            $data
        );
    }

    public function test_data_supervisors_returns_payload_and_delegates(): void
    {
        $service = Service::create([
            'name' => 'svc-metrics-controller-supervisors',
            'api_key' => 'k42345678901234567890123456789012345678901234567890123456789012',
            'base_url' => 'https://metrics-controller-supervisors.test',
            'status' => 'online',
        ]);

        $metrics = $this->createMock(HorizonMetricsService::class);

        $metrics->expects($this->once())
            ->method('getSupervisorsData')
            ->willReturnCallback(static function (array $scope) use ($service): array {
                Assert::assertSame([(int) $service->id], $scope);

                return [
                    [
                        'service_id' => (int) $service->id,
                        'service' => 'svc-metrics-controller-supervisors',
                        'name' => 'sup-1',
                        'status' => 'online',
                        'jobs' => 5,
                        'processes' => null,
                    ],
                ];
            });

        $controller = new MetricsController($metrics);
        $request = $this->createServiceRequest('/horizon/metrics/data/supervisors?service_id='.$service->id);

        $response = $controller->dataSupervisors($request);
        $data = $response->getData(true);

        $this->assertArrayHasKey('supervisors', $data);
        $this->assertCount(1, $data['supervisors']);
        $this->assertSame('sup-1', $data['supervisors'][0]['name']);
    }

    public function test_data_workload_returns_payload_and_delegates(): void
    {
        $service = Service::create([
            'name' => 'svc-metrics-controller-workload',
            'api_key' => 'k52345678901234567890123456789012345678901234567890123456789012',
            'base_url' => 'https://metrics-controller-workload.test',
            'status' => 'online',
        ]);

        $metrics = $this->createMock(HorizonMetricsService::class);

        $metrics->expects($this->once())
            ->method('getWorkloadData')
            ->willReturnCallback(static function (array $scope) use ($service): array {
                Assert::assertSame([(int) $service->id], $scope);

                return [
                    [
                        'service_id' => (int) $service->id,
                        'service' => 'svc-metrics-controller-workload',
                        'queue' => 'alpha',
                        'jobs' => 7,
                        'processes' => 2,
                        'wait' => 1.25,
                    ],
                ];
            });

        $controller = new MetricsController($metrics);
        $request = $this->createServiceRequest('/horizon/metrics/data/workload?service_id='.$service->id);

        $response = $controller->dataWorkload($request);
        $data = $response->getData(true);

        $this->assertArrayHasKey('workload', $data);
        $this->assertCount(1, $data['workload']);
        $this->assertSame('alpha', $data['workload'][0]['queue']);
    }

    public function test_data_summary_sanitizes_invalid_service_id_to_null(): void
    {
        $service = Service::create([
            'name' => 'svc-metrics-controller-summary-invalid-service-id',
            'api_key' => 'k62345678901234567890123456789012345678901234567890123456789012',
            'base_url' => 'https://metrics-controller-summary-invalid-service-id.test',
            'status' => 'online',
        ]);

        $metrics = $this->createMock(HorizonMetricsService::class);

        $metrics->expects($this->once())
            ->method('getThroughputTotalsForServiceIds')
            ->with([])
            ->willReturn([
                'jobsPastMinute' => 11,
                'jobsPastHour' => 22,
                'failedPastSevenDays' => 33,
            ]);

        $metrics->expects($this->once())
            ->method('getFailureRate24h')
            ->with([])
            ->willReturn([
                'rate' => 1.2,
                'processed' => 10,
                'failed' => 3,
            ]);

        $controller = new MetricsController($metrics);
        $request = $this->createServiceRequest('/horizon/metrics/data/summary?service_id=not-a-number');

        $response = $controller->dataSummary($request);
        $data = $response->getData(true);

        $this->assertSame(11, $data['jobsPastMinute']);
        $this->assertSame(22, $data['jobsPastHour']);
        $this->assertSame(33, $data['failedPastSevenDays']);
        $this->assertSame(1.2, $data['failureRate24h']['rate']);
    }
}
