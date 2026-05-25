<?php

namespace App\Services\Jobs;

use App\Models\Service;
use App\Services\Horizon\HorizonClientService;
use App\Support\Horizon\HorizonJobPaginator;
use Carbon\Carbon;

final class JobsWindowFetcher
{
    /**
     * The Horizon API client.
     */
    private HorizonClientService $horizonApi;

    /**
     * @var array<string, list<array<string, mixed>>>
     */
    private array $memo = [];

    /**
     * Construct the fetcher.
     */
    public function __construct(HorizonClientService $horizonApi)
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
