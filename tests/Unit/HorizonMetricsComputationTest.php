<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Jobs\JobsWindowFetcherService;
use App\Services\Metrics\Calculators\AbstractMetricsCalculator;
use App\Support\Queues\QueueNameNormalizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorizonMetricsComputationTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_services_for_metrics_and_workload_fallback_from_masters(): void
    {
        $fetcher = new JobsWindowFetcherService;
        $probe = new class($fetcher) extends AbstractMetricsCalculator
        {
            /**
             * Expose `Service::getServices()` with the calculator defaults: enabled services only, no name
             * ordering, and a limited column selection.
             *
             * @param array<int, int|string> $serviceIds The service ids to load; non-numeric and non-positive entries are dropped.
             *
             * @return Collection<int, Service>
             */
            public function public__services(array $serviceIds): Collection
            {
                return Service::getServices($serviceIds, true, false, ['id', 'name', 'base_url']);
            }
        };

        $service = Service::create(['name' => 'svc', 'base_url' => 'https://x.test', 'status' => 'online']);
        $this->assertCount(1, $probe->public__services([$service->id, 0, -1]));
        $this->assertCount(0, $probe->public__services([0, -1]));
    }

    public function test_metrics_computation_helpers_cover_edge_branches(): void
    {
        $fetcher = new JobsWindowFetcherService;
        $probe = new class($fetcher) extends AbstractMetricsCalculator
        {
            /**
             * Expose `private__initHourlyBuckets()` with an hourly format, three bucket cap, and a single
             * `v` counter as bucket initializer.
             *
             * @param Carbon $since The inclusive start of the bucket window.
             * @param Carbon $end The inclusive end of the bucket window.
             *
             * @return array<string, array<string, mixed>> Hourly buckets keyed by `Y-m-d H:00`.
             */
            public function public__initHourly(Carbon $since, Carbon $end): array
            {
                return $this->private__initHourlyBuckets($since, $end, 'Y-m-d H:00', 3, static fn (): array => ['v' => 0]);
            }

            /**
             * Expose `private__sumJobsByQueueNames()` to total the job counts of the given queues.
             *
             * @param array<int, string> $queues The queue names to sum.
             * @param array<string, int> $jobsByQueue The job count per queue name; unknown queues count as zero.
             */
            public function public__sumQueues(array $queues, array $jobsByQueue): int
            {
                return $this->private__sumJobsByQueueNames($queues, $jobsByQueue);
            }
        };

        $this->assertSame(5, $probe->public__sumQueues(['a', 'b'], ['a' => 2, 'b' => 3]));
        $this->assertSame(['default'], QueueNameNormalizer::normalizeListFromOptions(['queue' => 'redis.default']));
        $this->assertSame([], QueueNameNormalizer::normalizeListFromOptions(['queue' => null]));
        $this->assertSame(['alpha', 'beta'], QueueNameNormalizer::normalizeListFromOptions(['queue' => ['redis.alpha', 'redis.beta', '']]));
        $this->assertCount(2, $probe->public__initHourly(Carbon::parse('2026-01-01 00:00:00'), Carbon::parse('2026-01-01 01:00:00')));
    }
}
