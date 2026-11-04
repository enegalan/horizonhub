<?php

namespace App\Support\Batches;

use App\Support\ArrayFormatter;
use App\Support\Jobs\JobRuntime;
use App\Support\NumberFormatter;
use App\Support\StringFormatter;
use Carbon\Carbon;

final class BatchPayloadReader
{
    /**
     * Read one batch and its failed jobs from the Horizon API payload.
     *
     * @param array<mixed> $payload The decoded batch response.
     *
     * @return array{batch: array<string, mixed>|null, failedJobs: list<array<string, mixed>>}
     */
    public static function detail(array $payload): array
    {
        return [
            'batch' => self::normalize($payload['batch'] ?? null),
            'failedJobs' => self::failedJobs($payload['failedJobs'] ?? null),
        ];
    }

    /**
     * Normalize the failed jobs attached to a batch.
     *
     * @return list<array<string, mixed>>
     */
    public static function failedJobs(mixed $value): array
    {
        if (! \is_array($value)) {
            return [];
        }

        $jobs = [];

        foreach ($value as $job) {
            if (! \is_array($job)) {
                continue;
            }

            $id = StringFormatter::trim($job['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $reservedAt = JobRuntime::parseJobTimestamp($job['reserved_at'] ?? null);
            $failedAt = JobRuntime::parseJobTimestamp($job['failed_at'] ?? null);

            $jobs[] = [
                'id' => $id,
                'name' => StringFormatter::trim($job['name'] ?? ''),
                'queue' => StringFormatter::trim($job['queue'] ?? ''),
                'failed_at' => $failedAt,
                'reserved_at' => $reservedAt,
                'runtime' => JobRuntime::getFormattedRuntime(
                    JobRuntime::getRuntimeSeconds(
                        NumberFormatter::floatOrNull($job['runtime'] ?? null),
                        $reservedAt,
                        null,
                        $failedAt,
                    ),
                ),
            ];
        }

        return $jobs;
    }

    /**
     * Normalize one batch payload.
     *
     * @param array<string, mixed>|null $batch The raw batch.
     *
     * @return array<string, mixed>|null
     */
    public static function normalize(?array $batch): ?array
    {
        if (! \is_array($batch)) {
            return null;
        }

        $id = StringFormatter::trim($batch['id'] ?? '');

        if ($id === '') {
            return null;
        }

        $totalJobs = NumberFormatter::nonNegativeInt($batch['totalJobs'] ?? null);
        $pendingJobs = NumberFormatter::nonNegativeInt($batch['pendingJobs'] ?? null);
        $failedJobs = NumberFormatter::nonNegativeInt($batch['failedJobs'] ?? null);
        $processedJobs = NumberFormatter::intOrNull($batch['processedJobs'] ?? null);
        $processedJobs = $processedJobs !== null
            ? \max(0, $processedJobs)
            : \max(0, $totalJobs - $pendingJobs);
        $createdAt = JobRuntime::parseJobTimestamp($batch['createdAt'] ?? null);
        $finishedAt = JobRuntime::parseJobTimestamp($batch['finishedAt'] ?? null);
        $cancelledAt = JobRuntime::parseJobTimestamp($batch['cancelledAt'] ?? null);
        $progressRaw = NumberFormatter::floatOrNull($batch['progress'] ?? null);
        $progress = $progressRaw !== null
            ? (int) \round($progressRaw)
            : ($totalJobs > 0 ? (int) \round(($processedJobs / $totalJobs) * 100) : 0);
        $options = ArrayFormatter::orEmpty($batch['options'] ?? null);

        return [
            'id' => $id,
            'name' => \trim((string) ($batch['name'] ?? '')),
            'total_jobs' => $totalJobs,
            'pending_jobs' => $pendingJobs,
            'processed_jobs' => $processedJobs,
            'failed_jobs' => $failedJobs,
            'progress' => \max(0, \min(100, $progress)),
            'status' => self::status($pendingJobs, $failedJobs, $finishedAt, $cancelledAt),
            'options' => $options,
            'options_json' => \json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'created_at' => $createdAt,
            'finished_at' => $finishedAt,
            'cancelled_at' => $cancelledAt,
        ];
    }

    /**
     * Read and normalize the batches returned by the Horizon API.
     *
     * @param array<mixed> $payload The decoded batches response.
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(array $payload): array
    {
        $batches = $payload['batches'] ?? $payload;

        if (! \is_array($batches)) {
            return [];
        }

        $rows = [];

        foreach ($batches as $batch) {
            $row = self::normalize(\is_array($batch) ? $batch : null);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Derive the batch state shown in the UI.
     *
     * @return 'cancelled'|'failed'|'finished'|'pending'
     */
    public static function status(
        int $pendingJobs,
        int $failedJobs,
        ?Carbon $finishedAt,
        ?Carbon $cancelledAt,
    ): string {
        if ($cancelledAt !== null) {
            return 'cancelled';
        }

        if ($finishedAt !== null || $pendingJobs === 0) {
            return 'finished';
        }

        return $failedJobs > 0 ? 'failed' : 'pending';
    }

    /**
     * Aggregate batch counts from a Horizon batches list.
     *
     * @return array{total: int, inProgress: int, withFailures: int}
     */
    public static function summarize(mixed $batches): array
    {
        if (! \is_array($batches)) {
            return ['total' => 0, 'inProgress' => 0, 'withFailures' => 0];
        }

        $total = 0;
        $inProgress = 0;
        $withFailures = 0;

        foreach ($batches as $batch) {
            $row = self::normalize(\is_array($batch) ? $batch : null);

            if ($row === null) {
                continue;
            }

            $total++;

            if ($row['failed_jobs'] > 0) {
                $withFailures++;
            }

            if ($row['status'] === 'pending' || $row['status'] === 'failed') {
                $inProgress++;
            }
        }

        return [
            'total' => $total,
            'inProgress' => $inProgress,
            'withFailures' => $withFailures,
        ];
    }
}
