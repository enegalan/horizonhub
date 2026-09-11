<?php

namespace App\Enums\Concerns;

trait HasOptions
{
    /**
     * Get the value => label options.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return self::labels();
    }

    /**
     * Get all backing values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return \array_column(self::cases(), 'value');
    }

    /**
     * Get the human-readable label of the enum case.
     */
    public function label(): string
    {
        return self::labels()[$this->value] ?? $this->value;
    }

    /**
     * Get the label map for each backing value.
     *
     * @return array<string, string>
     */
    abstract public static function labels(): array;
}
