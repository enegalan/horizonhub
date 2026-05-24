<?php

namespace App\Support\Horizon;

final class HorizonJobPaginator
{
    /**
     * Paginate Horizon job list responses until no more pages.
     *
     * @param callable(array<string, mixed>): array{success: bool, data?: array<string, mixed>} $pageFetcher
     *
     * @return list<mixed>
     */
    public static function fetchAllPages(callable $pageFetcher): array
    {
        $maxPages = (int) config('horizonhub.max_horizon_pages');
        $jobsPerRequest = (int) config('horizonhub.horizon_api_job_list_page_size');
        $accumulated = [];
        $startingAt = -1;

        for ($pageIdx = 0; $pageIdx < $maxPages; $pageIdx++) {
            $response = $pageFetcher([
                'starting_at' => $startingAt,
                'limit' => $jobsPerRequest,
            ]);

            if (! $response['success']) {
                break;
            }

            $batch = self::private__jobsFromResponse($response);

            if ($batch === null || empty($batch)) {
                break;
            }

            foreach ($batch as $job) {
                $accumulated[] = $job;
            }

            if (\count($batch) < $jobsPerRequest) {
                break;
            }

            $startingAt = self::private__nextStartingAt($startingAt, $batch);
        }

        return $accumulated;
    }

    /**
     * Paginate until jobs fall before $sinceTimestamp.
     *
     * @param callable(array<string, mixed>): array{success: bool, data?: array<string, mixed>} $pageFetcher
     * @param callable(array<string, mixed>): ?int $jobTimestampExtractor
     *
     * @return list<array<string, mixed>>
     */
    public static function fetchSinceTimestamp(int $sinceTimestamp, callable $pageFetcher, callable $jobTimestampExtractor): array
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

            $batch = self::private__jobsFromResponse($response);

            if ($batch === null || empty($batch)) {
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

            $startingAt = self::private__nextStartingAt($startingAt, $batch);
            $page++;
        }

        return $jobs;
    }

    /**
     * Extract the jobs from the response.
     *
     * @param array<string, mixed> $response
     *
     * @return list<mixed>|null
     */
    private static function private__jobsFromResponse(array $response): ?array
    {
        $jobs = $response['data']['jobs'] ?? null;

        return \is_array($jobs) ? $jobs : null;
    }

    /**
     * Extract the next starting at value.
     *
     * @param int $startingAt The starting at value.
     * @param list<mixed> $batch The batch.
     *
     * @return int The next starting at value.
     */
    private static function private__nextStartingAt(int $startingAt, array $batch): int
    {
        $last = $batch[\array_key_last($batch)];

        if (\is_array($last) && isset($last['index'])) {
            return (int) $last['index'];
        }

        return \max(0, $startingAt + 1) + \count($batch) - 1;
    }
}
