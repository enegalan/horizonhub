<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EvaluationStatus: string
{
    use HasOptions;
    case Completed = 'completed';
    case Failed = 'failed';

    case Running = 'running';

    public static function labels(): array
    {
        return [
            self::Running->value => 'Running',
            self::Completed->value => 'Completed',
            self::Failed->value => 'Failed',
        ];
    }
}
