<?php

namespace App\Support;

final class NumberFormatter
{
    /**
     * Count an array or Countable, or 0 when the value is not countable.
     *
     * @param mixed $value The value.
     */
    public static function count(mixed $value): int
    {
        return \is_countable($value) ? \count($value) : 0;
    }

    /**
     * Coerce a numeric value to float, or null when it is not numeric.
     *
     * @param mixed $value The value.
     */
    public static function floatOrNull(mixed $value): ?float
    {
        return \is_numeric($value) ? (float) $value : null;
    }

    /**
     * Coerce a numeric value to int, or null when it is not numeric.
     *
     * @param mixed $value The value.
     */
    public static function intOrNull(mixed $value): ?int
    {
        return \is_numeric($value) ? (int) $value : null;
    }

    /**
     * Coerce a numeric value to a non-negative integer.
     *
     * Non-numeric values yield 0.
     *
     * @param mixed $value The value.
     */
    public static function nonNegativeInt(mixed $value): int
    {
        return \is_numeric($value) ? \max(0, (int) $value) : 0;
    }
}
