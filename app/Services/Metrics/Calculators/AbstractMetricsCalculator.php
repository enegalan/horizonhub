<?php

namespace App\Services\Metrics\Calculators;

use App\Models\Service;
use App\Services\Horizon\HorizonClientService;
use App\Services\Jobs\JobsWindowFetcherService;
use App\Support\Jobs\JobRuntime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

abstract class AbstractMetricsCalculator
{
    /**
     * The number of top queues to return.
     *
     * @var int
     */
    public const TOP_N_QUEUES = 12; // TODO: make this configurable.

    /**
     * The Horizon API proxy service.
     */
    protected HorizonClientService $horizonApi;

    /**
     * The jobs window fetcher.
     */
    protected JobsWindowFetcherService $jobsWindowFetcher;

    /**
     * The constructor.
     *
     * @param HorizonClientService $horizonApi The horizon API client.
     * @param JobsWindowFetcherService $jobsWindowFetcher The jobs window fetcher.
     */
    public function __construct(HorizonClientService $horizonApi, JobsWindowFetcherService $jobsWindowFetcher)
    {
        $this->horizonApi = $horizonApi;
        $this->jobsWindowFetcher = $jobsWindowFetcher;
    }

    /**
     * Fill hourly buckets with completed and failed job counts for the given services.
     *
     * @param array<string, array<string, mixed>> $buckets
     * @param Collection<int, Service> $services
     */
    protected function private__accumulateCompletedFailedHourlyBuckets(
        array &$buckets,
        Collection $services,
        int $sinceTimestamp,
        string $bucketFormat,
        string $completedKey,
        string $failedKey,
    ): void {
        /** @var Service $service */
        foreach ($services as $service) {
            $this->private__incrementHourlyBuckets(
                $buckets,
                $this->jobsWindowFetcher->fetchCompletedJobsSince($service, $sinceTimestamp),
                'completed_at',
                $completedKey,
                $sinceTimestamp,
                $bucketFormat,
            );
            $this->private__incrementHourlyBuckets(
                $buckets,
                $this->jobsWindowFetcher->fetchFailedJobsSince($service, $sinceTimestamp),
                'failed_at',
                $failedKey,
                $sinceTimestamp,
                $bucketFormat,
            );
        }
    }

    /**
     * Format hourly bucket keys as chart axis labels.
     *
     * @param array<string, mixed> $buckets
     *
     * @return list<string>
     */
    protected function private__hourlyAxisLabels(array $buckets): array
    {
        $xAxis = [];

        foreach (\array_keys($buckets) as $k) {
            $xAxis[] = Carbon::parse($k)->format('d/m H:i');
        }

        return $xAxis;
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
            $at = JobRuntime::parseJobTimestamp($job[$timestampField] ?? null);

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
