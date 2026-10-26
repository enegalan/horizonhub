<?php

namespace App\Support;

final class BoolFormatter
{
    /**
     * Coerce a value to bool, or null when it is not configured.
     *
     * Null yields null. Other values use PHP's boolean filter so Horizon
     * option strings such as "true" / "false" are accepted.
     *
     * @param mixed $value The value.
     */
    public static function orNull(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return \filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
