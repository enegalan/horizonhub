<?php

namespace App\Services\Horizon;

use App\Models\Service;
use Carbon\Carbon;

final class HorizonJobsWindowFetcher
{
    /**
     * The Horizon API proxy service.
     */
    private HorizonApiProxyService $horizonApi;

    /**
     * Construct the fetcher.
     */
    public function __construct(HorizonApiProxyService $horizonApi)
    {
        $this->horizonApi = $horizonApi;
    }

    /**
     * Fetch completed jobs with completed_at >= $sinceTimestamp by paginating the Horizon API.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchCompletedJobsSince(Service $service, int $sinceTimestamp): array
    {
        return $this->private__fetchJobsInWindow(
            $sinceTimestamp,
            function (array $query) use ($service): array {
                return $this->horizonApi->getCompletedJobs($service, $query);
            },
            static function (array $job): ?int {
                return self::private__extractTimestamp($job['completed_at'] ?? $job['processed_at'] ?? null);
            },
        );
    }

    /**
     * Fetch failed jobs with failed_at >= $sinceTimestamp by paginating the Horizon API.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchFailedJobsSince(Service $service, int $sinceTimestamp): array
    {
        return $this->private__fetchJobsInWindow(
            $sinceTimestamp,
            function (array $query) use ($service): array {
                return $this->horizonApi->getFailedJobs($service, $query);
            },
            static function (array $job): ?int {
                return self::private__extractTimestamp($job['failed_at'] ?? null);
            },
        );
    }

    private static function private__extractTimestamp(mixed $value): ?int
    {
        if (blank($value)) {
            return null;
        }

        if (\is_numeric($value)) {
            return (int) $value;
        }

        if (\is_string($value)) {
            try {
                return Carbon::parse($value)->getTimestamp();
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Fetch jobs in a time window by paginating Horizon until we cross the lower bound.
     *
     * @param callable(array<string, mixed>): array{success: bool, data?: array<string, mixed>} $pageFetcher
     * @param callable(array<string, mixed>): ?int $jobTimestampExtractor
     *
     * @return list<array<string, mixed>>
     */
    private function private__fetchJobsInWindow(int $sinceTimestamp, callable $pageFetcher, callable $jobTimestampExtractor): array
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

            if (! $response['success']) {
                break;
            }
            $batch = $response['data']['jobs'] ?? [];

            if (! \is_array($batch) || empty($batch)) {
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
            $last = $batch[\array_key_last($batch)];

            if (\is_array($last) && isset($last['index'])) {
                $startingAt = (int) $last['index'];
            } else {
                $startingAt = \max(0, $startingAt + 1) + \count($batch) - 1;
            }

            $page++;
        }

        return $jobs;
    }
}
