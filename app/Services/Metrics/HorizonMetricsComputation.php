<?php

namespace App\Services\Metrics;

use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Support\Horizon\QueueNameNormalizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

abstract class HorizonMetricsComputation
{
    /**
     * The number of days in a week.
     *
     * @var int
     */
    protected const DAYS_7 = 7;

    /**
     * The number of top queues to return.
     *
     * @var int
     */
    protected const TOP_N_QUEUES = 12;

    /**
     * The Horizon API proxy service.
     */
    protected HorizonApiProxyService $horizonApi;

    /**
     * Construct the Horizon metrics computation.
     */
    public function __construct(HorizonApiProxyService $horizonApi)
    {
        $this->horizonApi = $horizonApi;
    }

    /**
     * Aggregate queue counters from Horizon jobs payload.
     *
     * @param  mixed  $jobsPayload  The jobs payload.
     * @param  int  $sinceTimestamp  The since timestamp.
     * @param  string  $timestampField  The timestamp field.
     * @param  array<string, int>  $queueCounts  The queue counts.
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
     * @param  array<string, mixed>  $options
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

            return $queue !== null && $queue !== '' ? [$queue] : [];
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
     * Fetch completed jobs with completed_at >= $sinceTimestamp by paginating the Horizon API.
     *
     * Each request uses Horizon query `limit` = horizonhub.horizon_api_job_list_page_size.
     * The number of HTTP pages per service is capped by horizonhub.max_horizon_pages.
     *
     * @param  Service  $service  The service.
     * @param  int  $sinceTimestamp  The since timestamp.
     * @return list<array<string, mixed>>
     */
    protected function private__fetchCompletedJobsInWindow(Service $service, int $sinceTimestamp): array
    {
        return $this->private__fetchJobsInWindow(
            $sinceTimestamp,
            function (array $query) use ($service): array {
                return $this->horizonApi->getCompletedJobs($service, $query);
            },
            static function (array $job): ?int {
                $completedAt = $job['completed_at'] ?? $job['processed_at'] ?? null;
                if (! \is_numeric($completedAt)) {
                    return null;
                }

                return (int) $completedAt;
            },
        );
    }

    /**
     * Fetch failed jobs with failed_at >= $sinceTimestamp by paginating the Horizon API.
     *
     * Each request uses Horizon query `limit` = horizonhub.horizon_api_job_list_page_size.
     * The number of HTTP pages per service is capped by horizonhub.max_horizon_pages.
     *
     * @param  Service  $service  The service.
     * @param  int  $sinceTimestamp  The since timestamp.
     * @return list<array<string, mixed>>
     */
    protected function private__fetchFailedJobsInWindow(Service $service, int $sinceTimestamp): array
    {
        return $this->private__fetchJobsInWindow(
            $sinceTimestamp,
            function (array $query) use ($service): array {
                return $this->horizonApi->getFailedJobs($service, $query);
            },
            static function (array $job): ?int {
                $failedAt = $job['failed_at'] ?? null;
                if (! \is_numeric($failedAt)) {
                    return null;
                }

                return (int) $failedAt;
            },
        );
    }

    /**
     * Fetch jobs in a time window by paginating Horizon until we cross the lower bound.
     *
     * @param  int  $sinceTimestamp  The since timestamp.
     * @param  callable(array<string, mixed>): array{success: bool, data?: array<string, mixed>}  $pageFetcher  The page fetcher.
     * @param  callable(array<string, mixed>): ?int  $jobTimestampExtractor  The job timestamp extractor.
     * @return list<array<string, mixed>>
     */
    protected function private__fetchJobsInWindow(int $sinceTimestamp, callable $pageFetcher, callable $jobTimestampExtractor): array
    {
        $jobs = [];
        $startingAt = -1;
        $page = 0;
        $jobsPerRequest = (int) config('horizonhub.horizon_api_job_list_page_size');
        $maxPages = (int) config('horizonhub.max_horizon_pages');

        while ($page < $maxPages) {
            $response = $pageFetcher([
                'starting_at' => $startingAt,
                'limit' => $jobsPerRequest,
            ]);
            if (! ($response['success'] ?? false)) {
                break;
            }
            $batch = $response['data']['jobs'] ?? [];
            if (! \is_array($batch) || $batch === []) {
                break;
            }
            $oldestInBatch = null;
            foreach ($batch as $job) {
                if (! \is_array($job)) {
                    continue;
                }

                $ts = $jobTimestampExtractor($job);
                if ($ts === null) {
                    continue;
                }

                if ($ts >= $sinceTimestamp) {
                    $jobs[] = $job;
                }

                if ($oldestInBatch === null || $ts < $oldestInBatch) {
                    $oldestInBatch = $ts;
                }
            }

            if ($oldestInBatch === null || $oldestInBatch < $sinceTimestamp || \count($batch) < $jobsPerRequest) {
                break;
            }
            $startingAt = $this->private__nextMetricsJobsStartingAt($startingAt, $batch);
            $page++;
        }

        return $jobs;
    }

