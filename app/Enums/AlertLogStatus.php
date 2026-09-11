<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AlertLogStatus: string
{
    use HasOptions;
    case Failed = 'failed';

    case Sent = 'sent';

    public static function labels(): array
    {
        return [
            self::Sent->value => 'Sent',
            self::Failed->value => 'Failed',
        ];
    }
}
