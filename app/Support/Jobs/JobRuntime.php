<?php

namespace App\Support\Jobs;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class JobRuntime
{
    /**
     * Human-readable duration in seconds (e.g. "0.08 s", "1.23 s").
     *
     * @param float|null $seconds The seconds.
     */
    public static function getFormattedRuntime(?float $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        return \number_format($seconds, 2) . ' s';
    }

    /**
     * Compute the runtime in seconds for a job, using either a precomputed
     * runtime value or the difference between reserved_at and processed/failed_at.
     *
     * @param float|null $runtimeSeconds The runtime seconds.
     * @param Carbon|string|null $reservedAt The reserved at.
     * @param Carbon|string|null $processedAt The processed at.
     * @param Carbon|string|null $failedAt The failed at.
     */
    public static function getRuntimeSeconds(?float $runtimeSeconds, Carbon|string|null $reservedAt, Carbon|string|null $processedAt, Carbon|string|null $failedAt): ?float
    {
        if ($runtimeSeconds !== null && $runtimeSeconds >= 0) {
            return $runtimeSeconds;
        }

        $start = self::parseJobTimestamp($reservedAt);
        $end = self::parseJobTimestamp($processedAt) ?? self::parseJobTimestamp($failedAt);

        if ($start === null || $end === null) {
            return null;
        }

        $seconds = $start->diffInMilliseconds($end, false) / 1000;

        return $seconds < 0 ? null : $seconds;
    }

    /**
     * Normalise processed/failed timestamps according to job status.
     *
     * - For "processed" jobs, failed_at is cleared.
     * - For "failed" jobs, processed_at is cleared.
     * - For "processing" jobs, both processed_at and failed_at are cleared.
     *
     * @param string|null $status The status.
     * @param Carbon|string|null $processedAt The processed at.
     * @param Carbon|string|null $failedAt The failed at.
     */
    public static function normalizeStatusDates(?string $status, Carbon|string|null &$processedAt, Carbon|string|null &$failedAt): void
    {
        if ($status === 'processed') {
            $failedAt = null;
        } elseif ($status === 'failed') {
            $processedAt = null;
        } elseif ($status === 'processing') {
            $processedAt = null;
            $failedAt = null;
        }
    }

    /**
     * Parse a Horizon timestamp value into Carbon.
     *
     * @param mixed $value The value.
     */
    public static function parseJobTimestamp(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof CarbonInterface || $value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (\is_numeric($value)) {
            $seconds = (float) $value;

            return Carbon::createFromTimestampMs((int) \round($seconds * 1000));
        }

        if (\is_string($value)) {
            try {
                return Carbon::parse($value);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Resolve queued / reserved / end timestamps and formatted runtime from a Horizon job payload.
     *
     * @param array<string, mixed> $job
     * @param array<string, mixed> $payload
     *
     * @return array{
     *     queued_at: Carbon|null,
     *     reserved_at: Carbon|null,
     *     processed_at: Carbon|null,
     *     failed_at: Carbon|null,
     *     available_at: Carbon|null,
     *     runtime: string|null
     * }
     */
    public static function resolveJobTimingFields(array $job, array $payload, string $status): array
    {
        $queuedAt = self::parseJobTimestamp($payload['pushedAt'] ?? $job['pushedAt'] ?? null);
        $reservedAt = self::parseJobTimestamp($job['reserved_at'] ?? $payload['reserved_at'] ?? null);
        $processedAt = self::parseJobTimestamp($job['completed_at'] ?? null);
        $failedAt = self::parseJobTimestamp($job['failed_at'] ?? null);
        self::normalizeStatusDates($status, $processedAt, $failedAt);

        $commandData = JobCommandDataExtractor::extract($payload);
        $availableAt = isset($commandData['delay']['date'])
            ? self::parseJobTimestamp($commandData['delay']['date'])
            : null;

        $runtimeSeconds = isset($job['runtime']) && \is_numeric($job['runtime'])
            ? (float) $job['runtime']
            : null;

        return [
            'queued_at' => $queuedAt,
            'reserved_at' => $reservedAt,
            'processed_at' => $processedAt,
            'failed_at' => $failedAt,
            'available_at' => $availableAt,
            'runtime' => self::getFormattedRuntime(
                self::getRuntimeSeconds($runtimeSeconds, $reservedAt, $processedAt, $failedAt),
            ),
        ];
    }
}
