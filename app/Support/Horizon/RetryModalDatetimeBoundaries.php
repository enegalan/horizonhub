<?php

namespace App\Support\Horizon;

use Carbon\Carbon;

final class RetryModalDatetimeBoundaries
{
    /**
     * Lower bound: date-only strings use start of day; datetimes use parsed instant.
     */
    public static function parseLower(?string $value): ?Carbon
    {
        if ($value === null || \trim($value) === '') {
            return null;
        }
        $trimmed = \trim($value);
        try {
            $parsed = Carbon::parse($trimmed);
        } catch (\Throwable) {
            return null;
        }
        if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1) {
            return $parsed->copy()->startOfDay();
        }

        return $parsed;
    }

    /**
     * Upper bound: date-only strings use end of day; datetimes use parsed instant.
     */
    public static function parseUpper(?string $value): ?Carbon
    {
        if ($value === null || \trim($value) === '') {
            return null;
        }
        $trimmed = \trim($value);
        try {
            $parsed = Carbon::parse($trimmed);
        } catch (\Throwable) {
            return null;
        }
        if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1) {
            return $parsed->copy()->endOfDay();
        }

        return $parsed;
    }
}
