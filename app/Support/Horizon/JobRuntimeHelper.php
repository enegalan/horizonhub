<?php

namespace App\Support\Horizon;

use Carbon\Carbon;

class JobRuntimeHelper
{
    /**
     * Parse a Horizon timestamp value into Carbon.
     *
     * @param  mixed  $value  The value.
     */
    public static function parseJobTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
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
     * Compute the runtime in seconds for a job, using either a precomputed
     * runtime value or the difference between reserved_at and processed/failed_at.
     *
     * @param  float|null  $runtimeSeconds  The runtime seconds.
     * @param  Carbon|string|null  $reservedAt  The reserved at.
     * @param  Carbon|string|null  $processedAt  The processed at.
     * @param  Carbon|string|null  $failedAt  The failed at.
     */
    public static function getRuntimeSeconds(?float $runtimeSeconds, Carbon|string|null $reservedAt, Carbon|string|null $processedAt, Carbon|string|null $failedAt): ?float
    {
        if ($runtimeSeconds !== null && $runtimeSeconds >= 0) {
            return $runtimeSeconds;
        }

        $start = self::private__normalizeToCarbon($reservedAt);
        $end = self::private__normalizeToCarbon($processedAt) ?? self::private__normalizeToCarbon($failedAt);

        if ($start === null || $end === null) {
            return null;
        }

        $seconds = $start->diffInMilliseconds($end, false) / 1000;

        return $seconds < 0 ? null : $seconds;
    }

    /**
     * Human-readable runtime in seconds (e.g. "0.08 s", "1.23 s").
     *
     * @param  float|null  $seconds  The seconds.
     */
    public static function getFormattedRuntime(?float $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        return \number_format($seconds, 2).' s';
    }

    /**
     * Normalise processed/failed timestamps according to job status.
     *
     * - For "processed" jobs, failed_at is cleared.
     * - For "failed" jobs, processed_at is cleared.
     * - For "processing" jobs, both processed_at and failed_at are cleared.
     *
     * @param  string|null  $status  The status.
     * @param  Carbon|string|null  $processedAt  The processed at.
     * @param  Carbon|string|null  $failedAt  The failed at.
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
     * Normalize a value to a Carbon instance.
     *
     * @param  Carbon|string|null  $value  The value.
     */
    private static function private__normalizeToCarbon(Carbon|string|null $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
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
}
