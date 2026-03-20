<?php

namespace App\Services\Alerts\Rules;

use App\Models\Alert;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategyInterface;

final class NullAlertRuleStrategy implements AlertRuleStrategyInterface {

    /**
     * Evaluate the rule and return whether it triggered plus triggering job UUIDs (if applicable).
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string|null $jobUuid
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId, ?string $jobUuid): array {
        return ['triggered' => false, 'job_uuids' => []];
    }
}
