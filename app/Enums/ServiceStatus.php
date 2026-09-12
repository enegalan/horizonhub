<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ServiceStatus: string
{
    use HasOptions;
    case Offline = 'offline';

    case Online = 'online';
    case StandBy = 'stand_by';

    public static function labels(): array
    {
        return [
            self::Online->value => 'Online',
            self::StandBy->value => 'Stand by',
            self::Offline->value => 'Offline',
        ];
    }
}
