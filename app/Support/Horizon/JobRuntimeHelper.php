<?php

namespace App\Support\Horizon;

use Carbon\Carbon;

class JobRuntimeHelper {

    /**
     * Compute the runtime in seconds for a job, using either a precomputed
     * runtime value or the difference between queued_at and processed/failed_at.
     *
     * @param float|null $runtimeSeconds
     * @param Carbon|string|null $queuedAt
     * @param Carbon|string|null $processedAt
     * @param Carbon|string|null $failedAt
     * @return float|null
     */
    public static function getRuntimeSeconds(
        ?float $runtimeSeconds,
        $queuedAt,
        $processedAt,
        $failedAt
    ): ?float {
        if ($runtimeSeconds !== null && $runtimeSeconds >= 0) {
            return $runtimeSeconds;
        }

        $start = self::private__normalizeToCarbon($queuedAt);
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
     * @param float|null $seconds
     * @return string|null
     */
    public static function getFormattedRuntime(?float $seconds): ?string {
        if ($seconds === null) {
            return null;
        }

        return \number_format($seconds, 2) . ' s';
    }

    /**
     * @param Carbon|string|null $value
     * @return Carbon|null
     */
    private static function private__normalizeToCarbon($value): ?Carbon {
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
