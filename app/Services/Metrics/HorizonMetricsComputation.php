<?php

namespace App\Services\Metrics;

use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Horizon\HorizonJobsWindowFetcher;
use App\Support\Horizon\QueueNameNormalizer;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

abstract class HorizonMetricsComputation
{
    /**
     * The number of top queues to return.
     *
     * @var int
     */
    public const TOP_N_QUEUES = 12;

    /**
     * The Horizon API proxy service.
     */
    protected HorizonApiProxyService $horizonApi;

    /**
     * The jobs window fetcher.
     */
    protected HorizonJobsWindowFetcher $jobsWindowFetcher;

    /**
     * Construct the Horizon metrics computation.
     */
    public function __construct(HorizonApiProxyService $horizonApi, HorizonJobsWindowFetcher $jobsWindowFetcher)
    {
        $this->horizonApi = $horizonApi;
        $this->jobsWindowFetcher = $jobsWindowFetcher;
    }

    /**
     * Aggregate queue counters from Horizon jobs payload.
     *
     * @param mixed $jobsPayload The jobs payload.
     * @param int $sinceTimestamp The since timestamp.
     * @param string $timestampField The timestamp field.
     * @param array<string, int> $queueCounts The queue counts.
     */
    protected function private__aggregateQueueCountsFromJobsPayload(mixed $jobsPayload, int $sinceTimestamp, string $timestampField, array &$queueCounts): void
    {
        foreach ($jobsPayload as $job) {
            if (! \is_array($job)) {
                continue;
            }

            $tsRaw = $job[$timestampField] ?? null;

            if (! \is_numeric($tsRaw) || (int) $tsRaw < $sinceTimestamp) {
                continue;
            }

            $queueRaw = isset($job['queue']) ? (string) $job['queue'] : '';
            $queue = QueueNameNormalizer::normalize($queueRaw);

            if ($queue === null) {
                $queue = $queueRaw;
            }

            if (! isset($queueCounts[$queue])) {
                $queueCounts[$queue] = 0;
            }

            $queueCounts[$queue]++;
        }
    }

    /**
     * Extract a normalized queue list from supervisor options.
     *
     * @param array<string, mixed> $options
     *
     * @return array<int, string>
     */
    protected function private__extractQueuesFromSupervisorOptions(array $options): array
    {
        $queues = $options['queue'] ?? null;

        if (! \is_array($queues)) {
            if (empty($queues)) {
                return [];
            }

            $queue = QueueNameNormalizer::normalize((string) $queues);

            return ! empty($queue) ? [$queue] : [];
        }

        $normalizedQueues = [];

        foreach ($queues as $queue) {
            $normalizedQueue = QueueNameNormalizer::normalize((string) $queue);

            if (empty($normalizedQueue)) {
                continue;
            }

            $normalizedQueues[$normalizedQueue] = true;
        }

        return \array_keys($normalizedQueues);
    }

    /**
     * Fetch failed jobs with failed_at >= $sinceTimestamp by paginating the Horizon API.
     *
     * Each request uses Horizon query `limit` = horizonhub.horizon_api_job_list_page_size.
     * The number of HTTP pages per service is capped by horizonhub.max_horizon_pages.
     *
     * @param Service $service The service.
     * @param int $sinceTimestamp The since timestamp.
     *
     * @return list<array<string, mixed>>
     */
    protected function private__fetchFailedJobsInWindow(Service $service, int $sinceTimestamp): array
    {
        return $this->jobsWindowFetcher->fetchFailedJobsSince($service, $sinceTimestamp);
    }

    /**
     * Get services that can provide Horizon metrics.
     *
     * @param array<string, mixed> $serviceScope The service scope. Empty = all enabled services; non-empty = restrict by id.
     * @param bool $orderByName The order by name.
     * @param array<int, string> $selectColumns The select columns.
     *
     * @return Collection<int, Service>
     */
    protected function private__getServicesForMetrics(array $serviceScope = [], bool $orderByName = false, array $selectColumns = []): Collection
    {
        $servicesQuery = Service::query()->enabled();

        if (! empty($serviceScope)) {
            $ids = \array_values(\array_unique(\array_filter(
                \array_map(static fn ($v): int => (int) $v, $serviceScope),
                static fn (int $id): bool => $id > 0,
            )));

            if (empty($ids)) {
                return new Collection;
            }
            $servicesQuery->whereIn('id', $ids);
        }

        if ($orderByName) {
            $servicesQuery->orderBy('name');
        }

        if (! empty($selectColumns)) {
            return $servicesQuery->get($selectColumns);
        }

        return $servicesQuery->get();
    }

    /**
     * Increment the hourly buckets.
     *
     * @param array<string, array<string, mixed>> $buckets The buckets.
     * @param list<array<string, mixed>> $jobs The jobs.
     * @param string $timestampField The timestamp field.
     * @param string $counterKey The counter key.
     * @param int $sinceTimestamp The since timestamp.
     * @param string $bucketFormat The bucket format.
     */
    protected function private__incrementHourlyBuckets(array &$buckets, array $jobs, string $timestampField, string $counterKey, int $sinceTimestamp, string $bucketFormat): void
    {
        foreach ($jobs as $job) {
            $at = $this->private__parseJobTimestamp($job, $timestampField);

            if ($at === null) {
                continue;
            }

            $ts = $at->getTimestamp();

            if ($ts < $sinceTimestamp) {
                continue;
            }

            $bucket = $at->format($bucketFormat);

            if (isset($buckets[$bucket][$counterKey])) {
                $buckets[$bucket][$counterKey]++;
            }
        }
    }

    /**
     * Initialize hourly buckets between $since and $endHour (inclusive).
     *
     * @param Carbon $since The since.
     * @param Carbon $endHour The end hour.
     * @param string $bucketFormat The bucket format.
     * @param int $maxBuckets The max buckets.
     * @param callable(): array $bucketInitializer The bucket initializer.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function private__initHourlyBuckets(Carbon $since, Carbon $endHour, string $bucketFormat, int $maxBuckets, callable $bucketInitializer): array
    {
        $buckets = [];
        $bucketStart = $since->copy();

        while ($bucketStart <= $endHour && \count($buckets) < $maxBuckets) {
            $key = $bucketStart->format($bucketFormat);
            $buckets[$key] = $bucketInitializer();
            $bucketStart->addHour();
        }

        return $buckets;
    }

    /**
     * Aggregate queue counters from Horizon jobs payload.
     *
     * @param array<string, mixed> $job The job.
     * @param string $field The field.
     *
     * @return CarbonInterface|null The parsed timestamp.
     */
    protected function private__parseJobTimestamp(array $job, string $field): ?CarbonInterface
    {
        $raw = $job[$field] ?? null;

        if (blank($raw)) {
            return null;
        }

        try {
            if (\is_numeric($raw)) {
                return Carbon::createFromTimestamp((int) $raw);
            }

            if (\is_string($raw)) {
                return Carbon::parse($raw);
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * Sum jobs by queue names using a precomputed lookup table.
     *
     * @param array<int, string> $queueNames
     * @param array<string, int> $jobsByQueue
     */
    protected function private__sumJobsByQueueNames(array $queueNames, array $jobsByQueue): int
    {
        $jobs = 0;

        foreach ($queueNames as $q) {
            $jobs += $jobsByQueue[$q] ?? 0;
        }

        return $jobs;
    }
}
