<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\HorizonApiProxyService;
use App\Services\HorizonMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorizonMetricsServiceTest extends TestCase {
    use RefreshDatabase;

    public function test_get_workload_for_service_maps_nested_data_payload(): void {
        $api = $this->createMock(HorizonApiProxyService::class);
        $api->expects($this->once())
            ->method('getWorkload')
            ->willReturn([
                'success' => true,
                'data' => [
                    'data' => [
                        ['name' => 'redis.default', 'length' => 7, 'processes' => 2, 'wait' => 1.5],
                    ],
                ],
            ]);

        $service = Service::create([
            'name' => 'svc-a',
            'api_key' => 'k12345678901234567890123456789012345678901234567890123456789012',
            'base_url' => 'https://metrics.test',
            'status' => 'online',
        ]);

        $metrics = new HorizonMetricsService($api);
        $rows = $metrics->getWorkloadForService($service);

        $this->assertCount(1, $rows);
        $this->assertSame('redis.default', $rows[0]['queue']);
        $this->assertSame(7, $rows[0]['jobs']);
        $this->assertSame(2, $rows[0]['processes']);
        $this->assertSame(1.5, $rows[0]['wait']);
    }

    public function test_get_workload_for_service_returns_empty_without_base_url(): void {
        $api = $this->createMock(HorizonApiProxyService::class);
        $api->expects($this->never())->method('getWorkload');

        $service = Service::create([
            'name' => 'svc-b',
            'api_key' => 'k22345678901234567890123456789012345678901234567890123456789012',
            'base_url' => null,
            'status' => 'online',
        ]);

        $metrics = new HorizonMetricsService($api);
        $this->assertSame([], $metrics->getWorkloadForService($service));
    }

    public function test_get_workload_for_service_returns_empty_when_api_fails(): void {
        $api = $this->createMock(HorizonApiProxyService::class);
        $api->method('getWorkload')->willReturn([
            'success' => false,
            'status' => 503,
            'message' => 'unavailable',
        ]);

        $service = Service::create([
            'name' => 'svc-c',
            'api_key' => 'k32345678901234567890123456789012345678901234567890123456789012',
            'base_url' => 'https://metrics-c.test',
            'status' => 'online',
        ]);

        $metrics = new HorizonMetricsService($api);
        $this->assertSame([], $metrics->getWorkloadForService($service));
    }

    public function test_get_workload_for_service_accepts_numeric_indexed_rows(): void {
        $api = $this->createMock(HorizonApiProxyService::class);
        $api->method('getWorkload')->willReturn([
            'success' => true,
            'data' => [
                ['name' => 'alpha', 'size' => 4],
            ],
        ]);

        $service = Service::create([
            'name' => 'svc-d',
            'api_key' => 'k42345678901234567890123456789012345678901234567890123456789012',
            'base_url' => 'https://metrics-d.test',
            'status' => 'online',
        ]);

        $metrics = new HorizonMetricsService($api);
        $rows = $metrics->getWorkloadForService($service);

        $this->assertCount(1, $rows);
        $this->assertSame('alpha', $rows[0]['queue']);
        $this->assertSame(4, $rows[0]['jobs']);
    }
}
