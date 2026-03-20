<?php

namespace App\Services\Alerts\Rules\Contracts;

use App\Models\Alert;

interface AlertRuleStrategyInterface {

    /**
     * Evaluate the rule and return whether it triggered plus triggering job UUIDs (if applicable).
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string|null $jobUuid
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId, ?string $jobUuid): array;
}
