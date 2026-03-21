<?php

namespace Tests\Unit;

use App\Http\Controllers\Horizon\MetricsController;
use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Service;
use App\Services\HorizonMetricsService;
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
            ->method('getJobsPastMinute')
            ->willReturnCallback(static function ($svc) use ($serviceId): int {
                Assert::assertInstanceOf(Service::class, $svc);
                Assert::assertSame($serviceId, (int) $svc->id);

                return 11;
            });

        $metrics->expects($this->once())
            ->method('getJobsPastHour')
            ->willReturnCallback(static function ($svc) use ($serviceId): int {
                Assert::assertInstanceOf(Service::class, $svc);
                Assert::assertSame($serviceId, (int) $svc->id);

                return 22;
            });

        $metrics->expects($this->once())
            ->method('getFailedPastSevenDays')
            ->willReturnCallback(static function ($svc) use ($serviceId): int {
                Assert::assertInstanceOf(Service::class, $svc);
                Assert::assertSame($serviceId, (int) $svc->id);

                return 33;
            });

        $metrics->expects($this->once())
            ->method('getFailureRate24h')
            ->willReturnCallback(static function ($serviceIdArg) use ($serviceId): array {
                Assert::assertSame($serviceId, (int) $serviceIdArg);

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

    public function test_data_avg_runtime_returns_payload_and_delegates(): void
    {
        $service = Service::create([
            'name' => 'svc-metrics-controller-avg-runtime',
            'api_key' => 'k22345678901234567890123456789012345678901234567890123456789012',
            'base_url' => 'https://metrics-controller-avg-runtime.test',
            'status' => 'online',
        ]);

        $metrics = $this->createMock(HorizonMetricsService::class);

        $metrics->expects($this->once())
            ->method('getAvgRuntimeOverTime')
            ->willReturnCallback(static function ($serviceIdArg) use ($service): array {
                Assert::assertSame((int) $service->id, (int) $serviceIdArg);

                return ['xAxis' => ['01/01 00:00'], 'avgSeconds' => [1.23]];
            });

        $controller = new MetricsController($metrics);
        $request = $this->createServiceRequest('/horizon/metrics/data/avg-runtime?service_id='.$service->id);

        $response = $controller->dataAvgRuntime($request);
        $data = $response->getData(true);

        $this->assertSame(['xAxis' => ['01/01 00:00'], 'avgSeconds' => [1.23]], $data);
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
            ->willReturnCallback(static function ($serviceIdArg) use ($service): array {
                Assert::assertSame((int) $service->id, (int) $serviceIdArg);

                return ['xAxis' => ['01/01 00:00'], 'rate' => [12.3]];
            });

        $controller = new MetricsController($metrics);
        $request = $this->createServiceRequest('/horizon/metrics/data/failure-rate-over-time?service_id='.$service->id);

        $response = $controller->dataFailureRateOverTime($request);
        $data = $response->getData(true);

        $this->assertSame(['xAxis' => ['01/01 00:00'], 'rate' => [12.3]], $data);
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
            ->willReturnCallback(static function ($serviceIdArg) use ($service): array {
                Assert::assertSame((int) $service->id, (int) $serviceIdArg);

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
            ->willReturnCallback(static function ($serviceIdArg) use ($service): array {
                Assert::assertSame((int) $service->id, (int) $serviceIdArg);

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
            ->method('getJobsPastMinute')
            ->with(null)
            ->willReturn(11);

        $metrics->expects($this->once())
            ->method('getJobsPastHour')
            ->with(null)
            ->willReturn(22);

        $metrics->expects($this->once())
            ->method('getFailedPastSevenDays')
            ->with(null)
            ->willReturn(33);

        $metrics->expects($this->once())
            ->method('getFailureRate24h')
            ->with(null)
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
