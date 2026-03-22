<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use App\Services\Alerts\Rules\AlertRuleStrategyRegistry;

class AlertRuleEvaluator
{
    private AlertRuleStrategyRegistry $strategyRegistry;

    public function __construct(AlertRuleStrategyRegistry $strategyRegistry)
    {
        $this->strategyRegistry = $strategyRegistry;
    }

    /**
     * Evaluate the given alert rule for the provided context.
     */
    public function evaluate(Alert $alert, int $serviceId, ?string $jobUuid): bool
    {
        return $this->evaluateWithTriggeringJobs($alert, $serviceId, $jobUuid)['triggered'];
    }

    /**
     * Evaluate the alert rule and return whether it triggered plus the list of job UUIDs that triggered it.
     *
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId, ?string $jobUuid): array
    {
        $strategy = $this->strategyRegistry->resolve((string) $alert->rule_type);

        return $strategy->evaluateWithTriggeringJobs($alert, $serviceId, $jobUuid);
    }
}
