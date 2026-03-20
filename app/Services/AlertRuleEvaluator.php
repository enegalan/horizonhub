<?php

namespace App\Services;

use App\Models\Alert;
use App\Services\Alerts\Rules\AlertRuleStrategyRegistry;

class AlertRuleEvaluator {

    private AlertRuleStrategyRegistry $strategyRegistry;

    public function __construct(AlertRuleStrategyRegistry $strategyRegistry) {
        $this->strategyRegistry = $strategyRegistry;
    }

    /**
     * Evaluate the given alert rule for the provided context.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string|null $jobUuid
     * @return bool
     */
    public function evaluate(Alert $alert, int $serviceId, ?string $jobUuid): bool {
        return $this->evaluateWithTriggeringJobs($alert, $serviceId, $jobUuid)['triggered'];
    }

    /**
     * Evaluate the alert rule and return whether it triggered plus the list of job UUIDs that triggered it.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string|null $jobUuid
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId, ?string $jobUuid): array {
        $strategy = $this->strategyRegistry->resolve((string) $alert->rule_type);

        return $strategy->evaluateWithTriggeringJobs($alert, $serviceId, $jobUuid);
    }
}
