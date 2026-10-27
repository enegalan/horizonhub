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
     * stages. The scan stops after `job_list_scan_cap` matching jobs (or the
     * derived page budget), regardless of filters.
     *
     * @param callable(array<string, mixed>): array{success: bool, data?: array<string, mixed>} $pageFetcher
     * @param string|null $search The search string, matched against queue, job name and UUID.
     * @param (callable(array<string, mixed>): bool)|null $jobFilter Additional local filter.
     *
     * @return array{jobs: list<array<string, mixed>>, complete: bool} The matching jobs and whether the source was fully read.
     */
    public static function fetchFiltered(callable $pageFetcher, ?string $search = null, ?callable $jobFilter = null): array
    {
        $chunk = config('horizonhub.job_list_chunk');
        $scanCap = config('horizonhub.job_list_scan_cap');
        $maxPages = self::private__maxPages();
        $term = (string) $search;
        $accumulated = [];
        $startingAt = -1;
        $sourceExhausted = false;

        for ($pageIdx = 0; $pageIdx < $maxPages; $pageIdx++) {
            $batch = ClientResponse::data($pageFetcher([
                'starting_at' => $startingAt,
                'limit' => $chunk,
            ]), 'jobs');

            if ($batch === null) {
                break;
            }

            if (empty($batch)) {
                $sourceExhausted = true;
                break;
            }

            $rawCount = \count($batch);
            $batch = \array_values(\array_slice($batch, 0, $chunk));

            foreach ($batch as $job) {
                if (! \is_array($job)) {
                    continue;
                }

                if (! JobSearchFilter::matches($job, $term)) {
                    continue;
                }

                if ($jobFilter !== null && ! $jobFilter($job)) {
                    continue;
                }

                $accumulated[] = $job;

                if (\count($accumulated) >= $scanCap) {
                    return [
                        'jobs' => $accumulated,
                        'complete' => false,
                    ];
                }
            }

            if ($rawCount < $chunk) {
                $sourceExhausted = true;
                break;
            }

            $startingAt = self::private__nextStartingAt($startingAt, $batch);
        }

        return [
            'jobs' => $accumulated,
            'complete' => $sourceExhausted,
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
        $chunk = config('horizonhub.job_list_chunk');
        $maxPages = self::private__maxPages();

        while ($page < $maxPages) {
            $batch = ClientResponse::data($pageFetcher([
                'starting_at' => $startingAt,
                'limit' => $chunk,
            ]), 'jobs');

            if ($batch === null || empty($batch)) {
                break;
            }

            $rawCount = \count($batch);
            $batch = \array_values(\array_slice($batch, 0, $chunk));
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

            if ($oldestInBatch === null || $oldestInBatch < $sinceTimestamp || $rawCount < $chunk) {
                break;
            }

            $startingAt = self::private__nextStartingAt($startingAt, $batch);
            $page++;
        }

        return $jobs;
    }

    /**
     * Get the maximum number of pages.
     *
     * @return int The maximum number of pages.
     */
    private static function private__maxPages(): int
    {
        return \max(1, (int) \ceil(config('horizonhub.job_list_scan_cap') / config('horizonhub.job_list_chunk')));
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
