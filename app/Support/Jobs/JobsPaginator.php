<?php

namespace App\Support\Jobs;

use App\Support\Horizon\ClientResponse;

final class JobsPaginator
{
    /**
     * Paginate Horizon job list responses, keeping only the matching jobs.
     *
     * Matching runs on each job as soon as its page is received, so
     * non-matching jobs never reach the expensive row mapping and sorting
     * stages. When $maxMatches is a positive integer the loop stops as soon as
     * that many jobs matched, leaving the remaining pages unread; callers that
     * need a complete result set (for example bulk retries) pass null.
     *
     * @param callable(array<string, mixed>): array{success: bool, data?: array<string, mixed>} $pageFetcher
     * @param string|null $search The search string, matched against queue, job name and UUID.
     * @param int|null $maxMatches The maximum number of matching jobs, or null for no limit.
     *
     * @return array{jobs: list<array<string, mixed>>, complete: bool} The matching jobs and whether the source was fully read.
     */
    public static function fetchFiltered(callable $pageFetcher, ?string $search = null, ?int $maxMatches = null): array
    {
        $maxPages = config('horizonhub.max_horizon_pages');
        $jobsPerRequest = config('horizonhub.horizon_api_job_list_page_size');
        $limit = $maxMatches !== null && $maxMatches > 0 ? $maxMatches : null;
        $term = (string) $search;
        $accumulated = [];
        $startingAt = -1;

        for ($pageIdx = 0; $pageIdx < $maxPages; $pageIdx++) {
            $batch = ClientResponse::data($pageFetcher([
                'starting_at' => $startingAt,
                'limit' => $jobsPerRequest,
            ]), 'jobs');

            if ($batch === null || empty($batch)) {
                break;
            }

            foreach ($batch as $job) {
                if (! \is_array($job)) {
                    continue;
                }

                if (! JobSearchFilter::matches($job, $term)) {
                    continue;
                }

                $accumulated[] = $job;

                if ($limit !== null && \count($accumulated) >= $limit) {
                    return [
                        'jobs' => $accumulated,
                        'complete' => false,
                    ];
                }
            }

            if (\count($batch) < $jobsPerRequest) {
                break;
            }

            $startingAt = self::private__nextStartingAt($startingAt, $batch);
        }

        return [
            'jobs' => $accumulated,
            'complete' => true,
        ];
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
        $jobsPerRequest = config('horizonhub.horizon_api_job_list_page_size');
        $maxPages = config('horizonhub.max_horizon_pages');

        while ($page < $maxPages) {
            $batch = ClientResponse::data($pageFetcher([
                'starting_at' => $startingAt,
                'limit' => $jobsPerRequest,
            ]), 'jobs');

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
