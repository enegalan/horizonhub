<?php

namespace App\Support;

final class StringFormatter
{
    /**
     * Check whether a haystack contains a needle, ignoring case.
     *
     * @param mixed $haystack The haystack; non-string values yield an empty string.
     * @param string $needle The needle.
     */
    public static function containsIgnoreCase(mixed $haystack, string $needle): bool
    {
        return \str_contains(self::lower($haystack), \strtolower($needle));
    }

    /**
     * Lowercase a value; non-string values yield an empty string.
     *
     * @param mixed $value The value to lowercase.
     */
    public static function lower(mixed $value): string
    {
        if (! \is_string($value)) {
            return '';
        }

        return \strtolower($value);
    }

    /**
     * Trim a value and return null when the result is empty.
     *
     * @param mixed $value The value.
     */
    public static function nullIfBlank(mixed $value): ?string
    {
        $trimmed = self::trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Strip whitespace (or other characters) from the beginning and end of a value.
     *
     * Non-string values (null, arrays, objects) yield an empty string so request
     * input and decoded JSON payloads can be passed in safely.
     *
     * @param mixed $value The value to trim.
     * @param string $characters The characters to strip.
     */
    public static function trim(mixed $value, string $characters = " \n\r\t\v\x00"): string
    {
        if (! \is_string($value)) {
            return '';
        }

        return \trim($value, $characters);
    }
}