    /**
     * Get services that can provide Horizon metrics.
     *
     * @param  list<int>|null  $serviceScope  The service scope. Empty = all services with base_url; non-empty = restrict by id.
     * @param  bool  $orderByName  The order by name.
     * @param  array<int, string>  $selectColumns  The select columns.
     * @return Collection<int, Service>
     */
    protected function private__getServicesForMetrics(array $serviceScope = [], bool $orderByName = false, array $selectColumns = []): Collection
    {
        $servicesQuery = Service::query()->whereNotNull('base_url');

        if ($serviceScope !== []) {
            $ids = \array_values(\array_unique(\array_filter(
                \array_map(static fn ($v): int => (int) $v, $serviceScope),
                static fn (int $id): bool => $id > 0,
            )));
            if ($ids === []) {
                return new Collection;
            }
            $servicesQuery->whereIn('id', $ids);
        }

        if ($orderByName) {
            $servicesQuery->orderBy('name');
        }

        if ($selectColumns !== []) {
            return $servicesQuery->get($selectColumns);
        }

        return $servicesQuery->get();
    }

    /**
     * Get queue rows (name + 0 jobs) from masters/supervisors when workload API returns nothing.
     *
     * @param  Service  $service  The service.
     * @return array<int, array{queue: string, jobs: int, processes: int|null, wait: float|null}>
     */
    protected function private__getWorkloadFallbackFromMasters(Service $service): array
    {
        $mastersResponse = $this->horizonApi->getMasters($service);
        $mastersData = $mastersResponse['data'] ?? null;
        if (! ($mastersResponse['success'] ?? false) || ! \is_array($mastersData)) {
            return [];
        }

        $queueNames = [];
        foreach ($mastersData as $master) {
            if (! \is_array($master)) {
                continue;
            }

            $supervisors = $master['supervisors'] ?? null;
            if (! \is_array($supervisors)) {
                continue;
            }

            foreach ($supervisors as $supervisor) {
                if (! \is_array($supervisor)) {
                    continue;
                }

                $options = isset($supervisor['options']) && \is_array($supervisor['options']) ? $supervisor['options'] : [];
                $queues = $options['queue'] ?? null;
                if (! \is_array($queues)) {
                    $queues = $queues !== null && $queues !== '' ? [(string) $queues] : [];
                } else {
                    $queues = \array_map('strval', $queues);
                }

                foreach ($queues as $q) {
                    if (empty($q)) {
                        continue;
                    }

                    $normalizedQueue = QueueNameNormalizer::normalize($q);
                    if (empty($normalizedQueue)) {
                        continue;
                    }

                    $queueNames[$normalizedQueue] = true;
                }
            }
        }

        $queueNames = \array_keys($queueNames);
        \sort($queueNames);

        $rows = [];
        foreach ($queueNames as $name) {
            $rows[] = [
                'queue' => $name,
                'jobs' => 0,
                'processes' => null,
                'wait' => null,
            ];
        }

        return $rows;
    }

    /**
     * Initialize hourly buckets between $since and $endHour (inclusive).
     *
     * @param  Carbon  $since  The since.
     * @param  Carbon  $endHour  The end hour.
     * @param  string  $bucketFormat  The bucket format.
     * @param  int  $maxBuckets  The max buckets.
     * @param  callable(): array  $bucketInitializer  The bucket initializer.
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
     * Next Horizon jobs list cursor: starting_at is a zero-based index into the Redis-backed list, not a unix timestamp.
     *
     * @param  int  $startingAt  The starting at.
     * @param  array<int, mixed>  $batch  The batch.
     */
    protected function private__nextMetricsJobsStartingAt(int $startingAt, array $batch): int
    {
        $last = $batch[\array_key_last($batch)];
        if (\is_array($last) && isset($last['index'])) {
            return (int) $last['index'];
        }

        return \max(0, $startingAt + 1) + \count($batch) - 1;
    }

    /**
     * Sum jobs by queue names using a precomputed lookup table.
     *
     * @param  array<int, string>  $queueNames
     * @param  array<string, int>  $jobsByQueue
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
