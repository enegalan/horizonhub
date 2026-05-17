<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Horizon\HorizonJobsWindowFetcher;
use App\Services\Metrics\HorizonMetricsComputation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorizonMetricsComputationTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_services_for_metrics_and_workload_fallback_from_masters(): void
    {
        $api = $this->createMock(HorizonApiProxyService::class);
        $fetcher = new HorizonJobsWindowFetcher($api);
        $probe = new class($api, $fetcher) extends HorizonMetricsComputation
        {
            public function public__services(array $scope): Collection
            {
                return $this->private__getServicesForMetrics($scope, true, ['id', 'name', 'base_url']);
            }
        };

        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://x.test', 'status' => 'online']);
        $this->assertCount(1, $probe->public__services([$service->id, 0, -1]));
        $this->assertCount(0, $probe->public__services([0, -1]));

        $api->method('getMasters')->willReturn([
            'success' => true,
            'data' => [[
                'supervisors' => [[
                    'options' => ['queue' => ['redis.alpha', '']],
                ]],
            ]],
        ]);
    }

    public function test_metrics_computation_helpers_cover_edge_branches(): void
    {
        $api = $this->createMock(HorizonApiProxyService::class);
        $fetcher = new HorizonJobsWindowFetcher($api);
        $probe = new class($api, $fetcher) extends HorizonMetricsComputation
        {
            public function public__initHourly(Carbon $since, Carbon $end): array
            {
                return $this->private__initHourlyBuckets($since, $end, 'Y-m-d H:00', 3, static fn (): array => ['v' => 0]);
            }

            public function public__sumQueues(array $queues, array $jobsByQueue): int
            {
                return $this->private__sumJobsByQueueNames($queues, $jobsByQueue);
            }

            public function public__extractQueues(array $options): array
            {
                return $this->private__extractQueuesFromSupervisorOptions($options);
            }
        };

        $this->assertSame(5, $probe->public__sumQueues(['a', 'b'], ['a' => 2, 'b' => 3]));
        $this->assertSame(['default'], $probe->public__extractQueues(['queue' => 'redis.default']));
        $this->assertSame([], $probe->public__extractQueues(['queue' => null]));
        $this->assertCount(2, $probe->public__initHourly(Carbon::parse('2026-01-01 00:00:00'), Carbon::parse('2026-01-01 01:00:00')));
    }
}
