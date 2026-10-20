<?php

namespace App\Support;

final class ArrayFormatter
{
    /**
     * Return the value when it is an array, otherwise an empty array.
     *
     * @param mixed $value The value to coerce.
     *
     * @return array<mixed>
     */
    public static function orEmpty(mixed $value): array
    {
        return \is_array($value) ? $value : [];
    }
}
