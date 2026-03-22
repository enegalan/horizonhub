<?php

namespace App\Support\Horizon;

class ConfigHelper
{
    /**
     * Get a configuration value.
     *
     * @param  string  $key
     * @param  mixed|null  $default
     */
    public static function get($key, $default = null): mixed
    {
        return \config($key, $default);
    }

    /**
     * Get a configuration value as an integer with a minimum value.
     *
     * @param  string  $key
     * @param  int  $min
     * @param  mixed  $default
     * @return int|null
     */
    public static function getIntWithMin($key, $min = 0, $default = null): int
    {
        $value = (int) self::get($key, $default);
        if (\is_numeric($min) && $value < $min) {
            return $min;
        }

        return $value;
    }
}
