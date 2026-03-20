<?php

namespace App\Services\Metrics;

use App\Models\Service;
use App\Services\HorizonApiProxyService;
use App\Support\Horizon\QueueNameNormalizer;
use Carbon\Carbon;

abstract class HorizonMetricsComputation {

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
     * Maximum number of API pages to fetch when building 24h metrics (avoids infinite loops).
     */
    protected const METRICS_24H_MAX_PAGES = 20;

    /**
     * @var HorizonApiProxyService
     */
    protected HorizonApiProxyService $horizonApi;

    public function __construct(HorizonApiProxyService $horizonApi) {
        $this->horizonApi = $horizonApi;
    }

    /**
     * Fetch all completed jobs with completed_at >= $sinceTimestamp by paginating the Horizon API.
     *
     * @param Service $service
     * @param int $sinceTimestamp
     * @param int $pageLimit
     * @return list<array<string, mixed>>
     */
    protected function private__fetchCompletedJobsInWindow(Service $service, int $sinceTimestamp, int $pageLimit): array {
        return $this->private__fetchJobsInWindow(
            $sinceTimestamp,
            $pageLimit,
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
     * Fetch all failed jobs with failed_at >= $sinceTimestamp by paginating the Horizon API.
     *
     * @param Service $service
     * @param int $sinceTimestamp
     * @param int $pageLimit
     * @return list<array<string, mixed>>
     */
    protected function private__fetchFailedJobsInWindow(Service $service, int $sinceTimestamp, int $pageLimit): array {
        return $this->private__fetchJobsInWindow(
            $sinceTimestamp,
            $pageLimit,
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
     * @param int $sinceTimestamp
     * @param int $pageLimit
     * @param callable(array<string, mixed>): array{success: bool, data?: array<string, mixed>} $pageFetcher
     * @param callable(array<string, mixed>): ?int $jobTimestampExtractor
     * @return list<array<string, mixed>>
     */
    protected function private__fetchJobsInWindow(
        int $sinceTimestamp,
        int $pageLimit,
        callable $pageFetcher,
        callable $jobTimestampExtractor,
    ): array {
        $jobs = [];
        $startingAt = -1;
        $page = 0;

        while ($page < self::METRICS_24H_MAX_PAGES) {
            $response = $pageFetcher([
                'starting_at' => $startingAt,
                'limit' => $pageLimit,
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

            if ($oldestInBatch === null || $oldestInBatch < $sinceTimestamp || \count($batch) < $pageLimit) {
                break;
            }
            $startingAt = $oldestInBatch;
            $page++;
        }

        return $jobs;
    }

    /**
     * Get services that can provide Horizon metrics.
     *
     * @param int|null $service_id
     * @param bool $orderByName
     * @param array<int, string> $selectColumns
     * @return \Illuminate\Database\Eloquent\Collection<int, Service>
     */
    protected function private__getServicesForMetrics(?int $service_id = null, bool $orderByName = false, array $selectColumns = []): \Illuminate\Database\Eloquent\Collection {
        $servicesQuery = Service::query()->whereNotNull('base_url');

        if ($service_id !== null) {
            $servicesQuery->where('id', $service_id);
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
     * Initialize hourly buckets between $since and $endHour (inclusive).
     *
     * @param Carbon $since
     * @param Carbon $endHour
     * @param string $bucketFormat
     * @param int $maxBuckets
     * @param callable(): array $bucketInitializer
     * @return array<string, array<string, mixed>>
     */
    protected function private__initHourlyBuckets(Carbon $since, Carbon $endHour, string $bucketFormat, int $maxBuckets, callable $bucketInitializer): array {
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
     * Extract a normalized queue list from supervisor options.
     *
     * @param array<string, mixed> $options
     * @return array<int, string>
     */
    protected function private__extractQueuesFromSupervisorOptions(array $options): array {
        $queues = $options['queue'] ?? null;
        if (! \is_array($queues)) {
            return $queues !== null && $queues !== '' ? [(string) $queues] : [];
        }

        return \array_map('strval', $queues);
    }

    /**
     * Sum jobs by queue names using a precomputed lookup table.
     *
     * @param array<int, string> $queueNames
     * @param array<string, int> $jobsByQueue
     * @return int
     */
    protected function private__sumJobsByQueueNames(array $queueNames, array $jobsByQueue): int {
        $jobs = 0;

        foreach ($queueNames as $q) {
            $jobs += $jobsByQueue[$q] ?? 0;
        }

        return $jobs;
    }

    /**
     * Aggregate queue counters from Horizon jobs payload.
     *
     * @param mixed $jobsPayload
     * @param int $sinceTimestamp
     * @param string $timestampField
     * @param array<string, int> $queueCounts
     * @return void
     */
    protected function private__aggregateQueueCountsFromJobsPayload(mixed $jobsPayload, int $sinceTimestamp, string $timestampField, array &$queueCounts): void {
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
     * Get queue rows (name + 0 jobs) from masters/supervisors when workload API returns nothing.
     *
     * @param Service $service
     * @return array<int, array{queue: string, jobs: int, processes: int|null, wait: float|null}>
     */
    protected function private__getWorkloadFallbackFromMasters(Service $service): array {
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
                    if ($q !== '') {
                        $queueNames[$q] = true;
                    }
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
}
