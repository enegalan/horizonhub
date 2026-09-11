<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AlertRuleType: string
{
    use HasOptions;
    case AvgExecutionTime = 'avg_execution_time';

    case FailureCount = 'failure_count';
    case HorizonOffline = 'horizon_offline';
    case QueueBlocked = 'queue_blocked';
    case SupervisorOffline = 'supervisor_offline';
    case WorkerOffline = 'worker_offline';

    public static function labels(): array
    {
        return [
            self::FailureCount->value => 'Failure count in window',
            self::AvgExecutionTime->value => 'Avg execution time exceeded',
            self::QueueBlocked->value => 'Queue blocked',
            self::WorkerOffline->value => 'Worker offline',
            self::SupervisorOffline->value => 'Supervisor offline',
            self::HorizonOffline->value => 'Horizon offline',
        ];
    }
}
