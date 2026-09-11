<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum HorizonStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Inactive = 'inactive';
    case Paused = 'paused';
    case Running = 'running';

    public static function labels(): array
    {
        return [
            self::Active->value => 'Active',
            self::Running->value => 'Running',
            self::Inactive->value => 'Inactive',
            self::Paused->value => 'Paused',
        ];
    }

    /**
     * Whether the status means Horizon is up and processing.
     */
    public function isActive(): bool
    {
        return \in_array($this, [self::Active, self::Running], true);
    }
}
