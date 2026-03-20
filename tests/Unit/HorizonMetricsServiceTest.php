<?php

namespace Tests\Unit;

use Carbon\Carbon;
use App\Models\Service;
use App\Support\Horizon\QueueNameNormalizer;
use App\Services\HorizonApiProxyService;
use App\Services\HorizonMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorizonMetricsServiceTest extends TestCase {
    use RefreshDatabase;

    public function test_queue_name_normalizer_strips_redis_prefix(): void {
        $this->assertNull(QueueNameNormalizer::normalize(null));
        $this->assertSame('', QueueNameNormalizer::normalize(''));
        $this->assertSame('default', QueueNameNormalizer::normalize('redis.default'));
        $this->assertSame('default', QueueNameNormalizer::normalize('redis:default'));
        $this->assertSame('emails', QueueNameNormalizer::normalize('database.emails'));
        $this->assertSame('notifications', QueueNameNormalizer::normalize('sqs:notifications'));
        $this->assertSame('redis.', QueueNameNormalizer::normalize('redis.'));
        $this->assertSame('alpha', QueueNameNormalizer::normalize('alpha'));
    }

    public function test_queue_name_normalizer_strips_custom_connection_prefix_via_fallback_pattern(): void {
        $this->assertSame('jobs', QueueNameNormalizer::normalize('acme_worker:jobs'));
        $this->assertSame('emails', QueueNameNormalizer::normalize('acme_worker.emails'));
    }

    public function test_queue_name_normalizer_prefers_longest_queue_config_connection_name(): void {
        \config(['queue.connections.redis_cluster' => ['driver' => 'redis']]);

        $this->assertSame('default', QueueNameNormalizer::normalize('redis_cluster:default'));
        $this->assertSame('default', QueueNameNormalizer::normalize('redis:default'));
    }

    public function test_queue_name_normalizer_does_not_strip_when_leading_segment_starts_with_digit(): void {
        $this->assertSame('1queue:jobs', QueueNameNormalizer::normalize('1queue:jobs'));
    }

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
        $this->assertSame('default', $rows[0]['queue']);
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

    public function test_get_failure_rate_over_time_builds_expected_buckets_and_rates(): void {
        $api = $this->createMock(HorizonApiProxyService::class);

        $now = Carbon::parse('2026-03-20 15:30:00');
        Carbon::setTestNow($now);

        $service = Service::create([
            'name' => 'svc-series',
            'api_key' => 'k12345678901234567890123456789012345678901234567890123456789012',
            'base_url' => 'https://metrics-series.test',
            'status' => 'online',
        ]);

        $since = $now->copy()->subDay()->startOfDay();
        $bucket1 = $since->copy()->addHours(2);  // 02:00 previous day
        $bucket2 = $since->copy()->addHours(30); // 06:00 current day

        $completedJobsBucket1 = [
            ['completed_at' => $bucket1->copy()->addMinutes(10)->getTimestamp()],
            ['completed_at' => $bucket1->copy()->addMinutes(20)->getTimestamp()],
            ['completed_at' => $bucket1->copy()->addMinutes(30)->getTimestamp()],
        ];
        $completedJobsBucket2 = [
            ['completed_at' => $bucket2->copy()->addMinutes(5)->getTimestamp()],
        ];
        $failedJobsBucket1 = [
            ['failed_at' => $bucket1->copy()->addMinutes(40)->getTimestamp()],
        ];

        $api->method('getCompletedJobs')->willReturn([
            'success' => true,
            'data' => [
                'jobs' => (function () use ($completedJobsBucket1, $completedJobsBucket2): array {
                    $jobs = $completedJobsBucket1;
                    foreach ($completedJobsBucket2 as $job) {
                        $jobs[] = $job;
                    }
                    return $jobs;
                })(),
            ],
        ]);
        $api->method('getFailedJobs')->willReturn([
            'success' => true,
            'data' => [
                'jobs' => $failedJobsBucket1,
            ],
        ]);

        $metrics = new HorizonMetricsService($api);
        $result = $metrics->getFailureRateOverTime($service->id);

        $endHour = $now->copy()->startOfHour();
        $expectedBucketCount = \min(
            48,
            ($endHour->getTimestamp() - $since->getTimestamp()) / 3600 + 1
        );

        $this->assertCount((int) $expectedBucketCount, $result['xAxis']);
        $this->assertCount((int) $expectedBucketCount, $result['rate']);

        $indexBucket1 = (int) (($bucket1->getTimestamp() - $since->getTimestamp()) / 3600);
        $indexBucket2 = (int) (($bucket2->getTimestamp() - $since->getTimestamp()) / 3600);

        $this->assertSame(\round(100 * 1 / (3 + 1), 1), $result['rate'][$indexBucket1]); // 25.0
        $this->assertSame(0.0, $result['rate'][$indexBucket2]);
        $this->assertNull($result['rate'][$indexBucket1 - 1]);

        Carbon::setTestNow();
    }

    public function test_get_avg_runtime_over_time_builds_expected_avg_seconds(): void {
        $api = $this->createMock(HorizonApiProxyService::class);

        $now = Carbon::parse('2026-03-20 15:30:00');
        Carbon::setTestNow($now);

        $service = Service::create([
            'name' => 'svc-runtime',
            'api_key' => 'k32345678901234567890123456789012345678901234567890123456789012',
            'base_url' => 'https://metrics-runtime.test',
            'status' => 'online',
        ]);

        $since = $now->copy()->subDay()->startOfDay();
        $bucket = $since->copy()->addHours(2);

        $completedAt1 = $bucket->copy()->addMinutes(10)->getTimestamp();
        $reservedAt1 = $completedAt1 - 120;
        $completedAt2 = $bucket->copy()->addMinutes(20)->getTimestamp();
        $reservedAt2 = $completedAt2 - 180;

        $failedAt = $bucket->copy()->addMinutes(30)->getTimestamp();
        $reservedAtFailed = $failedAt - 240;

        $api->method('getCompletedJobs')->willReturn([
            'success' => true,
            'data' => [
                'jobs' => [
                    [
                        'reserved_at' => $reservedAt1,
                        'completed_at' => $completedAt1,
                    ],
                    [
                        'reserved_at' => $reservedAt2,
                        'completed_at' => $completedAt2,
                    ],
                ],
            ],
        ]);
        $api->method('getFailedJobs')->willReturn([
            'success' => true,
            'data' => [
                'jobs' => [
                    [
                        'reserved_at' => $reservedAtFailed,
                        'failed_at' => $failedAt,
                    ],
                ],
            ],
        ]);

        $metrics = new HorizonMetricsService($api);
        $result = $metrics->getAvgRuntimeOverTime($service->id);

        $endHour = $now->copy()->startOfHour();
        $expectedBucketCount = \min(
            48,
            ($endHour->getTimestamp() - $since->getTimestamp()) / 3600 + 1
        );

        $this->assertCount((int) $expectedBucketCount, $result['xAxis']);
        $this->assertCount((int) $expectedBucketCount, $result['avgSeconds']);

        $indexBucket = (int) (($bucket->getTimestamp() - $since->getTimestamp()) / 3600);

        // Sum = 120 + 180 + 240 = 540; Count = 3 => 180.00
        $this->assertSame(180.0, $result['avgSeconds'][$indexBucket]);
        $this->assertNull($result['avgSeconds'][$indexBucket + 1]);

        Carbon::setTestNow();
    }

    public function test_get_supervisors_data_aggregates_jobs_by_queue_and_processes(): void {
        $api = $this->createMock(HorizonApiProxyService::class);

        $service = Service::create([
            'name' => 'svc-supervisors',
            'api_key' => 'k123789012345678901234567890123456789012345678901234567890123456',
            'base_url' => 'https://metrics-supervisors.test',
            'status' => 'online',
        ]);

        $serviceId = $service->id;

        $api->method('getWorkload')->with($this->callback(function ($svc) use ($serviceId): bool {
            return $svc instanceof Service && (int) $svc->id === (int) $serviceId;
        }))->willReturn([
            'success' => true,
            'data' => [
                'data' => [
                    ['name' => 'redis.default', 'length' => 7, 'processes' => 2, 'wait' => 1.5],
                ],
            ],
        ]);

        $api->method('getMasters')->with($this->callback(function ($svc) use ($serviceId): bool {
            return $svc instanceof Service && (int) $svc->id === (int) $serviceId;
        }))->willReturn([
            'success' => true,
            'data' => [
                [
                    'supervisors' => [
                        [
                            'name' => 'sup-1',
                            'processes' => [1, '2'],
                            'options' => [
                                'queue' => 'redis.default',
                            ],
                        ],
                        [
                            'name' => 'sup-2',
                            'options' => [
                                'queue' => ['redis.default', 'beta'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $metrics = new HorizonMetricsService($api);
        $rows = $metrics->getSupervisorsData($service->id);

        $this->assertCount(2, $rows);

        $this->assertSame($service->id, $rows[0]['service_id']);
        $this->assertSame('svc-supervisors', $rows[0]['service']);
        $this->assertSame('sup-1', $rows[0]['name']);
        $this->assertSame('online', $rows[0]['status']);
        $this->assertSame(7, $rows[0]['jobs']);
        $this->assertSame(3, $rows[0]['processes']);

        $this->assertSame($service->id, $rows[1]['service_id']);
        $this->assertSame('sup-2', $rows[1]['name']);
        $this->assertSame(7, $rows[1]['jobs']);
        $this->assertNull($rows[1]['processes']);
    }
}
