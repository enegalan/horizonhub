<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Enums\AlertRuleType;
use App\Models\Alert;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy as AlertRuleContract;

final class NullRule implements AlertRuleContract
{
    /**
     * Get the type.
     *
     * Sentinel value: the null rule is never stored or validated as a rule type.
     */
    public static function type(): AlertRuleType
    {
        return AlertRuleType::FailureCount;
    }

    /**
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId): array
    {
        return ['triggered' => false, 'job_uuids' => []];
    }
}
