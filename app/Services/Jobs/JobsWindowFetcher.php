<?php

namespace App\Services\Jobs;

use App\Models\Service;
use App\Services\Horizon\Contracts\HorizonClientApi;
use App\Support\Horizon\HorizonJobPaginator;
use App\Support\Horizon\JobRuntimeHelper;

final class JobsWindowFetcher
{
    /**
     * The Horizon API client.
     */
    private HorizonClientApi $horizonApi;

    /**
     * @var array<string, list<array<string, mixed>>>
     */
    private array $memo = [];

    /**
     * The constructor.
     *
     * @param HorizonClientApi $horizonApi The horizon API client.
     */
    public function __construct(HorizonClientApi $horizonApi)
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
        $memoKey = $this->private__memoKey($service->id, 'completed', $sinceTimestamp);

        if (isset($this->memo[$memoKey])) {
            return $this->memo[$memoKey];
        }

        $jobs = HorizonJobPaginator::fetchSinceTimestamp(
            $sinceTimestamp,
            function (array $query) use ($service): array {
                return $this->horizonApi->getCompletedJobs($service, $query);
            },
            static function (array $job): ?int {
                return self::private__extractTimestamp($job['completed_at'] ?? $job['processed_at'] ?? null);
            },
        );

        $this->memo[$memoKey] = $jobs;

        return $jobs;
    }

    /**
     * Fetch failed jobs with failed_at >= $sinceTimestamp by paginating the Horizon API.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchFailedJobsSince(Service $service, int $sinceTimestamp): array
    {
        $memoKey = $this->private__memoKey($service->id, 'failed', $sinceTimestamp);

        if (isset($this->memo[$memoKey])) {
            return $this->memo[$memoKey];
        }

        $jobs = HorizonJobPaginator::fetchSinceTimestamp(
            $sinceTimestamp,
            function (array $query) use ($service): array {
                return $this->horizonApi->getFailedJobs($service, $query);
            },
            static function (array $job): ?int {
                return self::private__extractTimestamp($job['failed_at'] ?? null);
            },
        );

        $this->memo[$memoKey] = $jobs;

        return $jobs;
    }

    /**
     * Run a callback with request-scoped memoization for job window fetches.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function runWithMemo(callable $callback): mixed
    {
        $previousMemo = $this->memo;
        $this->memo = [];

        try {
            return $callback();
        } finally {
            $this->memo = $previousMemo;
        }
    }

    /**
     * Extract the timestamp from a value.
     *
     * @param mixed $value The value.
     */
    private static function private__extractTimestamp(mixed $value): ?int
    {
        $parsed = JobRuntimeHelper::parseJobTimestamp($value);

        return $parsed?->getTimestamp();
    }

    /**
     * Generate a memo key for a given service, type, and since timestamp.
     *
     * @param int $serviceId The service ID.
     * @param string $type The type.
     * @param int $sinceTimestamp The since timestamp.
     */
    private function private__memoKey(int $serviceId, string $type, int $sinceTimestamp): string
    {
        return "$serviceId:$type:$sinceTimestamp";
    }
}
