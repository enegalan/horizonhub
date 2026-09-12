<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FlashType: string
{
    use HasOptions;
    case Error = 'error';

    case Success = 'success';
    case Warning = 'warning';

    public static function labels(): array
    {
        return [
            self::Success->value => 'Success',
            self::Error->value => 'Error',
            self::Warning->value => 'Warning',
        ];
    }
}
