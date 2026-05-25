<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy as AlertRuleContract;

final class NullRule implements AlertRuleContract
{
    /**
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId): array
    {
        return ['triggered' => false, 'job_uuids' => []];
    }
}
