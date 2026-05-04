<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Metrics\HorizonMetricsComputation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorizonMetricsComputationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_jobs_window_and_helpers_cover_edge_branches(): void
    {
        $api = $this->createMock(HorizonApiProxyService::class);
        $probe = new class($api) extends HorizonMetricsComputation {
            public function public__fetchJobs(int $since, callable $fetcher, callable $extractor): array
            {
                return $this->private__fetchJobsInWindow($since, $fetcher, $extractor);
            }

            public function public__nextStartingAt(int $startingAt, array $batch): int
            {
                return $this->private__nextMetricsJobsStartingAt($startingAt, $batch);
            }

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

        config()->set('horizonhub.horizon_api_job_list_page_size', 2);
        config()->set('horizonhub.max_horizon_pages', 5);
        $calls = 0;
        $jobs = $probe->public__fetchJobs(
            100,
            function () use (&$calls): array {
                $calls++;
                if ($calls === 1) {
                    return ['success' => true, 'data' => ['jobs' => [['index' => 0, 'ts' => 120], ['index' => 1, 'ts' => 100]]]];
                }

                return ['success' => true, 'data' => ['jobs' => []]];
            },
            static fn (array $job): ?int => isset($job['ts']) ? (int) $job['ts'] : null
        );

        $this->assertCount(2, $jobs);
        $this->assertSame(1, $probe->public__nextStartingAt(-1, [['index' => 0], ['index' => 1]]));
        $this->assertSame(2, $probe->public__nextStartingAt(0, [['x' => 1], ['x' => 2]]));
        $this->assertSame(5, $probe->public__sumQueues(['a', 'b'], ['a' => 2, 'b' => 3]));
        $this->assertSame(['default'], $probe->public__extractQueues(['queue' => 'redis.default']));
        $this->assertSame([], $probe->public__extractQueues(['queue' => null]));
        $this->assertCount(2, $probe->public__initHourly(Carbon::parse('2026-01-01 00:00:00'), Carbon::parse('2026-01-01 01:00:00')));
    }

    public function test_get_services_for_metrics_and_workload_fallback_from_masters(): void
    {
        $api = $this->createMock(HorizonApiProxyService::class);
        $probe = new class($api) extends HorizonMetricsComputation {
            public function public__services(array $scope): \Illuminate\Database\Eloquent\Collection
            {
                return $this->private__getServicesForMetrics($scope, true, ['id', 'name', 'base_url']);
            }

            public function public__fallback(Service $service): array
            {
                return $this->private__getWorkloadFallbackFromMasters($service);
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
        $fallback = $probe->public__fallback($service);
        $this->assertCount(1, $fallback);
        $this->assertSame('alpha', $fallback[0]['queue']);
        $this->assertSame(0, $fallback[0]['jobs']);
    }

    public function test_fetch_jobs_window_breaks_on_unsuccessful_or_invalid_batches(): void
    {
        $api = $this->createMock(HorizonApiProxyService::class);
        $probe = new class($api) extends HorizonMetricsComputation {
            public function public__fetchJobs(int $since, callable $fetcher, callable $extractor): array
            {
                return $this->private__fetchJobsInWindow($since, $fetcher, $extractor);
            }

            public function public__fallback(Service $service): array
            {
                return $this->private__getWorkloadFallbackFromMasters($service);
            }
        };

        config()->set('horizonhub.horizon_api_job_list_page_size', 3);
        config()->set('horizonhub.max_horizon_pages', 2);

        $failed = $probe->public__fetchJobs(
            100,
            static fn (): array => ['success' => false],
            static fn (array $job): ?int => $job['ts'] ?? null
        );
        $this->assertSame([], $failed);

        $invalidBatch = $probe->public__fetchJobs(
            100,
            static fn (): array => ['success' => true, 'data' => ['jobs' => [null, 'x']]],
            static fn (array $job): ?int => $job['ts'] ?? null
        );
        $this->assertSame([], $invalidBatch);

        $service = Service::query()->create(['name' => 'svc-no-masters', 'base_url' => 'https://x.test', 'status' => 'online']);
        $api->method('getMasters')->willReturn(['success' => false]);
        $this->assertSame([], $probe->public__fallback($service));
    }
}
